<?php
/**
 * User: simon
 * Date: 10.12.2016
 * Time: 00:33
 */
namespace ShortPixelWeb;

const WEB_VERSION = "0.9.5a";


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
                        isset($_POST['onlyFiles']) && $_POST['onlyFiles'] == 'true',
                        isset($_POST['extended']) && $_POST['extended'] == 'true');
                    break;
                case 'shortpixel_folder_options':
                    $this->renderFolderOptionsData($_POST['folder']);
                    break;
                case 'shortpixel_optimize' :
                    $this->optimizeAction($_POST['folder'], isset($_POST['slice']) ? $_POST['slice'] : 0);
            }
        }
        elseif(isset($_GET['folder'])) {
            $this->renderOptimizeNow($_GET);
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
        if(isset($post['backup_path'])) {
            $data['backup_path'] = $post['backup_path'];
        }
        if(isset($post['exclude'])) {
            $data['exclude'] = $post['exclude'];
        }
        if(isset($post['base_url']) && strlen($post['base_url'])) {
            $data['base_url'] = rtrim($post['base_url'], '/');
        } elseif (isset($post['change_base_url']) && strlen($post['change_base_url'])) {
            $data['base_url'] = rtrim($post['change_base_url'], '/');
        }
        return $data;
    }

    function pathToRelative($path, $reference) {
        $pa = explode('/', trim($path, '/'));
        $ra = explode('/', trim($reference, '/'));
        $res = array();
        for($i = 0, $same = true; $i < max(count($pa), count($ra)); $i++) {
            if($same && isset($pa[$i]) && isset($ra[$i]) && $pa[$i] == $ra[$i]) continue;
            $same = false;
            if(isset($ra[$i])) array_unshift($res, '..');
            if(isset($pa[$i])) $res[] = $pa[$i];
        }
        return implode('/', $res);
    }

    private function folderFullPath($folder) {
        $root = dirname(dirname(__DIR__));
        return rawurldecode($root.$folder );
    }

    function renderFolderOptionsData($folder) {
        $folderPath = $this->normalizePath($this->folderFullPath($folder));
        $optionsPath = $folderPath . '/' . self::FOLDER_INI_NAME;
        $options = array();
        if(file_exists($optionsPath)) {
            $options = parse_ini_file($optionsPath);
        }
        if(!isset($options['base_url'])) { //try to detect the base URL
            $myUri = explode('/', $_SERVER["REQUEST_URI"]);
            if(count($myUri) && ($myUri[count($myUri) -1] == 'index.php' || $myUri[count($myUri) -1] == '')) unset($myUri[count($myUri) -1]);
            if(count($myUri) >= 3) { //we have the base folder of shortpixel-web inside the web root so we can determine a corresponding URL for the folder
                unset($myUri[count($myUri) - 1]); //off with webroot
                unset($myUri[count($myUri) - 1]); //off with shortpixel-web
                $options['base_url_detected'] = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . implode('/', $myUri) . $folder;
            }
        }
        die(json_encode($options));
    }

    /**
     *  try to find the options for the folder by searching the parent folders too - used for finding the backup
     * @param $postDir
     */
    function searchBackupFolder($postDir) {
        $optionsDir = $postDir; $optionsParents = $siblings = '';
        while(file_exists($optionsDir . '/' . \ShortPixel\opt("persist_name"))){
            $folderIni = $optionsDir . '/' . self::FOLDER_INI_NAME;
            if(file_exists($folderIni)) {
                $folderOptions = parse_ini_file($folderIni);
                if(isset($folderOptions['backup_path'])) {
                    $relative = $optionsParents . $folderOptions['backup_path'] . $siblings;
                    $absolute = $this->normalizePath($postDir .  $relative);
                    $url = false;
                    if(isset($folderOptions['base_url']) && strlen($folderOptions['base_url'])) {
                        $baseUrl = explode("://", $folderOptions['base_url']);
                        $url = str_replace($this->normalizePath($optionsDir . '/' . $folderOptions['backup_path']),
                                           $baseUrl[0] . '://' . $this->normalizePath($baseUrl[1] . '/' . $folderOptions['backup_path']),
                                           $absolute);
                    }
                    return  array (
                        "backupPath" => $absolute,
                        "backupUrl" => $url
                    );
                }
                break;
            } else {
                $siblings .= "/" . basename($optionsDir);
                $optionsDir = dirname($optionsDir);
                $optionsParents .= "../";
            }
        }
        return array("backupPath" => false, "backupUrl" => false);
    }

    function renderBrowseFolderFragment($folder, $multiSelect, $onlyFolders, $onlyFiles, $extended = false) {
        $postDir = $this->folderFullPath($folder);
        $checkbox = $multiSelect ? "<input type='checkbox' />" : null;

        if( file_exists($postDir) ) {

            $files = scandir($postDir);
            if($extended) {
                $this->setupWrapper(false);

                $bkFld = $this->searchBackupFolder($postDir);
                $backupFolder = $bkFld['backupPath']; $backupUrl = $bkFld['backupUrl'];
                $filesStatus = \ShortPixel\folderInfo($postDir, false, true);
                //die(var_dump($filesStatus));
            }

            natcasesort($files);

            if( count($files) > 2 ) { // The 2 accounts for . and ..
                echo "<ul class='jqueryFileTree'>";
                foreach( $files as $file ) {

                    if(in_array($file, array('ShortPixelBackups', '.sp-options', '.shortpixel', '.sp-lock'))) continue;

                    $htmlRel	= $this->normalizePath(htmlentities($folder . '/' . $file));
                    $htmlName	= htmlentities($file);
                    $ext		= preg_replace('/^.*\./', '', $file);

                    if( file_exists($postDir . $file) && $file != '.' && $file != '..' ) {
                        if( is_dir($postDir . $file) && (!$onlyFiles || $onlyFolders) ) {
                            if($extended) {
                                //echo "<div class='sp-file-status'>25%</div>";
                            }
                            echo "<li class='directory collapsed'>{$checkbox}<a rel='" . $htmlRel . "/'>" . $htmlName . "</a>";
                            echo "</li>";
                        } else if (!$onlyFolders || $onlyFiles) {
                            echo "<li class='file ext_{$ext}'>{$checkbox}<a rel='" . $htmlRel . "/'>" . $htmlName . "</a>";
                            if($extended && isset($filesStatus->fileList[$file])) {
                                $info = $filesStatus->fileList[$file];
                                echo "<div class='sp-file-status'>";
                                switch($info->status) {
                                    case 'success':
                                        if($info->percent > 0) {
                                            echo "Optimized by " . $info->percent . "% (" . $info->compressionType . ")";
                                            $backupPath = $backupFolder . '/' . $file;
                                            if($backupFolder && $backupUrl && file_exists($backupPath)) {
                                                $originalUrl = $backupUrl . '/' . $file; $optimizedUrl = $backupPath;
                                                echo "<a class='optimized-view' href='#' data-original='" . $originalUrl . "' data-optimized='" . $optimizedUrl . "' title='Compare images for " . $file . " (original vs. lossy)' style='display: inline;'>";
                                                echo "<span class='dashicons sp-eye-open' style='cursor:pointer;font-size:1.2em'></span>";
                                                echo "</a>";
                                            }
                                        } else {
                                            echo "Bonus processing";
                                        }
                                        break;
                                    case 'pending':
                                        echo "Pending";
                                        break;
                                }
                                echo "</div></li>";
                            }
                        }
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
        $this->renderMain();
    }

    function renderOptimizeNow($optData) {
        $folder = $optData['folder'];
        $exclude = array();
        $folderPath = $this->normalizePath(dirname(dirname(__DIR__)) . $folder);
        if(!strlen($folder)) {
            $this->renderStartPage(array('error' => "Please select a folder."));
            return;
        }
        if(isset($optData['type'])) {
            //the action is from the Optimize now button and it has the settings, persist them in the .sp-options file
            if(isset($optData['backup_path'])) {
                $this->xtpl->assign('backup_path', $optData['backup_path']);
            }
            if(isset($optData['exclude'])) {
                $this->xtpl->assign('exclude', $optData['exclude']);
                $exclude = explode(',', $optData['exclude']);
            }
            if(!$this->persistFolderSettings($optData, $folderPath)){
                $this->xtpl->assign('error', "Could not write options file " . $folderPath . '/' . self::FOLDER_INI_NAME . ". Please check rights.");
                $this->xtpl->parse('main.progress.error');
            }
        }
        $this->setupWrapper($folderPath);
        $status = \ShortPixel\folderInfo(dirname(dirname(__DIR__)) . $folder, true, false, $exclude);
        $this->xtpl->assign('folder', $folder);

        if($status->status !== 'error' && $status->total == $status->succeeded + $status->failed) {
            //success
            $this->xtpl->assign('total_files', $status->total);
            $this->xtpl->assign('succeeded_files', $status->succeeded);
            $this->xtpl->assign('failed_files', $status->failed);
            $this->xtpl->parse('main.glyphicons');
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
        $this->renderMain();
    }

    function renderMain() {
        $this->xtpl->assign('web_version', WEB_VERSION);
        $this->xtpl->parse('main');
        $this->xtpl->out('main');
    }

    function optimizeAction($folder, $slice) {
        $timeLimit = ini_get('max_execution_time');
        if($timeLimit) {
            $timeLimit -= 5;
        } else {
            $timeLimit = 60;
        }

        $folderPath = dirname(dirname(__DIR__)) . $folder;
        $this->setupWrapper($folderPath);
        $slice = $slice ? $slice : \ShortPixel\ShortPixel::MAX_ALLOWED_FILES_PER_CALL;

        try {
            $exclude = array();
            if(\ShortPixel\opt('exclude')) {
                $exclude = explode(',',\ShortPixel\opt('exclude'));
            }
            if(\ShortPixel\opt('base_url')) {
                $cmd = \ShortPixel\fromWebFolder($folderPath, \ShortPixel\opt('base_url'), $exclude);
            } else {
                $cmd = \ShortPixel\fromFolder($folderPath, $slice, $exclude);
            }
            die(json_encode($cmd->wait($timeLimit)->toFiles($folderPath)));
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
