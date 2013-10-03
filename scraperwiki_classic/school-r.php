<?php

require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';

$directory_url = "http://schools.nyc.gov/Home/InOurSchoolsToday/2012-2013/cancellations";

$records = get_sources($directory_url);

$records = objectToArray( $records );

$count = 1;
foreach ($records as $record) {
	
	$impacted = $record['impacted'];
	$receiving = $record['receiving'];

	
	$export = array();
	
	$export['program'] = $record['program'];
	$export['category'] = $record['category'];	
	
	$export['bn'] = $impacted['bn'];
	$export['name'] = $impacted['name'];	
	$export['principle'] = $impacted['principle'];	
	$export['staffopen'] = $impacted['staffopen'];	
	$export['studentopen'] = $impacted['studentopen'];		

	$export['receiving_bn'] = $receiving['bn'];
	$export['receiving_name'] = $receiving['name'];	
	$export['receiving_principal'] = $receiving['principle'];	
	$export['receiving_address'] = $receiving['address'];	
	$export['receiving_borough'] = $receiving['borough'];	
	$export['receiving_zip'] = $receiving['zip'];		
	
	
	$export['index'] = $export['bn'] . ' ' . $export['program'];
	
	//scraperwiki::save(array('index'), $export);
	
	$exportall[] = $export;
	
}



header('Content-type: application/json');
print json_encode($exportall);

function get_sources($url) {
    
    
    $html = scraperWiki::scrape($url);

    $dom = new simple_html_dom();
    $dom->load($html);

    $list = $dom->find("div[id=topcenter]", 0);

	$script = $list->find("div[class=searchform]", 0)->find("script", 0);
	
	$script = $script->innertext;
	
	$data = substr($script, strpos($script, 'impacted:'));
	
	$length = strlen($data) - strpos($data, '$(document).ready(function ()');
	$length = strlen($data) - $length;
	$data = substr($data, 0, $length);
	
	$data = '[{ ' . $data;
	
	$data = trim($data);
	$data = substr($data, 0, strlen($data) - 2);
	$data = trim($data);
	
	$data = preg_replace('/James Baldwin School, The:/', 'James Baldwin School, The', $data);
	$data = preg_replace('/The Christa Mcauliffe School\\\\I.S. 187/', 'The Christa Mcauliffe School', $data);	
	$data = preg_replace('/Academy Of Medical Technology:/', 'Academy Of Medical Technology', $data);

    $data = preg_replace('/(\w+):/i', '"\1":', $data);


	
	$data = json_decode($data);


	return $data;
	

}



function objectToArray( $object )
{
    if( !is_object( $object ) && !is_array( $object ) )
    {
        return $object;
    }
    if( is_object( $object ) )
    {
        $object = get_object_vars( $object );
    }
    return array_map( 'objectToArray', $object );
}

function my_json_decode($s) {
       $s = str_replace(
           array('"',  "'"),
           array('\"', '"'),
           $s
       );
       $s = preg_replace('/(\w+):/i', '"\1":', $s);
       return json_decode(sprintf('{%s}', $s));
   }




?>