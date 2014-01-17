<?php

/**
 * Asset Management for Flywheel Framework
 *  'assets' => array(
 *      'default' => array(
 *         'environment' => 'dev', // dev||product *
 * *       'base_url' => '', // auto define if option is null
 * *       'cache_dir' => 'cache', // 
 * *       'cache_path' => '', /path/to/cache/folder. Default assets/cache/
 * *       'cache_url' => 'http://domain/assets/cache',
 * *       'js_path' => '', //Default: /pathto/assets/js
 * *       'js_dir' => 'js',  // 
 * *       'js_url' => 'http://domain/assets/js',
 * *       'css_path' => '',
 * *       'css_dir' => 'css',
 * *       'css_url' => 'http://domain/assets/css' 
 *    ),
 *
 * );
 * @author tradade
 */

namespace Flywheel\Asset;

use \Flywheel\Factory;
use \Flywheel\Config\ConfigHandler;
use \Flywheel\Loader as Loader;

require_once('cssmin.php');
require_once('jsmin.php');

class Asset
{

    public $envi = 'dev';
    public $config;
    public $base_url, $base_path;
    public $assets_dir, $assets_path;
    public $cache_dir, $cache_path, $cache_url;
    public $js_dir, $js_path, $js_url;
    public $css_dir, $css_path, $css_url;
    public $asset_uri = '';
    public $minify = true;
    public $combine = true;
    private $_assets = array();
    private $_css = array();
    private $_js = array();
    private $js_array = array('main' => array());
    private $css_array = array('main' => array());
    private $js_str, $css_str;

    function __construct($section = 'default')
    {


        $config = ConfigHandler::get('assets');
        if (!$config) {
            throw new Exception('Config "assets" not found');
        }
        $config = $config[$section];

        $this->_config($config);
    }

    private function _path()
    {

        if (!$this->base_url) {
            $this->base_url = "http://" . $_SERVER['HTTP_HOST'];
            $this->base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']) . $this->assets_dir;
            $this->base_path = realpath($this->assets_path);
        } else {
            $this->base_url = $this->base_url . $this->assets_dir;
        }


        if (stripos($this->base_url, '//') === 0) {
            $slash = '/';
        } else {
            $slash = '';
        }

        $this->cache_path = $this->base_path . '/' . $this->cache_dir . '/';
        $this->cache_url = $slash . $this->base_url . '/' . $this->cache_dir . '/';
        $this->js_path = $this->base_path . '/' . $this->js_dir . '/';
        $this->js_url = $slash . $this->base_url . '/' . $this->js_dir . '/';
        $this->css_path = $this->base_path . '/' . $this->css_dir . '/';
        $this->css_url = $slash . $this->base_url . '/' . $this->css_dir . '/';
    }

    private function _config($config)
    {
        foreach ($config as $key => $value) {
            if ($key == 'groups') {
                foreach ($value as $group_name => $assets) {
                    $this->group($group_name, $assets);
                }
                break;
            }
            $this->$key = $value;
        }

        $this->_path();
    }

    public function display($type, $group = 'main')
    {
        switch (strtolower($type)) {
            case 'js':
                $this->_displayJs($group);
                break;
            case 'css':
                $this->_displayCss($group);
                break;
            default:
                $this->_displayJs($group);
                $this->_displayCss($group);
                break;
        }
    }

    private function _displayJs($group = 'main')
    {
        if (empty($this->_js)) {
            return;
        }
        if (!isset($this->_js[$group])) {
            return;
        }
        $files = $this->_js[$group];
        $cfiles = array();
        if ($this->envi == 'dev') {
            foreach ($files AS $fl) {
                $url = $this->js_url . $fl;
                $this->printTag($url, 'js', '', true);
            }
        } elseif ($this->combine == true && $this->minify == true) {
            $now = time();
            $cache_name = '';
            $last_modified = 0;

            foreach ($files AS $file) {
                $lastmodified = max($lastmodified, filemtime(realpath($this->js_path . $file)));
                $cache_name .= $file;

                $cfiles[] = $file;
            }

            $cache_name = $lastmodified . '.' . md5($cache_name) . '.js';
            if (!file_exists($this->cache_path . $cache_name)) {
                $this->_combine('js', $this->cache_path . $cache_name);
            }

            echo $this->_printCache($cache_name, 'js');
        }
    }

    private function _displayCss($group = 'main')
    {
        if (empty($this->_css)) {
            return;
        }
        if (!isset($this->_css[$group])) {
            return;
        }
        $files = $this->_css[$group];
        $cfiles = array();
        if ($this->envi == 'dev') {
            foreach ($files AS $fl) {
                $url = $this->css_url . $fl;
                $this->printTag($url, 'css', '', true);
            }
        } elseif ($this->combine == true) {
            $now = time();
            $cache_name = '';
            $last_modified = 0;

            foreach ($files AS $file) {
                $lastmodified = max($lastmodified, filemtime(realpath($this->css_path . $file)));
                $cache_name .= $file;

                $cfiles[] = $file;
            }
            $cache_name = $lastmodified . '.' . md5($cache_name) . '.css';
            if (!file_exists($this->cache_path . $cache_name)) {
                $this->_combine('css', $cfiles, $this->cache_path . $cache_name);
            }

            echo $this->_printCache($cache_name, 'css');
        }
    }

