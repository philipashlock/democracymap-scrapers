<?php

$run_environment = 'dev'; // either 'dev' or 'prod'
$max_records = 4; // only used for testing

if ($run_environment == 'dev') {
	error_reporting(E_ALL); 	
    require 'scraperwiki.php';
}


require 'scraperwiki/simple_html_dom.php';



// There's a bug of some kind that makes the scraper time out or think its finished after California (CA) 
// so we're trying to keep track of where things left off and start there again if it stops midway (see the custom 'scraper_state' and 'last_state' variables)

if ($run_environment == 'dev') {
	$states = array("AL", "AK");
} else {
	$states = array("AL","AK","AZ","AR","CA","CO","CT","DC","DE","FL","GA","GU","HI","ID","IL","IN","IA","KS","KY","LA","ME","MD","MA","MI","MN","MO","MP","MS","MT","NE","NV","NH","NJ","NM","NY","NC","ND","OH","OK","OR","PA","PR","RI","SC","SD","TN","TX","UT","VT","VA","WA","WV","WI","WY");	
}



$url = "http://www.usmayors.org/meetmayors/mayorsatglance.asp";

// Set state of scraper as running in case we crash part way thru
scraperwiki::save_var('scraper_state', 'running');


foreach ($states as $state) {
	
	// Check if we failed part way through a run and find where we left off
	if (scraperwiki::get_var('scraper_state') == 'running') {
		
		$last_state = scraperwiki::get_var('last_state');
		
		if (!empty($last_state)) {
			if ($last_state !== $state) {
				continue;
			} 
		} else {
			scraperwiki::save_var('last_state', $state);
		}
				
	}	
	
	
	
	
	
	//set POST variables
	$fields = array(
	            'mode' => 'search_db',
	            'State' => $state
	        );

	$result = get_post_response($url,$fields);
	

	// used for debugging
	if ($run_environment == 'dev') {
		if(empty($records)) {
			$records = get_mayors($result);				
		} else {
			$records = array_merge($records, get_mayors($result));
		}
	} else {
		get_mayors($result);
		// sleep(10); // this might be needed on scraperwiki.com	
	}
		
	// reset the progress bookmark
	scraperwiki::save_var('last_state', '');
}	


// Set state of scraper to complete so we know it didn't crash part way thru
scraperwiki::save_var('scraper_state', 'complete');


// if testing
if ($run_environment == 'dev') {
    header('Content-type: application/json');
    print json_encode($records);
} 

function get_post_response($url,$fields) {
	
	$fields_string = '';
	
	//url-ify the data for the POST
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');

	//open connection
	$ch = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($ch,CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);		
	curl_setopt($ch,CURLOPT_POST, count($fields));
	curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

	//execute post
	$result = curl_exec($ch);

	//close connection
	curl_close($ch);
	
	return $result;
		
}





