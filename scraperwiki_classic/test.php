<?php 

require 'libs/scraperwiki.php';
require 'libs/scraperwiki/simple_html_dom.php';



$html = scraperWiki::scrape("http://answers.usa.gov/system/selfservice.controller?CONFIGURATION=1000&PARTITION_ID=1&CMD=VIEW_ARTICLE&USERTYPE=1&LANGUAGE=en&COUNTRY=US&ARTICLE_ID=9829");

$dom = new simple_html_dom();
$dom->load($html);

$count = 1;

foreach($dom->find("div[id=dashboard_main_content] tr") as $data){
	

    $ths = $data->find("th");
    $tds = $data->find("td");

	$heading = $ths[0]->plaintext;
	$object = $tds[0];
	$value = $object->plaintext;

		if(!$heading) {
			$heading = "Extra Field $count:";
			$count++;			
		}

		$heading = substr($heading, 0, strpos($heading, ":")); 
		$clean_heading = str_replace(' ', '_', strtolower($heading));

        $record[$clean_heading] = array('name' => $heading, 'value' => $value);

		switch ($clean_heading) {
			case 'official_name';
			case 'governor';
				$value = $object->find("a", 0);
				$value = $value->href;
				$clean_heading = $clean_heading . "_url";
				$heading = "$heading URL";
				$record[$clean_heading] = array('name' => $heading, 'value' => $value);
				break;
		}

}	


header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

print json_encode($record);

//var_dump($record);



?>