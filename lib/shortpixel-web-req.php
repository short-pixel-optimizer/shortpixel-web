<?php

if(PHP_MAJOR_VERSION < 5 || (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 4)) {
    die("ShortPixel Web Tool needs at least PHP 5.4. Please upgrade and retry.");
}

require_once("ShortPixelWeb/XTemplate.php");
require_once("ShortPixelWeb.php");
