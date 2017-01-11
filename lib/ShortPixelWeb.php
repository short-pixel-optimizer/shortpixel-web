<?php
/**
 * User: simon
 * Date: 10.12.2016
 * Time: 00:33
 */
namespace ShortPixelWeb;

use ShortPixelWeb\XTemplate;

class ShortPixelWeb
{
    private $settings;
    private $xtpl;
    private $INI_PATH;
    const FOLDER_INI_NAME = '.sp-options';

    function __construct() {
        $this->xtpl = new XTemplate('main.html', __DIR__ . '/ShortPixelWeb/tpl');
        $this->INI_PATH = dirname(__DIR__). '/shortpixel.ini';
    }

    function bootstrap() {
        date_default_timezone_set("UTC");
        $settings = array();
        $apiKey = false;
        if(file_exists($this->INI_PATH)) {
            $this->settings = parse_ini_file($this->INI_PATH);
        }
        //die(phpinfo());

        $this->handleRequest();
    }

    function handleRequest() {
        if(isset($_POST['API_KEY'])) {
            $this->renderStartPage($this->persistApiKeyAndSettings($_POST));
        }
        elseif(isset($_POST['action'])) {
            switch($_POST['action']) {
                case 'shortpixel_browse_content':
                    $this->renderBrowseFolderFragment(isset($_POST['dir']) ? $_POST['dir'] : null,
                        isset($_POST['multiSelect']) && $_POST['multiSelect'] == 'true',
                        isset($_POST['onlyFolders']) && $_POST['onlyFolders'] == 'true',
                        isset($_POST['onlyFiles']) && $_POST['onlyFiles'] == 'true' );
                    break;
                case 'shortpixel_folder_options':
                    $this->renderFolderOptionsData($_POST['folder']);
                    break;
                case 'shortpixel_optimize' :
                    $this->optimizeAction($_POST['folder']);
            }
        }
        elseif(isset($_GET['folder'])) {
            $this->renderOptimizeNow($_GET['folder']);
        }
        else {
            $this->renderStartPage(array());
        }
    }

    function persistApiKeyAndSettings($data) {
        if(isset($data['API_KEY']) && strlen($data['API_KEY']) == 20) {
            if(file_exists($this->INI_PATH)) {
                unlink($this->INI_PATH);
            }
            $strSettings = "[SHORTPIXEL]\nAPI_KEY=" . $data['API_KEY'] . "\n";
            $settings = $this->post2options($data);
            foreach($settings as $key => $val) {
                $strSettings .= $key . '=' . $val . "\n";
            }
            $settings['API_KEY'] = $data['API_KEY'];

            if(!@file_put_contents($this->INI_PATH, $strSettings)) {
                return array("error" => "Could not write properties file " . $this->INI_PATH . ". Please check rights.");
            }
            $this->settings = $settings;
            return array("success" => "API Key set: " . $data['API_KEY']);
        } else {
            return array("error" => "API Key should be 20 characters long.");
        }
    }

    function persistFolderSettings($data, $path) {
        $strSettings = "[SHORTPIXEL]\n";
        foreach($this->post2options($data) as $key => $val) {
            if(!in_array($key, array("API_KEY", "folder", "")))
                $strSettings .= $key . '=' . $val . "\n";
        }
        return @file_put_contents($path . '/' . self::FOLDER_INI_NAME, $strSettings);
    }

    function post2options($post) {
        $data = array();
        if(isset($post['type'])) $data['lossy'] = $post['type'] == 'lossy' ? 1 : 0;
        $data['keep_exif'] = isset($post['removeExif']) ? 0 : 1;
        $data['cmyk2rgb'] = isset($post['cmyk2rgb']) ? 1 : 0;
        $data['resize'] = isset($post['resize']) ? ($post['resize_type'] == 'outer' ? 1 : 3) : 0;
        if($data['resize'] && isset($post['width'])) $data['resize_width'] = $post['width'];
        if($data['resize'] && isset($post['height'])) $data['resize_height'] = $post['height'];
        $data['convertto'] = isset($post['webp']) ? '+webp' : '';
        return $data;
    }

    private function folderFullPath($folder) {
        $root = dirname(dirname(__DIR__));
        return rawurldecode($root.$folder );
    }

    function renderFolderOptionsData($folder) {
        $optionsPath = $this->normalizePath($this->folderFullPath($folder) . '/' . self::FOLDER_INI_NAME);
        if(file_exists($optionsPath)) {
            die(json_encode(parse_ini_file($optionsPath)));
        }
        die(json_encode(array()));

    }

    function renderBrowseFolderFragment($folder, $multiSelect, $onlyFolders, $onlyFiles) {
        $postDir = $this->folderFullPath($folder);
        $checkbox = $multiSelect ? "<input type='checkbox' />" : null;

        if( file_exists($postDir) ) {

            $files = scandir($postDir);

            natcasesort($files);

            if( count($files) > 2 ) { // The 2 accounts for . and ..
                echo "<ul class='jqueryFileTree'>";
                foreach( $files as $file ) {

                    if($file == 'ShortpixelBackups') continue;

                    $htmlRel	= $this->normalizePath(htmlentities($folder . '/' . $file));
                    $htmlName	= htmlentities($file);
                    $ext		= preg_replace('/^.*\./', '', $file);

                    if( file_exists($postDir . $file) && $file != '.' && $file != '..' ) {
                        if( is_dir($postDir . $file) && (!$onlyFiles || $onlyFolders) )
                            echo "<li class='directory collapsed'>{$checkbox}<a rel='" .$htmlRel. "/'>" . $htmlName . "</a></li>";
                        else if (!$onlyFolders || $onlyFiles)
                            echo "<li class='file ext_{$ext}'>{$checkbox}<a rel='" . $htmlRel . "/'>" . $htmlName . "</a></li>";
                    }
                }

                echo "</ul>";
            }
        }
    }

