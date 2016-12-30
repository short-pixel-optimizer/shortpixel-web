<?php
/**
 * User: simon
 * Date: 09.12.2016
 * Time: 23:40
 */

//die(phpinfo());
require_once(dirname(__DIR__) . '/lib/shortpixel-web-req.php');

$web = new \ShortPixelWeb\ShortPixelWeb();
$web->bootstrap();
