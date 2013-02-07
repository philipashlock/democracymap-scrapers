<?php
  

/// -------------------------------- First download the file --------------------------------

$url  = 'http://www.usa.gov/About/developer_resources/allfaqs.xml';
//$xml_file_path = '/tmp/allfaqs.xml';
$xml_file_path = '/Users/philipashlock/Sites/test.dev/scraper/faq-data/allfaqs.xml';


// $ch = curl_init($url);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// 
// $data = curl_exec($ch);
// 
// curl_close($ch);
// 
// file_put_contents($xml_file_path, $data);
                                          
/// ------------------------------------------------------------------------------------------



$records = get_sources($xml_file_path);

foreach ($records as $record) {
	//scraperwiki::save(array('city'), $record)
}

header('Content-type: application/json');
print json_encode($records);


function get_sources($xml_file_path) {
    


	// Specify configuration
	$config = array(
	           'indent'         => true,
	           'output-xhtml'   => true,
	           'output-html'   => false,	
			   'show-warnings'	=> false,
			   'show-body-only' => true,
	           'wrap'           => 200);
	

	$count = 1;


	$XMLReader = new XMLReader;	
	$XMLReader->open($xml_file_path);

	// Move to the first "[item name]" node in the file.
	while ($XMLReader->read() && $XMLReader->name !== "Row");


	// Now that we're at the right depth, hop to the next "[item name]" until the end of tree/file.
	while ($XMLReader->name === "Row" && $count < 70) {
		
		if ($count > 1) {
			
			$xml = null;
			$xml = $XMLReader->readOuterXML();
			//$xml = html_entity_decode($xml, ENT_NOQUOTES | XML1, 'UTF-8');
			//$xml = str_replace('&deg;', '', $xml);
			//$xml = htmlspecialchars_decode($xml, ENT_NOQUOTES | ENT_HTML401);

			$xml = insert_cdata($xml);
			
			$xml_validate = check_xml($xml);
			
			if ($xml_validate === true) {
			
				$node = new SimpleXMLElement($xml);			

				$record = null;
				//if($count > 5) exit;

				$record['url'] 			= (string)$node->Item[0];
			
				$record['faq_id']			= substr($record['url'], strpos($record['url'], '?p_faq_id=') + 10);
			
				$record['question'] 	= (string)$node->Item[1];
				//$record['answer'] 		= (string)$node->Item[2];  
		
		
				$search = array('<![CDATA[', ']]>', '<BR>', '</LI>', '</P>', '</UL>', '&nbsp;');
				$replace = array('', '', " \n", "</LI> \n", "</P> \n\n", "</UL> \n\n", ' ');
		
				$answer = (string)$node->Item[2];
				$answer = html_entity_decode($answer);
				$answer_clean 				= str_replace($search, $replace, $answer);
				$record['answer_text'] 		= strip_tags($answer_clean);                

				$tidy = new tidy;
				$tidy->parseString($answer, $config, 'utf8');
				$tidy->cleanRepair();

				$record['answer_html'] = $tidy->value;		
		              
				$record['ranking'] 		= (string)$node->Item[3];                        
				$record['last_updated'] = (string)$node->Item[4];
				$record['last_updated'] = ($record['last_updated']) ? date(DATE_ATOM, strtotime($record['last_updated'])) : null;
				//$today = date("Y-m-d H:i:s");
		                        
				$record['topic'] 		= (string)$node->Item[5];                        
				$record['subtopic'] 	= (string)$node->Item[6];

				// Set empty strings as null
				array_walk($record, 'check_null');

				$records[] = $record;
			}
			else {
				return $xml_validate;
			}
		}

		// Skip to the next node of interest.
		$XMLReader->next("Row");
		$count++;
	}

	 return $records;
 
}

function check_null(&$value) {
	$value = (empty($value)) ? null : $value;
}


function insert_cdata($xml) {
	
	$start = null;
	$position = null;
	while($position = strpos($xml, '<Item>', $position+1)) {
		$start[] = $position+1;
	}
	
	if($start[2] > ($start[1] + 6)) {
	    
		$xml = str_insert('<![CDATA[', $xml, $start[2]+5);
	
		$position = null;	
		$end = null;
		while($position = strpos($xml, '</Item>', $position+1)) {
			$end[] = $position+1;
		}						
	
		$xml = str_insert(']]>', $xml, $end[2]-1);			

	}
	
	return $xml;	
	
}


function str_insert($insertstring, $intostring, $offset) {
   $part1 = substr($intostring, 0, $offset);
   $part2 = substr($intostring, $offset);
  
   $part1 = $part1 . $insertstring;
   $whole = $part1 . $part2;
   return $whole;
}


function check_xml($xml){
    libxml_use_internal_errors(true);

    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->loadXML($xml);

    $errors = libxml_get_errors();

    if(empty($errors)){
        return true;
    }

    $error = $errors[0];
    if($error->level < 3){
        return true;
    }

    $explodedxml = explode("r", $xml);
    $badxml = $explodedxml[($error->line)-1];

    $message = $error->message . ' at line ' . $error->line . '. Bad XML: ' . htmlentities($badxml);
    return array('source' => $xml, 'error' => $message);
}




// notes from ruby parser

//  doc.root.elements.each('Row') do |row|
//    if counter > 0
//      items = row.elements.to_a('Item')
//      Faq.create(:url      => items[0].text,
//                 :question => items[1].text.gsub(/<\/?[^>]*>/, ""),
//                 :answer   => items[2].text,
//                 :ranking  => items[3].text.to_i,
//                 :locale   => locale)
//    end
//    counter += 1
//  end




?>