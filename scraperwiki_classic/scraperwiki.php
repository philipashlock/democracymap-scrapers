<?php

require_once   ('scraperwiki/datastore.php') ;
require_once   ('scraperwiki/stacktrace.php' ) ;

class scraperwiki
{
   private static $attachlist = array();

   static function sw_dumpMessage($dict)
   {
      global $logfd ;
      if ($logfd) {
		 $val = json_encode($dict);
       $data = "JSONRECORD(" .  strval(strlen($val)) . "):" . $val;
       flush();
       fwrite ($logfd, $data . "\n");
       flush();
	  }
   }

   static function httpresponseheader ($headerkey, $headervalue)
   {
       scraperwiki::sw_dumpMessage
         (  array
            (  'message_type' => 'httpresponseheader',
               'headerkey'    => $headerkey,
               'headervalue'  => $headervalue
         )  )  ;
   }

   static function save($unique_keys, $data, $date = null, $latlng = null)
   {
      $ds = SW_DataStoreClass::create() ;
      $ldata = $data;   
      if (!is_null($date))
         $ldata["date"] = $date; 
      if (!is_null($latlng))
      {
         $ldata["latlng_lat"] = $latlng[0]; 
         $ldata["latlng_lng"] = $latlng[1]; 
      }
      return scraperwiki::save_sqlite($unique_keys, $ldata); 
   }

   static function sqliteexecute($sqlquery=null, $data=null, $verbose=1)
   {
      $ds = SW_DataStoreClass::create();
      $result = $ds->request(array('maincommand'=>'sqliteexecute', 'sqlquery'=>$sqlquery, 'attachlist'=>self::$attachlist, 'data'=>$data));
      if (property_exists($result, 'error'))
         throw new Exception ($result->error);
      if ($verbose == 2 )
         scraperwiki::sw_dumpMessage(array('message_type'=>'sqlitecall', 'command'=>"execute", 'val1'=>$sqlquery, 'val2'=>$data));
      return $result; 
   }


   static function unicode_truncate($val, $n)
   {
      if ($val == null)
         $val = ""; 
      return substr($val, 0, $n); //need to do more?
   }

   static function save_sqlite($unique_keys, $data, $table_name="swdata", $verbose=2)
   {
      $ds = SW_DataStoreClass::create();
      if (count($data) == 0)
          return;
      if (!array_key_exists(0, $data))
          $data = array($data); 

      # convert special types
      foreach ($data as &$row) {
          foreach ($row as $key => &$value) {
                if ($value instanceof DateTime) {
                    $new_value = clone $value;
                    $new_value->setTimezone(new DateTimeZone('UTC'));
                    $value = $new_value->format(DATE_ISO8601);
                    assert(strpos($value, "+0000") !== FALSE);
                    $value = str_replace("+0000", "", $value);
                }
          }
    }

      $result = $ds->request(array('maincommand'=>'save_sqlite', 'unique_keys'=>$unique_keys, 'data'=>$data, 'swdatatblname'=>$table_name)); 
      if (property_exists($result, 'error'))
         throw new Exception ($result->error);

      if ($verbose == 2)
      {
         $sdata = $data[0]; 
         $pdata = array(); 
         foreach ($sdata as $key=>$value)
            $pdata[scraperwiki::unicode_truncate($key, 50)] = scraperwiki::unicode_truncate($value, 50); 
         if (count($data) >= 2)
            $pdata["number_records"] = "Number Records: ".count($data); 
         scraperwiki::sw_dumpMessage(array('message_type'=>'data', 'content'=>$pdata));
      }
      return $result; 
   }

   static function select($sqlquery, $data=null)
   {
      $result = scraperwiki::sqliteexecute("select ".$sqlquery, $data); 

          // see http://rosettacode.org/wiki/Hash_from_two_arrays
      $res = array(); 
      foreach ($result->data as $i => $row)
         array_push($res, array_combine($result->keys, $row)); 
      return $res; 
   }

   static function attach($name, $asname=null)
   {
      $ds = SW_DataStoreClass::create();
      array_push(self::$attachlist, array("name"=>$name, "asname"=>$asname)); 
      $result = $ds->request(array('maincommand'=>'sqlitecommand', 'command'=>'attach', "name"=>$name, "asname"=>$asname));
   }
   static function sqlitecommit()
   {
      $ds = SW_DataStoreClass::create();
      $result = $ds->request(array('maincommand'=>'sqlitecommand', 'command'=>'commit'));
      if (property_exists($result, 'error'))
         throw new Exception ($result->error);
      return $result; 
   }

