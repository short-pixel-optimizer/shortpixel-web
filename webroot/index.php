<?php
/**
 * User: simon
 * Date: 09.12.2016
 * Time: 23:40
 */

//die(phpinfo());
if(!defined('CURLOPT_RETURNTRANSFER')) {
    die('The cURL PHP extension is needed by ShortPixel but it\'s not installed. Please install it and then reload this page.');
}
require_once(dirname(__DIR__) . '/lib/shortpixel-web-req.php');

$web = new \ShortPixelWeb\ShortPixelWeb();
$web->bootstrap();
