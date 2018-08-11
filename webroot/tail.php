<?php
/**
 * User: simon
 * Date: 03.08.2018
 */

require dirname(__DIR__) . '/lib/ShortPixelWeb/PhpTail.php';
/**
 * Initilize a new instance of PHPTail
 * @var PHPTail
 */
$tail = new ShortPixelWeb\PhpTail(array("ShortPixel CLI" => urldecode($_GET['file'])));

/**
 * We're getting an AJAX call
 */
if(isset($_GET['ajax']))  {
    echo $tail->getNewLines("ShortPixel CLI", $_GET['lastsize'], $_GET['grep'], $_GET['invert']);
    die();
}
/**
 * Regular GET/POST call, print out the GUI
 */
$tail->generateGUI();