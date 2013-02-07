<?php

$state = 'Alabama';

$state = ucwords($state);		

$query = "select * from `swdata` where state = '$state' limit 1";		
$query = urlencode($query);

$url = "https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=50_states_data&query=$query";		



$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
$state=curl_exec($ch);
curl_close($ch);

$state = json_decode($state, true);

var_dump($state);

echo $url;





?>