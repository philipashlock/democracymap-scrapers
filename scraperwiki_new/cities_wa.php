<?php

$run_environment = 'dev'; // either 'dev' or 'prod'
$max_records = 4; // only used for testing


require 'rb.php';if(empty($_SERVER["SERVER_ADDR"])OR stripos($_SERVER["SERVER_ADDR"],'127.0.0.1')===false){new scraperwiki();}class scraperwiki{protected $db;public function __construct($db='sqlite:scraperwiki.sqlite'){scraperwiki::_connect($db);}static function _connect($db=null){if(empty($db)){R::setup();}else{R::setup($db);}}static function save($unique_keys=array(),$data,$table_name="swdata",$date=null){$ldata=$data;if(!is_null($date))$ldata["date"]=$date;return scraperwiki::save_sqlite($unique_keys,$ldata,$table);}static function save_sqlite($unique_keys=array(),$data,$table_name='swdata'){if(count($data)==0)return;$table=R::dispense($table_name);foreach($data as&$value){if($value instanceof DateTime){$new_value=clone $value;$new_value->setTimezone(new DateTimeZone('UTC'));$value=$new_value->format(DATE_ISO8601);assert(strpos($value,"+0000")!==FALSE);$value=str_replace("+0000","",$value);}}unset($value);foreach($data as $key=>$value){$table->$key=$value;}if(!R::$redbean->tableExists($table_name)){if(!empty($unique_keys)){}R::store($table);return true;}if(!empty($unique_keys)){$parameters['table_name']=$table_name;$parameters['keys']=join(", ",array_keys($data));$parameters['values']=join(', ',array_fill(0,count($data),'?'));$sql=vsprintf('INSERT or REPLACE INTO %s (%s) VALUES (%s)',$parameters);R::exec($sql,array_values($data));return true;}else{R::store($table);return true;}}static function save_var($name,$value){$vtype=gettype($value);if(($vtype!="integer")&&($vtype!="string")&&($vtype!="double")&&($vtype!="NULL"))print_r("*** object of type $vtype converted to string\n");$data=array("name"=>$name,"value_blob"=>strval($value),"type"=>$vtype);scraperwiki::save_sqlite(array("name"),$data,"swvariables");}static function get_var($name,$default=null){$data=R::findOne('swvariables',' name = ? ',array($name));if(!$data)return $default;$svalue=$data->value_blob;$vtype=$data->type;if($vtype=="integer")return intval($svalue);if($vtype=="double")return floatval($svalue);if($vtype=="NULL")return null;return $svalue;}static function sqliteexecute($sqlquery=null,$data=null,$verbose=1){if(!empty($data)){}$result=R::exec($sqlquery);return $result;}static function select($sqlquery,$data=null){$result=scraperwiki::sqliteexecute("select ".$sqlquery,$data);return $result;}static function scrape($url){$curl=curl_init($url);curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);curl_setopt($curl,CURLOPT_MAXREDIRS,10);curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);$res=curl_exec($curl);curl_close($curl);return $res;}}
require 'scraperwiki/simple_html_dom.php';

if ($run_environment == 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors','On');   

	new scraperwiki('');
}

// Set state of scraper as running in case we crash part way thru
scraperwiki::save_var('scraper_state', 'running');
scraperwiki::save_var('last_start', gmdate("Y-m-d\TH:i:s\Z"));

$city_directory = "http://www.mrsc.org/cityprofiles/citylist.aspx";

    
$cities = get_cities($city_directory);

$count = 1;

foreach ($cities as $city) {
   
    if ($run_environment == 'prod') {
        scraperwiki::save_sqlite(array('data_url'), $city, $table_name='city');    
        get_city_data($city['name'], $city['officials_url']);
    }
    else {
        $reps    =     get_city_data($city['name'], $city['officials_url']);
        $alldata[] = array('city' => $city, 'reps' => $reps);
    }
        
    $count++;
    if ($run_environment == 'dev' && $count > $max_records) break;
}

