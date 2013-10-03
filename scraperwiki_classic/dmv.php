<?php

require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';


$state_directory_url = "http://www.usa.gov/Topics/Motor-Vehicles.shtml";

$state_sources = get_state_sources($state_directory_url);


// Sort the array of states alphabetically
foreach ($state_sources as $key => $row) { $state[$key] = $row['state']; }
array_multisort($state, SORT_ASC, $state_sources);








header('Content-type: application/json');
print json_encode($state_sources);


function get_state_sources($url) {
    
    
    $html = scraperWiki::scrape($url);

    $dom = new simple_html_dom();
    $dom->load($html);

    $list = $dom->find("div[class=three_column_container]", 0);

    foreach($list->find("ul[class=three_column_bullets]") as $column){

        foreach($column->find("li") as $items){

                $state = $items->plaintext;
				$state = trim(str_replace("\t", "", $state));
				

                $url = $items->find('a', 0);
                $url = $url->href;

                $source[] = array ('state' => $state, 'url' => $url);


        }


    }
    
    return $source;
        
}





?>