   static function show_tables($dbname=null)
   {
      $name = "sqlite_master"; 
      if ($dbname != null)
          $name = "`$dbname`.sqlite_master"; 
      $result = scraperwiki::sqliteexecute("select tbl_name, sql from $name where type='table'"); 
      $res = array(); 
      foreach ($result->data as $i=>$row)
         $res[$row[0]] = $row[1]; 
      return $res; 
   }

   static function table_info($name)
   {
      $sname = explode(".", $name); 
      if (count($sname) == 2)
          $result = scraperwiki::sqliteexecute("PRAGMA ".$sname[0].".table_info(`".$sname[1]."`)"); 
      else
          $result = scraperwiki::sqliteexecute("PRAGMA table_info(`".$name."`)"); 
      $res = array(); 
      foreach ($result->data as $i => $row)
         array_push($res, array_combine($result->keys, $row)); 
      return $res; 
   }

   static function save_var($name, $value)
   {
      $vtype = gettype($value); 
      if (($vtype != "integer") && ($vtype != "string") && ($vtype != "double") && ($vtype != "NULL"))
         print_r("*** object of type $vtype converted to string\n"); 
      $data = array("name"=>$name, "value_blob"=>strval($value), "type"=>$vtype); 
      scraperwiki::save_sqlite(array("name"), $data, "swvariables"); 
   }

   static function get_var($name, $default=null)
   {
      $ds = SW_DataStoreClass::create();
      try  { $result = scraperwiki::sqliteexecute("select value_blob, type from swvariables where name=?", array($name)); }
      catch (Exception $e)
      {
         if (substr($e->getMessage(), 0, 29) == 'sqlite3.Error: no such table:')
            return $default;
         if (substr($e->getMessage(), 0, 43) == 'DB Error: (OperationalError) no such table:')
            return $default;
         throw $e;
      }
      $data = $result->data; 
      if (count($data) == 0)
         return $default; 
      $svalue = $data[0][0]; 
      $vtype = $data[0][1];
      if ($vtype == "integer")
         return intval($svalue); 
      if ($vtype == "double")
         return floatval($svalue); 
      if ($vtype == "NULL")
         return null;
      return $svalue; 
   }


   static function gb_postcode_to_latlng ($postcode)
   {
       $url = "https://views.scraperwiki.com/run/uk_postcode_lookup/?postcode=".urlencode($postcode); 
       $sres = scraperwiki::scrape($url); 
       $jres = json_decode($sres, true); 
       if ($jres["lat"] && $jres["lng"])
            return array($jres["lat"], $jres["lng"]); 
       return null; 
   }

   static function scrape($url)
   {
      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
      // disable SSL checking to match behaviour in Python/Ruby.
      // ideally would be fixed by configuring curl to use a proper 
      // reverse SSL proxy, and making our http proxy support that.
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      $res = curl_exec($curl);
      curl_close($curl);
      return $res;
   }

   // These are DEPRECATED and just here for compatibility
   // the meta functions weren't being used to any extent in PHP anyway
   static function get_metadata($metadata_name, $default = null)
   {
      return scraperwiki::get_var($metadata_name, $default); 
   }

   static function save_metadata($metadata_name, $value)
   {
      return scraperwiki::save_var($metadata_name, $value); 
   }


    static function getInfo($name) {
        $url = "http://api.scraperwiki.com/api/1.0/scraper/getinfo?name=".urlencode($name); 
        $handle = fopen($url, "r"); 
        $ljson = stream_get_contents($handle); 
        fclose($handle);
        return json_decode($ljson); 
    }

    static function getKeys($name) {
        throw new Exception("getKeys has been deprecated"); 
    }
    static function getData($name, $limit= -1, $offset= 0) {
        throw new Exception("getData has been deprecated"); 
    }

    static function getDataByDate($name, $start_date, $end_date, $limit= -1, $offset= 0) {
        throw new Exception("getDataByDate has been deprecated"); 
    }
    
    static function getDataByLocation($name, $lat, $lng, $limit= -1, $offset= 0) { 
        throw new Exception("getDataByLocation has been deprecated"); 
    }
        
    static function search($name, $filterdict, $limit= -1, $offset= 0) {
        throw new Exception("apiwrapper.search has been deprecated"); 
    }
}

?>
