<?php
/**
 * converts a web request to a disconnected cmdShortPixelOptimize.php call
 * User: simon
 * Date: 02.08.2018
 *
 * Example:
 * shortpixel-web/webroot/web2cmd.php?apiKey=BCOGLCznxUECmVeOH2zR&folder=/home/simon/ShortPixel/DEV/WRAPERS/TEST/TEST1&verbose=&backupBase=/home/simon/ShortPixel/DEV/WRAPERS/TEST/TEST1/ShortPixelBackups&cacheTime=3600
 */

if(!isset($_REQUEST['apiKey'])) {
    die("\n\n--key parameter missing\n\n");
}
if(!isset($_REQUEST['folder']) || !file_exists($_REQUEST['folder'])) {
    die("\n\n--folder parameter missing or invalid\n\n");
}

$args = "";
foreach($_REQUEST as $param => $val) {
    $args .= " --" . $param . (strlen($val) ? "=" . escapeshellcmd($val) : "");
}
putenv("SHELL=/bin/bash");
unlink($_REQUEST['folder'] . '/shortpixel_log');
$cmd = 'echo "/usr/bin/php ' . dirname(__DIR__) . '/vendor/shortpixel/shortpixel-php/lib/cmdShortpixelOptimize.php ' . $args . ' 2>&1 >>' . $_REQUEST['folder'] . '/shortpixel_log" | at now 2>&1';
file_put_contents($_REQUEST['folder'] . '/shortpixel_log', $cmd, FILE_APPEND);
exec($cmd, $ret, $stat);

if($stat) {
    die(print_r($ret));
}
//echo "executed $cmd<br><br>";

$uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);

$location = 'Location: '. (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($uri_parts[0])
    . "/tail.php?file=" . urlencode($_REQUEST['folder'] . '/shortpixel_log') . "#" . $_REQUEST['folder'] . '/shortpixel_log';

//echo "redirecting to " . $location;
header($location);