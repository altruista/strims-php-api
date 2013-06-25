<?php

require_once "Strims.class.php";

$strims = new Strims();

$entries = $strims->get_entries('u/altruista');

if ($strims->login('uzytkownik', 'haslo')) {
    $strims->post_entry('Ciekawostki', 'ciekawy wpis');
    $strims->post_link('Ciekawostki', 'ciekawy link', 'http://google.pl');
}


