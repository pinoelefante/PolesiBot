<?php
    set_error_handler("app_error_logger");

    function LogArray($array, $file = "log_array.log")
    {
        LogMessage(GetArrayToString($array), $file);
    }
    function LogMessage($messaggio, $file = "log_error.log", $backtrace = false)
    {
        $timestamp = date("d/m/Y - H:i:s");
        $line = "$timestamp: $messaggio\n".($backtrace ? GetArrayToString(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))."\n" : "");
        file_put_contents ("$file", $line, FILE_APPEND | LOCK_EX);
    }
    function GetArrayToString($array)
    {
        $content = "";
        if(!empty($array))
        {
            foreach($array as $key=>$value)
                $content = $content."$key".(is_array($value) ? ":\n{ ".GetArrayToString($value)."}" : " = ".$value)."\n";
        }
        return $content;
    }
    function app_error_logger($errno, $errstr, $errfile, $errline)
    {
        $message = "[$errno] $errstr : line $errline in file $errfile";
        LogMessage($message, "php_warnings.log", true);
        return false;
    }
?>