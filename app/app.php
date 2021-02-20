<?php


namespace dokuwiki\plugin\websockets\app;

if(!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../../') . '/');
define('NOSESSION', 1);
require_once(DOKU_INC . 'inc/init.php');

error_reporting(E_ALL);

$server = new Server('127.0.0.1', 9000);
$server->run();