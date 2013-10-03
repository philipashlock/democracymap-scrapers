<?php

require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';

require 'source/state-list.php';

$directory_url = "http://test.dev/scraper/source/state-emo.html";

$records = get_sources($directory_url);


$response['states'] = $state_list->states;
$response['em'] = $records;

foreach ($records as $record) {
	//scraperwiki::save(array('city'), $record)
}

header('Content-type: application/json');
print json_encode($response);


function get_sources($url) {
    
    
    $html = scraperWiki::scrape($url);

    $dom = new simple_html_dom();
    $dom->load($html);

    $list = $dom->find("article[id=node-site-page-32056]", 0);

	$list = $list->find("div[class=field-item]", 0);

	$count = 1;
    foreach($list->find("p") as $row){
		
		if ($row->find("br", 0)) {

			$data = $row->innertext;
			$data = explode("<br>", $data);

			$link = $row->find("a",0)->href;
			
			if(!empty($link)) {
				if (preg_match("/redirect\?url\=/", $link)) {
					$start = strpos($link, 'http://www.fema.gov/redirect?url=') + strlen('http://www.fema.gov/redirect?url=');
					$length = strlen($link) - $start;
					$link = substr($link, $start, $length);
					$link = urldecode($link);
				}
			}
			
			$clean = null;
			foreach ($data as $key => $value) {
				
				
				if ($key == 0) {
					$clean['title'] = $value;
				}
				if ($key == 1) {
					
					// if it doesn't contain a number
					if(!preg_match('/[0-9]/', $value)) {
						$clean['title'] .= ', ' . $value;
					} else {
						$clean['address_1'] = $value;
					}
				}
				
				if ($key > 1) {
					
					// Check to see if it's a phone num					
					if((substr($value, 0, 1) == '(') || preg_match("/:\ \(/", $value) || preg_match("/:\(/", $value)) {
						
						if(!preg_match("/Fax/i", $value)) {
							$clean['phone'][] = $value;
						}
					}
					else {
						// if it contains a number
						if(preg_match('/[0-9]/', $value)) {
							if(empty($clean['address_1'])) $clean['address_1'] = $value;
							if(!empty($clean['address_1']) && empty($clean['address_2']) && $clean['address_1'] !== $value) $clean['address_2'] = $value;
							if(!empty($clean['address_1']) && !empty($clean['address_2']) && !preg_match("/<a /", $value)) $clean['address_full'] = $value;					
						} 
					}		
				}	
				
				
				// double check that address_2 is accurate 
				if(!empty($clean['address_2'])) {
					
					$address_2 = $clean['address_2'];
					
					if(preg_match("/P.O./i", $address_2) || preg_match("/Floor/i", $address_2) || preg_match("/Suite/i", $address_2) || preg_match("/PO /i", $address_2)) {
						$clean['address_2'] = $address_2;
					}
					else {
						$clean['address_2'] = null;
						
						if(!preg_match("/<a /", $address_2)) {
							$clean['address_full'] = $address_2;
						}

					}
				}
				
				
			if(!empty($clean['address_full'])) {
				$address_full = $clean['address_full'];
				
					$clean['address_city'] = trim(substr($address_full, 0, strrpos($address_full, ', ')));
					
					$start = strrpos($address_full, ', ')+2;
					$end = strrpos($address_full, ' ')+1;
					$length = $end - $start;
					$clean['address_state'] = trim(substr($address_full, $start, $length));
					$clean['address_zip'] = substr($address_full, strrpos($address_full, ' ')+1);				
			}
				
				
			}

			
			$clean['url'] = $link;
			
			if (empty($clean['address_city'])) {
				if (preg_match("/([A-Z]{2})/", $clean['address_full'])) {

					preg_match("/([A-Z]{2})/", $clean['address_full'], $matches);					
					$clean['address_state'] = $matches[0];
				}
			}
			
			if(!preg_match("/<a /i", $clean['title'])) {
			
				$raw_data[] = $data;
				$records[] = $clean;
			
			}
		}
		
    }
    
    return array('record' => $records, 'raw' => $raw_data);
        
}





?>