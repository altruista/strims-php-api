<?php

function find_between($str, $a, $b = false)
{
    $tmp = explode($a, $str);
    unset($tmp[0]);
    $result = Array();
    foreach ($tmp as $chunk) {
        if ($b === false && $chunk) {
            $result[] = $chunk;
            continue;
        }
        list($add) = explode($b, $chunk);
        if ($add) {
            $result[] = $add;
        }
    }
    return $result;
}

function find_one_between($str, $a, $b = false)
{
    $result = find_between($str, $a, $b);
    return isset($result[0]) ? $result[0] : false;
}

class API_Curl
{
    private $_ch = false;
    protected $_config = Array('cookie_file' => 'cookie.txt', 'user_agent' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1');
    public $html;

    public function __construct() {    	    	
    	if(!is_writeable($this->_config['cookie_file'])) {
    		throw new Exception("Plik {$this->_config['cookie_file']} nie ma uprawnien do zapisu!");
    	}
    }
    
    private function _curl_init()
    {
        if ($this->_ch) {
            curl_close($this->_ch);
        }
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_COOKIEFILE, $this->_config['cookie_file']);
        curl_setopt($this->_ch, CURLOPT_COOKIEJAR, $this->_config['cookie_file']);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, 1);
        
        curl_setopt($this->_ch, CURLOPT_USERAGENT, $this->_config['user_agent']);
    }
    private function _curl_close()
    {
        curl_close($this->_ch);
        $this->_ch = false;
    }
    public function get($url)
    {
        $this->_curl_init();
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        $this->html = curl_exec($this->_ch);
        $this->_curl_close();
        return $this->html;
    }
    public function post($url, $data)
    {
        $this->_curl_init();
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        curl_setopt($this->_ch, CURLOPT_POST, 1);
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $this->html = curl_exec($this->_ch);
        $this->_curl_close();
        return $this->html;
    }
}

class Strims extends API_Curl
{
    private $_strims_domain = 'http://strims.pl/';
    private $_token;
    private $_logged_in;

    public function get($url)
    {
        return parent::get($this->_strims_domain . $url);
    }
    public function post($url, $data)
    {
        return parent::post($this->_strims_domain . $url, $data);
    }
    
    public function get_token()
    {
        $this->get('zaloguj');
        return find_one_between($this->html, "page_template.token = '", "'");
    }
    
    /**
     * Logowanie do strims.pl
     * @param string $username Nazwa użytkownika
     * @param string $password Hasło uzytkownika
     * @return bool true jeśli udało się zalogować
     */
    public function login($username, $password)
    {
    	$token = $this->get_token();
        $login_postdata = Array(
            'token' => $token,
            '_external[remember]' => 1,
            'name' => $username,
            'password' => $password
        );
        $this->post('zaloguj', $login_postdata);
        $result = stripos($this->html, 'wyloguj') !== false;
        if($result) {
        	$this->_logged_in = true;
        	$this->_token = $token;
        }
        return $result;
    }

    /**
     * Pobieranie wpisów
     * @param bool|string $strim skad wpisy np. "s/Ciekawostki", "u/Uzytkownik" lub false jeśli główne wpisy
     * @param int $page numer strony zaczynajać od 1
     * @return array Tablica z wynikami
     */
    public function get_entries($strim = false, $page = 1)
    {
        $this->get($strim . '/wpisy' . ($page == 1 ? "" : "?strona={$page}"));
        $tmp = find_between($this->html, 'entry   level_0', '</ul');
        
        $entries = Array();
        foreach($tmp as $div) {
			$div_user = find_one_between($div, '<div class="entry_user">', '</a>');
			$div_info = find_one_between($div, 'entry_info', false);

        	$entry = new stdClass;        	
        	$entry->user 	= find_one_between($div_user, '<span class="bold">', '</span>');
        	$entry->id 		= find_one_between($div, '<a id="', '" class="anchor"></a>');
        	$entry->html 	= find_one_between($div, 'div class="markdown">', '</div>');
        	$entry->text 	= strip_tags($entry->html);
        	$entry->strim 	= find_one_between($div_info, 'href="/s/', '/');
        	
        	$entries[] = $entry;
        }
        return $entries;
    }

    /**
     * Dodawanie wpisu
     * @param bool|string $strim dokad wpis np. "Ciekawostki" lub false jeśli do głównego 
     * @param string $content Treść wpisu
     * @return object odpowiedź ajax ze strimsa
     */
    public function post_entry($strim = false, $content)
    {
    	if(!$this->_logged_in) {
    		throw new Exception("Musisz byc zalogowany!");
    	}
    	$entry_postdata = Array(
    		'token'				=> $this->_token,
    		'_external[parent]'	=> '',
    		'text'				=> $content,
    		'_external[strim]'	=> $strim
		);
		$result = $this->post('ajax/wpisy/dodaj', $entry_postdata);
		return json_decode($result);
    }

	/**
     * Dodawanie treści (link)
     * @param string $strim dokad wpis np. "Ciekawostki" lub false jeśli do głównego 
     * @param string $title tytuł
     * @param string $url url odnośnika
     * @param bool $thumb miniaturka true/false     
     */
    public function post_link($strim, $title, $url, $thumb = true)
    {
    	if(!$this->_logged_in) {
    		throw new Exception("Musisz byc zalogowany!");
    	}
    	$link_postdata = Array(
    		'token'				=> $this->_token,    		
			'kind'				=> 'link',
			'title'				=> $title,
			'url'				=> $url,
			'text'				=> '',
			'_external[strim]'	=> $strim,
			'media'				=> $thumb ? 1 : 0
		);
		$this->post('s/'.$strim.'/dodaj', $link_postdata);		
    }    


    
}