    //Add assets to a group
    public function group($group_name = '', $assets)
    {
        if (!isset($assets['js']) && !isset($assets['css'])) {
            return;
        }
        if (isset($assets['js']))
            $this->js($assets['js'], $group_name);

        if (isset($assets['css']))
            $this->css($assets['css'], $group_name);
    }

    //Add css
    public function css($files, $group = 'main')
    {
        if (is_string($files)) {
            $files = array($files);
        }
        foreach ($files AS $file) {
            $this->_assets('css', $file, $group);
        }
    }

    //add js
    public function js($files, $group = 'main')
    {
        if (is_string($files)) {
            $files = array($files);
        }
        foreach ($files AS $file) {
            $this->_assets('js', $file, $group);
        }
    }

    private function _assets($type, $file, $group = 'main')
    {
        if ($type == 'css') {
            $this->_css[$group][] = $file;
        }
        if ($type == 'js') {
            $this->_js[$group][] = $file;
        }
    }

    private function _getFileString($file)
    {
        if ($this->_isUrl($file) && function_exists('curl_version')) {
            $file_data = $this->_getUrl($file);
        } else {
            if (function_exists("file_get_contents")) {
                return file_get_contents($file);
            } else {
                $string = "";

                $file_handle = @fopen($file, "r");
                if (!$file_handle) {
                    throw new \Exception("Can't read file");
                }
                while (!feof($file_handle)) {
                    $line = fgets($file_handle);
                    $string .= $line;
                }
                fclose($file_handle);

                return $string;
            }
        }
        return $file_data;
    }

    public function printTag($file = '', $type = 'css', $attributes = '', $echo = true)
    {
        $str = '';
        if ($type === 'css') {
            $str = '<link rel="stylesheet" type="text/css" href="' . $file . '"' . $attributes . ' />' . PHP_EOL;
        } elseif ($type === 'js') {
            $str = '<script src="' . $file . '" type="text/javascript"' . $attributes . '></script>' . PHP_EOL;
        }
        if ($echo) {
            echo $str;
        } else {
            return $str;
        }
    }

    public function _printCache($file = '', $type = 'css')
    {
        $url = $this->cache_url . $file;

        if ($type === 'css') {
            return $str = '<link rel="stylesheet" type="text/css" href="' . $url . '"' . $attributes . ' />' . PHP_EOL;
        } elseif ($type === 'js') {
            return '<script src="' . $url . '" type="text/javascript"></script>' . PHP_EOL;
        }
    }

    public function clear($type = '')
    {
        if ($type === 'css') {
            foreach (new DirectoryIterator($this->cache_path) as $file) {
                if (!$file->isDot() && $file->getExtension() === 'css') {
                    unlink($file->getFilename());
                }
            }
        } elseif ($type === 'js') {
            foreach (new DirectoryIterator($this->cache_path) as $file) {
                if (!$file->isDot() && $file->getExtension() === 'js') {
                    unlink($file->getFilename());
                }
            }
        } else {
            foreach (new DirectoryIterator($this->cache_path) as $file) {
                unlink($file->getFilename());
            }
        }

        return true;
    }

    private function _jsString($str, $group = 'main')
    {
        if (is_string($str)) {
            $str = array($str);
        }
        foreach ($str AS $st) {
            $this->js_str[$group][] = $st;
        }
    }

    private function _cssString($str, $group = 'main')
    {
        if (is_string($str)) {
            $str = array($str);
        }
        foreach ($str AS $st) {
            $this->css_str[$group][] = $st;
        }
    }

    private function _minify($type, $file_path)
    {

        $file_content = $this->_getFileString($file_path);
        switch ($type) {
            case 'css':
                return \CssMin::minify($file_content);
                break;
            case 'js':
                return \JSMin::minify($file_content);
                break;
            default:
                break;
        }
    }

    private function _combine($type, $files, $file_name)
    {
        $file_content = '';
        $file_path = '';
        if (!is_dir($this->cache_path)) {
            if (!mkdir($this->cache_path, 0777, true)) {
                throw new Exception('Cache folder ' . $this->cache_path . ' does not exist');
                die;
            }
        }
        switch ($type) {
            case 'css':

                foreach ($files AS $file) {
                    $file_path = $this->css_path . $file;
                    if (!file_exists($file_path)) {
                        throw new Exception('File ' . $file_path . ' does not exist');
                        die;
                    }
                    if ($this->minify) {
                        $file_content .= $this->_minify('css', $file_path);
                    } else {
                        $file_content .= $this->_getFileString($file_path);
                    }
                }

                break;
            case 'js':
                $file_path = $this->js_path;
                foreach ($files AS $file) {
                    $file_path = $this->js_path . $file;
                    if ($this->minify) {
                        $file_content .= $this->_minify('js', $file_path);
                    } else {
                        $file_content .= $this->_getFileString($file_path);
                    }
                }
                break;
            default:
                break;
        }
        $this->_saveCache($file_name, $file_content);
    }

    private function _getUrl($url)
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function _isUrl($string)
    {
        $pattern = '@(((https?|ftp):)?//([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@';
        return preg_match($pattern, $string);
    }

    public function _saveCache($file_name, $file_data)
    {
        $result = file_put_contents($file_name, $file_data);
        return $result;
    }

}

?>
