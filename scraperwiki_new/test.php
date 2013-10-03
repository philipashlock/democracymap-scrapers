<?php


//require 'scraperwiki.php';


require 'rb.php';if(empty($_SERVER["SERVER_ADDR"])OR stripos($_SERVER["SERVER_ADDR"],'127.0.0.1')===false){new scraperwiki();}class scraperwiki{protected $db;public function __construct($db='sqlite:scraperwiki.sqlite'){scraperwiki::_connect($db);}static function _connect($db=null){if(empty($db)){R::setup();}else{R::setup($db);}}static function save($unique_keys=array(),$data,$table_name="swdata",$date=null){$ldata=$data;if(!is_null($date))$ldata["date"]=$date;return scraperwiki::save_sqlite($unique_keys,$ldata,$table);}static function save_sqlite($unique_keys=array(),$data,$table_name='swdata'){if(count($data)==0)return;$table=R::dispense($table_name);foreach($data as&$value){if($value instanceof DateTime){$new_value=clone $value;$new_value->setTimezone(new DateTimeZone('UTC'));$value=$new_value->format(DATE_ISO8601);assert(strpos($value,"+0000")!==FALSE);$value=str_replace("+0000","",$value);}}unset($value);foreach($data as $key=>$value){$table->$key=$value;}if(!R::$redbean->tableExists($table_name)){if(!empty($unique_keys)){$table->setMeta("buildcommand.unique",array($unique_keys));}R::store($table);return true;}if(!empty($unique_keys)){$parameters['table_name']=$table_name;$parameters['keys']=join(", ",array_keys($data));$parameters['values']=join(', ',array_fill(0,count($data),'?'));$sql=vsprintf('INSERT or REPLACE INTO %s (%s) VALUES (%s)',$parameters);R::exec($sql,array_values($data));return true;}else{R::store($table);return true;}}static function save_var($name,$value){$vtype=gettype($value);if(($vtype!="integer")&&($vtype!="string")&&($vtype!="double")&&($vtype!="NULL"))print_r("*** object of type $vtype converted to string\n");$data=array("name"=>$name,"value_blob"=>strval($value),"type"=>$vtype);scraperwiki::save_sqlite(array("name"),$data,"swvariables");}static function get_var($name,$default=null){$data=R::findOne('swvariables',' name = ? ',array($name));if(!$data)return $default;$svalue=$data->value_blob;$vtype=$data->type;if($vtype=="integer")return intval($svalue);if($vtype=="double")return floatval($svalue);if($vtype=="NULL")return null;return $svalue;}static function scrape($url){$curl=curl_init($url);curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);curl_setopt($curl,CURLOPT_MAXREDIRS,10);curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);$res=curl_exec($curl);curl_close($curl);return $res;}}
new scraperwiki('');


$name = 'Car';
$value = 'Humvee';

scraperwiki::save_var($name, $value);

echo "Saving '$name' as '$value'";

echo '<br>';

echo "get $name: " . scraperwiki::get_var($name);


?>