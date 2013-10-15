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

$url = "http://www.floridaleagueofcities.com/directory.aspx";

$city_list = get_city_list($url);

// $alldata = $city_list;

$count = 1;
foreach ($city_list as $link) {
    
    $url = $link['source'];
    $city = $link['name'];
    
    if ($run_environment == 'prod') {
        get_city_data($city, $url);
    }
    else {
        $alldata[] = get_city_data($city, $url);
    }

    $count++;
    if ($run_environment == 'dev' && $count > $max_records) break;

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



function get_city_list($url) {
        
    global $run_environment;
    global $max_records;

    $html = scraperWiki::scrape($url);    
    $dom = new simple_html_dom();
    $dom->load($html);
    
    // table/tr/td/div/table/tr/td[2]/table/tr/td/table/tr[5]
    $content = $dom->find("div[id=ctl00_cphmain_pnlIndex]", 0)->find("table", 1);

    $count = 0;
    foreach($content->find("a") as $link){
        
        if ($link->href) {

            $city['source'] = 'http://www.floridaleagueofcities.com' . $link->href;
            $city['name'] = $link->plaintext;

            $cities[] = $city;
        
             $count++;
        }

    }  

    // Clear memory
    $dom->__destruct();
    $content->__destruct();

     return $cities;

}



function get_city_data($name, $url) {
    
    global $run_environment;
    
    $html = scraperWiki::scrape($url);        
    $dom = new simple_html_dom();
    $dom->load($html);

    $count = 1;

    // for debugging
    if(!$dom->find("table", 0)) {
        echo $url; exit;
    }
    
    
    
    
    $content = $dom->find("div[id=ctl00_cphmain_pnlCityInfo]", 0)->find("table", 0);

    $jurisdiction = jurisdiction();
	
    $jurisdiction['type']                   = 'government';

    $jurisdiction['level']                   = 'municipal';

    $jurisdiction['name']                   = $name;
    $jurisdiction['url']                   = ($content->find("tr", 7)->find("td", 1)->find("a", 0)) ? $content->find("tr", 7)->find("td", 1)->find("a", 0)->href : null;;
    
    $jurisdiction['email']                   = ($content->find("tr", 8)->find("td", 1)->find("a", 0)) ? $content->find("tr", 8)->find("td", 1)->find("a", 0)->href : null;
    $jurisdiction['phone']                   = trim($content->find("tr", 4)->find("td", 1)->plaintext);                

     $address                 = trim($content->find("tr", 1)->find("td", 1)->plaintext);    
     $address_street         = substr($address, 0, strpos($address, $name));
     $address_street         = trim($address_street);    
     $address_postcode         =  substr($address, strpos($address, ', FL')+4);
     $address_postcode         = trim($address_postcode);
     
     // $jurisdiction['address_name']           = ;
     $jurisdiction['address_1']               = $address_street;
     // $jurisdiction['address_2']               = ;   
     $jurisdiction['address_locality']     = $name;
      $jurisdiction['address_region']         = 'FL';
      $jurisdiction['address_postcode']     = $address_postcode;
      $jurisdiction['address_country']     = 'USA';
     
     
     $other = array();
     
     $other['address_shipping']        = trim($content->find("tr", 2)->find("td", 1)->plaintext);                
     $other['fax']                    = trim($content->find("tr", 5)->find("td", 1)->plaintext);                        
     $other['year_incorporated']      = trim($content->find("tr", 10)->find("td", 1)->plaintext);                        
     $other['population']             = trim($content->find("tr", 11)->find("td", 1)->plaintext);                        
     $other['county']                 = trim($content->find("tr", 12)->find("td", 1)->plaintext);        
     //$other['city_history']           = ($content->find("tr", 14)->find("td", 1)) ? trim($content->find("tr", 14)->find("td", 1)->plaintext) : null;            
     
     $jurisdiction['other_data']     	= json_encode($other);
     $jurisdiction['sources']                = json_encode(array(array('description' => null, 'url' => $url, "timestamp" => gmdate("Y-m-d\TH:i:s\Z"))));
     


    // Get reps
    $rep_details = get_rep_details($dom, $url, $jurisdiction['name']);
   
   
    // Clear memory
    $dom->__destruct();
    $content->__destruct();
   
    if ($run_environment == 'dev') {
        $jurisdiction['officials'] = $rep_details;
        return $jurisdiction;
    }
    else {
        scraperwiki::save_sqlite(array('name'), $jurisdiction, $table_name = 'jurisdictions');    
        return true;

    }


}



function get_rep_details($dom, $source, $city) {
        
     global $run_environment;

    //$html = scraperWiki::scrape($url);    
    //$dom = new simple_html_dom();
    //$dom->load($html);

    $content = $dom->find("div[id=ctl00_cphmain_pnlStaff]", 0)->find("table", 0);
    
    foreach($content->find("tr") as $row){
    

        $official = official();
        

        $official['title']                    =    trim($row->find("td", 0)->plaintext);;
        $official['name_full']                =    trim($row->find("td", 1)->plaintext);;

        $official['government_name']        =    $city;

        $official['government_level']        = 'municipal';

        $official['address_locality']        =    $city;
        $official['address_region']            =    'FL';
        $official['address_country']        =  'USA';           
    
        $official['sources']                = json_encode(array(array('description' => null, 'url' => $source, "timestamp" => gmdate("Y-m-d\TH:i:s\Z"))));

           if ($run_environment == 'dev') {
                $officials[] = $official;
           }
           else {
                scraperwiki::save_sqlite(array('name_full','title','address_locality'), $official, $table_name='officials');    
           }
    
    
    }
    
    if ($run_environment == 'dev') {            
        return $officials;
    }
    else {
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