function get_mayors($html) {
    
    global $run_environment;

    $dom = new simple_html_dom();
    $dom->load($html);

    
    foreach ($dom->find("div[@align=center] table") as $data) {
        
    
        //$image = $data->find("table[class=pagesSectionBodyTight] td", 0);
        //$text = $data->find("td", 0);

		//$name = $data->find("strong", 0);
		$raw = $data->innertext;
		$mayor = $data->find("strong", 0);
		
		if(!$mayor) continue;
		
		$mayor = $mayor->innertext;
		
		$name = substr($mayor, 0, strpos($mayor, '<br>'));
		$location = substr($mayor, strpos($mayor, '<br>') + 4);
		$city = substr($location, 0, strpos($location, ','));
		$state = substr($location, strpos($location, ',') + 2);		

		$bio_url = $data->find("a[class=pagesSectionBodyTight]", 0);
		if($bio_url) {
 			$bio_url = $bio_url->href;
		} else {
			$bio_url = null;
		}



		$info = null;
		$raw = null;
		
		// These conditions could probably be explicitly associated with each piece of data, but the 
		// variation with "not available" seemed like it could apply to anything, so I figured I'd be 
		// careful and test all condiations against each piece of data
		foreach ($data->find("table[class=pagesSectionBodyTight] td") as $row) {
			
			$raw_info = $row->innertext;
			
			$raw[] = $raw_info;
			
			
			$start = 0;
			$end = strlen($raw_info);
			
			if((strpos($raw_info, '<b>')) && (strpos($raw_info, '</b>'))) {			
				$start = strpos($raw_info, '<b>') + 3;
				$end = strpos($raw_info, '</b>');
			}
			
			// this is to catch the wild card of "not available"
			if(strpos($raw_info, '<i>')) {
				$start = strpos($raw_info, '<i>') + 3;
				$end = strpos($raw_info, '</i>');
			}
			
			if((strpos($raw_info, '<b>')) && (!strpos($raw_info, '</b>'))) {
				$start = strpos($raw_info, '<b>') + 3;
				$end = strpos($raw_info, '</a></B>') - 2;
			}
			
			if(strpos($raw_info, '<a href=') && (!strpos($raw_info, '</a></B>'))) {
				$start = strpos($raw_info, '">') + 2;
				$end = strpos($raw_info, '</a>');
			}	
			
			if(strpos($raw_info, 'height=270 width=216')) {
				$start = strpos($raw_info, '<img src=') + 9;				
				$end = strpos($raw_info, 'height=270') -1;
			}			

			$length = $end - $start;	
		
			$info[] =  substr($raw_info, $start, $length);
		}
		
		$url_photo = ($info[5]) ? 'http://www.usmayors.org' . $info[5] : null;
		$next_election = is_numeric(substr($info[2], 0, 1)) ? date("Y-m-d", strtotime($info[2])) : null;

		if($name) {
			
			$official = official();
			
			
			//$record[] = array...... - used for debugging
			$record = array('name'=>$name, 
							   	'city' => $city, 
								'state' => $state, 
							   	'population' => $info[0], 
								'phone' => $info[1],
							   	'next_election' => $next_election, 
								'email' => $info[3],
								'url' => $info[4],
								'bio_url' => $bio_url,
								'url_photo' => $url_photo																
								);
			
								$official['government_name']		=	$city;
								$official['government_level']		=	'municipal';
								$official['type']					=	'executive';
								$official['title']					=	'Mayor';
								//$official['description']			=	;
								//$official['name_given']				=	;
								//$official['name_family']			=	;
								$official['name_full']				=	$name;
								$official['url']					=	$info[4];
								$official['url_photo']				=	$url_photo;
								//$official['url_schedule']			=	;
								//$official['url_contact']			=	;
								$official['email']					=	$info[3];
								$official['phone']					=	$info[1];
								//$official['address_name']			=	;
								//$official['address_1']				=	;
								//$official['address_2']				=	;
								//$official['address_locality']		=	;
								$official['address_region']			=	$state;
								//$official['address_postcode']		=	;
								//$official['current_term_enddate']	=	;
								//$official['last_updated']			=	;
								//$official['social_media']			=	;
								$official['other_data']				=	json_encode(array('biography_url' => $bio_url, 'next_election' => $next_election));
								//$official['conflicting_data']		=	;
								$official['sources']				=	json_encode(array(array('description' => null, 'url' => 'http://usmayors.org/meetmayors/mayorsatglance.asp', "timestamp" => gmdate("Y-m-d H:i:s") )));;
		
		
		
		
			
		 if ($run_environment == 'dev') {
			$officials[] = $official;		 	
		 } else {
			scraperwiki::save_sqlite(array('title','name_full','government_name'), $official, $table_name='officials'); 
		}		
		 

					
		}

	}
    

	 if ($run_environment == 'dev') {
		return $officials;		 	
	 } else {
		return true;
	}
  
    
}


function official() {
	
	$official = array(
		'government_name'		=> NULL,                 		
		'government_level'		=> NULL,                 		
		'type' 					=> NULL,                 		
		'title' 				=> NULL,                		
		'description' 			=> NULL,          		
		'name_given' 			=> NULL,           		
		'name_family' 			=> NULL,          			
		'name_full' 			=> NULL,            			
		'url' 					=> NULL,                  
		'url_photo' 			=> NULL,            
		'url_schedule' 			=> NULL,         
		'url_contact' 			=> NULL,          
		'email' 				=> NULL,                
		'phone' 				=> NULL,                
		'address_name' 			=> NULL,         
		'address_1' 			=> NULL,            
		'address_2' 			=> NULL,            
		'address_locality' 		=> NULL,         
		'address_region' 		=> NULL,        
		'address_postcode' 		=> NULL,          
		'current_term_enddate' 	=> NULL, 
		'last_updated' 			=> NULL,         			
		'social_media' 			=> NULL,         			
		'other_data' 			=> NULL,
		'conflicting_data'		=> NULL,
		'sources' 				=> NULL				         			

	);
	
	return $official;
	
}




?>