if ($run_environment == 'prod') {
    sleep(3); // this may be needed on scraperwiki.com
}    
    

// Set finish time
scraperwiki::save_var('last_finish', gmdate("Y-m-d\TH:i:s\Z"));

// Set total number of rows
if ($result = scraperwiki::select('count(*) as count from jurisdictions')) {
	if (!empty($result[0]['count'])) {
		$count = $result[0]['count'];
	} 
}

$count = empty($count) ? null : $count;
scraperwiki::save_var('rows_scraped', $count);
scraperwiki::save_var('scraper_state', 'ok');


// if testing
if ($run_environment == 'dev') {
    header('Content-type: application/json');
    print json_encode($alldata);
}



function get_cities($url) {
    
    global $base_url;
    
    $html = scraperWiki::scrape($url);
    
    $dom = new simple_html_dom();
    $dom->load($html);

    $count = 1;

    $table = $dom->find("table[id=dgCities]", 0);
    
    // echo $table->find("tr", 1)->outertext; exit;
    
    foreach($table->find("tr") as $data){
            
        // Skip the header row
        if($count < 2) {
            $count++;
            continue;
        }

        $tds = $data->find("td");

        if ($object = $tds[0]->find("a", 0)) {        
            
            $city['name']                 = trim($object->plaintext);
            $city['data_url']             = 'http://www.mrsc.org/cityprofiles/' .  $object->href;
            $city['population']            = trim($tds[1]->plaintext);
            $city['county']                = trim($tds[2]->plaintext);            
            $city['class']                = trim($tds[3]->plaintext);            
            $city['gov_type']            = trim($tds[4]->plaintext);                                    

            $city['officials_url']         = 'http://www.mrsc.org' . $tds[5]->find("a", 0)->href;
        
            $cities[] = $city;
            $city = null;
        
        }

        $count++;
    }   

    
    return $cities;

}



function get_city_data($city, $url) {
    
    global $run_environment;
        
    $html = scraperWiki::scrape($url);    
    $dom = new simple_html_dom();
    $dom->load($html);

    $count = 1;
    
    $content = $dom->find("div[id=content]", 0);
    $table = $content->find("table", 0);


    foreach($table->find("tr") as $data){
        

        $tds = $data->find("td");

        if(empty($tds[1])) break;
        
        $official = official();

		$official['government_name']            =   $city;
		$official['government_level']        	=    'municipal';
		
		

		
		$official['type']                    	=    null;
		$official['title']                    	=    trim($tds[0]->plaintext);
        $official['sources']                = json_encode(array(array('description' => null, 'url' => $url, "timestamp" => gmdate("Y-m-d\TH:i:s\Z"))));
		$official['name_full']                =    trim($tds[1]->plaintext);             


        $official['address_locality']        =    $city;
        $official['address_region']            =    'WA';
        $official['address_country']        =  'USA';

		if (strripos($official['title'], 'mayor') !== false) { 
			$official['type'] = 'executive';		
		}
		if (strripos($official['title'], 'council') !== false 
			  OR strripos($official['title'], 'member') !== false 
			  OR strripos($official['title'], 'district') !== false 
			  OR strripos($official['title'], 'selectman') !== false 
			  OR strripos($official['title'], 'selectboard') !== false 
			  OR strripos($official['title'], 'alderman') !== false) {
			$official['type'] = 'legislative';		
		}



        
        if ($run_environment == 'dev') {
            $officials[] = $official;
        }
        else {
            scraperwiki::save_sqlite(array('name_full','title','address_locality'), $official, $table_name='officials');    
        }        
                
        $count++;
    }   

    if ($run_environment == 'dev') {
        return $officials;
    } else {
        return true;
    }

}


