<?php

class SW_DataStoreClass
{
   private static $m_ds       ;
   protected      $m_socket   ;
   protected      $m_host     ;
   protected      $m_port     ;
   protected $m_scrapername;
   protected $m_runid;
   protected $m_attachables; 
   protected $m_verification_key;

   function __construct ($host, $port, $scrapername, $runid, $attachables, $verification_key)
   {
      $this->m_socket    = null     ;
      $this->m_host      = $host    ;
      $this->m_port      = $port    ;
      $this->m_scrapername = $scrapername;
      $this->m_runid = $runid;
      $this->m_attachables = $attachables; 
	  $this->m_verification_key = $verification_key;
   }


   function connect ()
   {
      /*
      Connect to the data proxy. The data proxy will need to make an Ident call
      back to get the scraperID. Since the data proxy may be on another machine
      and the peer address it sees will have been subject to NAT or masquerading,
      send the UML name and the socket port number in the request.
      */
      if (is_null($this->m_socket))
      {
            $this->m_socket    = socket_create (AF_INET, SOCK_STREAM, SOL_TCP) ;
            if (socket_connect     ($this->m_socket, $this->m_host, $this->m_port) === FALSE)
                throw new Exception("Could not socket_connect to datastore");
            socket_getsockname ($this->m_socket, $addr, $port) ;
            //print "socket_getsockname " . $addr . ":" . $port . "\n";
            $getmsg = sprintf  ("GET /?uml=%s&port=%s&vscrapername=%s&vrunid=%s&verify=%s HTTP/1.1\n\n", 'lxc', $port, urlencode($this->m_scrapername), urlencode($this->m_runid), urlencode($this->m_verification_key)) ;
            socket_write        ($this->m_socket, $getmsg);

            socket_recv        ($this->m_socket, $buffer, 0xffff, 0) ;
            $result = json_decode($buffer, true);
            if ($result["status"] != "good")
               throw new Exception ($result["status"]);
      }
   }


   function request($req)
   {
      $this->connect () ;
      $reqmsg  = json_encode ($req) . "\n" ;
      socket_write ($this->m_socket, $reqmsg);

      $text = '' ;
      while (true)
      {
            socket_recv ($this->m_socket, $buffer, 0xffff, 0) ;
            if (strlen($buffer) == 0)
               break ;
            $text .= $buffer ;
            if ($text[strlen($text)-1] == "\n")
               break ;
      }

      return json_decode($text) ;
   }

   function save ($unique_keys, $scraper_data, $date = null, $latlng = null)
   {
       throw new Exception ("This function is no more and shouldn't be accessible") ;
   }


   function close ()
   {
      socket_write  ($this->m_socket, ".\n");
      socket_close ($this->m_socket) ;
      $this->m_socket = undef ;
   }

    // function used both to iniatialize the settings and get the object
   static function create ($host = null, $port = null, $scrapername = null, $runid = null, $attachables = null, $verification_key=null)
   {
      if (is_null(self::$m_ds))
         self::$m_ds = new SW_DataStoreClass ($host, $port, $scrapername, $runid, $attachables,$verification_key );
      return self::$m_ds;
   }
}

?>
