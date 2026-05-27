<?php
session_start();

define('APP_NAME', 'TimeTracking Autostart');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host= $_SERVER['HTTP_HOST'];
$path= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $protocol . '://' . $host . $path);
