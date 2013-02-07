<?php

function traceToSWStackDump($trace, $script, $scriptlines, $skipa = 1, $skipb = 0)
{
    $stackdump = array(); 
    for ($i = count($trace) - 1 - $skipa; $i >= $skipb; $i--)
    {
        $stackPoint = $trace[$i]; 
        
        $linenumber = $stackPoint["line"]; 
        $stackentry = array("linenumber" => $linenumber, "duplicates" => 1); 
        $stackentry["file"] = (realpath($stackPoint["file"]) == realpath($script) ? "<string>" : $stackPoint["file"]); 

		$stackentry["linetext"] = "";
        if (($linenumber >= 0) && ($linenumber < count($scriptlines))) {
            $stackentry["linetext"] = $scriptlines[$linenumber - 1];
        } else {
            # XXX bit of a hack to show the line number in third party libraries
            $stackentry["file"] .= ":" . $linenumber;
        }



        if (array_key_exists("args", $stackPoint) and count($stackPoint["args"]) != 0)
        {
            $args = array(); 
            foreach ($stackPoint["args"] as $arg => $val)
                $args[] = $arg."=>".$val; 
            $stackentry["furtherlinetext"] = " param values: (".implode(", ", $args).")"; 
        }
        $stackdump[] = $stackentry; 
    }
    return $stackdump;
}

function exceptionHandler($exception, $script) 
{
    $scriptlines = explode("\n", file_get_contents($script)); 
    $trace = $exception->getTrace(); 
    $stackdump = traceToSWStackDump($trace, $script, $scriptlines, 1, 0);

    $linenumber = $exception->getLine(); 
    $finalentry = array("linenumber" => $linenumber, "duplicates" => 1); 
    $finalentry["file"] = (realpath($exception->getFile()) == realpath($script) ? "<string>" : $exception->getFile()); 
    if (($linenumber >= 0) && ($linenumber < count($scriptlines))) {
        $finalentry["linetext"] = $scriptlines[$linenumber - 1]; 
    } else {
        # XXX bit of a hack to show the line number in third party libraries
        $finalentry["file"] .= ":" . $linenumber;
    }
    $finalentry["furtherlinetext"] = $exception->getMessage(); 
    $stackdump[] = $finalentry; 
    
    return array('message_type' => 'exception', 'exceptiondescription' => $exception->getMessage(), "stackdump" => $stackdump); 
}

$php_error_codes = Array(
    1 => "E_ERROR",
    2 => "E_WARNING",
    4 => "E_PARSE",
    8 => "E_NOTICE",
    16 => "E_CORE_ERROR",
    32 => "E_CORE_WARNING",
    64 => "E_COMPILE_ERROR",
    128 => "E_COMPILE_WARNING",
    256 => "E_USER_ERROR",
    512 => "E_USER_WARNING",
    1024 => "E_USER_NOTICE",
    2048 => "E_STRICT",
    4096 => "E_RECOVERABLE_ERROR",
    8192 => "E_DEPRECATED",
    16384 => "E_USER_DEPRECATED",
);


function errorParserNoStack($errno, $errstr, $errfile, $errline)
{
    $scriptlines = explode("\n", file_get_contents($errfile)); 

    $linenumber = $errline; 
    $errorentry = array("linenumber" => $linenumber, "duplicates" => 1); 
    $errorentry["file"] = "<string>";
    if (($linenumber >= 0) && ($linenumber < count($scriptlines)))
        $errorentry["linetext"] = $scriptlines[$linenumber - 1]; 
    global $php_error_codes;
    $errcode = $php_error_codes[$errno]; 

    $stackdump = array();
    $stackdump[] = $errorentry; 
    return array('message_type' => 'exception', 'exceptiondescription' => $errcode."  ".$errstr, "stackdump" => $stackdump); 
}

function errorParserStack($errno, $errstr, $script)
{
    $scriptlines = explode("\n", file_get_contents($script)); 

    $trace = debug_backtrace();
    $stackdump = traceToSWStackDump($trace, $script, $scriptlines, 1, 2);

    global $php_error_codes;
    $errcode = $php_error_codes[$errno]; 
    return array('message_type' => 'exception', 'exceptiondescription' => $errcode."  ".$errstr, "stackdump" => $stackdump); 
}


?>