    function renderSettings($type) {
        $this->xtpl->assign('options_type', $type);
        $this->setupWrapper(false);
        $this->xtpl->assign('lossy_checked', \ShortPixel\ShortPixel::opt('lossy') == 1 ? 'checked' : '');
        $this->xtpl->assign('lossless_checked', \ShortPixel\ShortPixel::opt('lossy') == 1 ? '' : 'checked');
        $this->xtpl->assign('cmyk2rgb_checked', \ShortPixel\ShortPixel::opt('cmyk2rgb') == 1 ? 'checked' : '');
        $this->xtpl->assign('remove_exif_checked', \ShortPixel\ShortPixel::opt('keep_exif') == 1 ? '' : 'checked');
        $this->xtpl->assign('resize_checked', \ShortPixel\ShortPixel::opt('resize') ? 'checked' : '');
        $this->xtpl->assign('width', \ShortPixel\ShortPixel::opt('resize_width'));
        $this->xtpl->assign('height', \ShortPixel\ShortPixel::opt('resize_height'));
        $this->xtpl->assign('webp_checked', \ShortPixel\ShortPixel::opt('convertto') == '+webp' ? 'checked' : '');
        $this->xtpl->assign('resize_outer_checked', \ShortPixel\ShortPixel::opt('resize') & 2 ? '' : 'checked');
        $this->xtpl->assign('resize_inner_checked', \ShortPixel\ShortPixel::opt('resize') & 2 ? 'checked' : '');
    }

    function renderStartPage($messages) {
        $apiKey = false;
        if(isset($this->settings["API_KEY"])) {
            $apiKey = $this->settings["API_KEY"];
        }
        if($apiKey) {
            $this->renderSettings('Folder');
            $this->displayMessages('main.form', $messages);
            $this->xtpl->parse('main.form');
        } else {
            $this->renderSettings('Default');
            $this->displayMessages('main.key', $messages);
            $this->xtpl->parse('main.key');
        }
        $this->xtpl->parse('main');
        $this->xtpl->out('main');
    }

    function renderOptimizeNow($folder) {
        if(isset($_GET['type'])) {
            //the action is from the Optimize now button and it has the settings, persist them in the .sp-options file
            $this->persistFolderSettings($_GET, $this->normalizePath(dirname(dirname(__DIR__)) . $_GET['folder']));
        }
        $this->setupWrapper($folder);
        $status = \ShortPixel\folderInfo(dirname(dirname(__DIR__)) . $folder);
        $this->xtpl->assign('folder', $folder);

        if($status->status !== 'error' && $status->total == $status->succeeded + $status->failed) {
            //success
            $this->xtpl->assign('total_files', $status->total);
            $this->xtpl->assign('succeeded_files', $status->succeeded);
            $this->xtpl->assign('failed_files', $status->failed);
            $this->xtpl->parse('main.success');
        } else {
            if($status->status == 'error') {
                $this->xtpl->assign('error', $status->message . " (code: " . $status->code . ")");
                $this->xtpl->parse('main.progress.error');
            } else {
                $this->xtpl->assign('total_files', $status->total);
                $this->xtpl->assign('done_files', $status->succeeded + $status->failed);
                $percent = 100.0 * ($status->succeeded + $status->failed) / $status->total;
                $this->xtpl->assign('percent', round($percent));
                $this->xtpl->assign($percent > 30 ? 'percent_before' : 'percent_after', number_format($percent, 1) . "%");
                $this->xtpl->parse('main.optimize_js');
                $this->xtpl->parse('main.progress.bar');
            }
            $this->xtpl->parse('main.progress');
        }
        $this->xtpl->parse('main');
        $this->xtpl->out('main');
    }

    function optimizeAction($folder) {
        $timeLimit = ini_get('max_execution_time');
        if($timeLimit) {
            $timeLimit -= 5;
        } else {
            $timeLimit = 60;
        }

        $folderPath = dirname(dirname(__DIR__)) . $folder;
        $this->setupWrapper($folderPath);
        try {
            die(json_encode(\ShortPixel\fromFolder($folderPath)->wait($timeLimit)->toFiles($folderPath)));
        }
        catch(\Exception $e) {
            die(json_encode(array("status" => array("code" => $e->getCode(), "message" => $e->getMessage()))));
        }

    }

    function displayMessages($xtplPath, $messages) {
        foreach ($messages as $key => $val ) {
            $this->xtpl->assign($key, $val);
            $this->xtpl->parse($xtplPath . "." . $key);
        }
    }

    function setupWrapper($path) {
        //TODO schimba asta cu composer
        require_once("../vendor/autoload.php");
        \ShortPixel\setKey($this->settings["API_KEY"]);
        $opts = $this->readOptions($path);
        $opts["persist_type"] = "text";
        \ShortPixel\ShortPixel::setOptions($opts);
        //die(var_dump(\ShortPixel\ShortPixel::options()));
    }

    function readOptions($path) {
        $options = $this->settings;
        if($path && file_exists($path . '/' . self::FOLDER_INI_NAME)) {
            $options = array_merge($options, parse_ini_file($path . '/' . self::FOLDER_INI_NAME));
        }
        unset($options['API_KEY']);
        return $options;

    }

    function normalizePath($path) {
        $patterns = array('~/{2,}~', '~/(\./)+~', '~([^/\.]+/(?R)*\.{2,}/)~', '~\.\./~');
        $replacements = array('/', '/', '', '');
        return preg_replace($patterns, $replacements, $path);
    }
}
