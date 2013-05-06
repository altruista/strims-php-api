<?php

require_once "Strims.class.php";

header("Content-type:text/plain;charset=utf-8");
$strims = new Strims();

$entries = $strims->get_entries('u/altruista');

if($strims->login('altruista', 'xxxxx')) {
	$strims->post_entry('Ciekawostki', 'ciekawy wpis');
	$strims->post_link('Ciekawostki', 'ciekawy link', 'http://google.pl');
}

