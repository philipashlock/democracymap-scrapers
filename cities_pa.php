<?php

require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';


$cities = "http://munstatspa.dced.state.pa.us/ReportViewer.axd?R=LocalOfficial&F=C";

$data = scraperWiki::scrape($cities);

$lines = explode("\n", $data);

$count = 1;
foreach($lines as $row) {  
	$row = str_getcsv($row); 
	
	$output[] = $row;
	
	if ($count > 5) break;
	$count++;
}


//$header = str_getcsv(array_shift($lines));

// foreach($lines as $row) {
//     $row = str_getcsv($row);
//     if ($row[0]) {
//         $record = array_combine($header, $row);
//         $record['Amount'] = (float)$record['Amount'];
//         scraperwiki::save(array('Transaction Number', 'Expense Type', 'Expense Area'), $record);
//     }
// }





header('Content-type: application/json');
print json_encode($output);


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