function official() {
    
    $official = array(
        'government_name'        => NULL,                         
        'government_level'        => NULL,                         
        'type'                     => NULL,                         
        'title'                 => NULL,                        
        'description'             => NULL,                  
        'name_given'             => NULL,                   
        'name_family'             => NULL,                      
        'name_full'             => NULL,                        
        'url'                     => NULL,                  
        'url_photo'             => NULL,            
        'url_schedule'             => NULL,         
        'url_contact'             => NULL,          
        'email'                 => NULL,                
        'phone'                 => NULL,                
        'address_name'             => NULL,         
        'address_1'             => NULL,            
        'address_2'             => NULL,            
        'address_locality'         => NULL,         
        'address_region'         => NULL,        
        'address_postcode'         => NULL,          
        'current_term_enddate'     => NULL,
        'last_updated'             => NULL,                     
        'social_media'             => NULL,                     
        'other_data'             => NULL,
        'conflicting_data'        => NULL,
        'sources'                 => NULL                                     

    );
    
    return $official;
    
}

function jurisdiction() {
    
    $jurisdiction = array(
        'ocd_id'                => NULL,
        'uid'                    => NULL,
        'type'                       => NULL,
        'type_name'               => NULL,      
        'level'                   => NULL,          
        'level_name'             => NULL,                
        'name'                       => NULL,          
        'url'                       => NULL,          
        'url_contact'           => NULL,          
        'email'                   => NULL,          
        'phone'                   => NULL,          
        'address_name'           => NULL,          
        'address_1'               => NULL,          
        'address_2'               => NULL,                      
        'address_locality'         => NULL,         
        'address_region'         => NULL,        
        'address_postcode'         => NULL,   
        'address_country'         => NULL,
        'service_discovery'     => NULL,
        'last_updated'           => NULL,          
        'social_media'             => NULL,
        'other_data'             => NULL,
        'conflicting_data'        => NULL,
        'sources'                => NULL
    );

    return $jurisdiction;
    
}



// template for setting variables
//
// $official['government_name']            =    ;
// $official['government_level']        =    ;
// $official['type']                    =    ;
// $official['title']                    =    ;
// $official['description']                =    ;
// $official['name_given']                =    ;
// $official['name_family']                =    ;
// $official['name_full']                =    ;
// $official['url']                        =    ;
// $official['url_photo']                =    ;
// $official['url_schedule']            =    ;
// $official['url_contact']                =    ;
// $official['email']                    =    ;
// $official['phone']                    =    ;
// $official['address_name']            =    ;
// $official['address_1']                =    ;
// $official['address_2']                =    ;
// $official['address_locality']        =    ;
// $official['address_region']            =    ;
// $official['address_postcode']        =    ;
// $official['current_term_enddate']    =    ;
// $official['last_updated']            =    ;
// $official['social_media']            =    ;
// $official['other_data']                =    ;
// $official['conflicting_data']        =    ;
// $official['sources']                    =    ;


// Jurisdiction

// $jurisdiction['ocd_id']                = ;
// $jurisdiction['uid']                    = ;
// $jurisdiction['type']                   = ;
// $jurisdiction['type_name']               = ;
// $jurisdiction['level']                   = ;
// $jurisdiction['level_name']             = ;
// $jurisdiction['name']                   = ;
// $jurisdiction['id']                     = ;
// $jurisdiction['url']                   = ;
// $jurisdiction['url_contact']           = ;
// $jurisdiction['email']                   = ;
// $jurisdiction['phone']                   = ;
// $jurisdiction['address_name']           = ;
// $jurisdiction['address_1']               = ;
// $jurisdiction['address_2']               = ;   
// $jurisdiction['address_locality']     = ;
// $jurisdiction['address_region']         = ;
// $jurisdiction['address_postcode']     = ;
// $jurisdiction['address_country']     = ;
// $jurisdiction['service_discovery']      = ;
// $jurisdiction['last_updated']           = ;
// $jurisdiction['social_media']         = ;
// $jurisdiction['other_data']             = ;
// $jurisdiction['conflicting_data']    = ;
// $jurisdiction['sources']                = ;





?>