<?php
/**
 * Place this file anywhere you want, it can be triggered by Bash and 
 * will update your DB with your files
 */


//This nifty code converts PHP Errors from old functions into Exceptions for 
//better Handling
set_error_handler(function($errno, $errstr, $errfile, $errline){ 
    if (!(error_reporting() & $errno)) {
        return;
    }
    
    throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);

});

require_once __DIR__.'/../vendor/autoload.php';

//Add further autoloaders & config Files (example below)
require_once __DIR__.'/../configs/constants.php';

$mysql = new \Leedch\Mysql\Tools\Update();
echo $mysql->updateDb();


//It is best practice to reset error handling when finished
restore_error_handler();