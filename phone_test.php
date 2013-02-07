<?php

$phone_list = '1.602.542.4900 E-mail: azgov@azgita.gov Additional Contact Information';


$phone_list = preg_replace('/[^0-9]/','',$phone_list);

$pattern = '/(?:\+|00)?(\d[\d\s]{9,10})/';
preg_match_all($pattern, $phone_list, $matches);
$numbers = array();
if (isset($matches[1])) {
    foreach ($matches[1] as $match) {
        $numbers[] = str_replace(' ', '', $match);
    }
}

var_dump($numbers);


?>