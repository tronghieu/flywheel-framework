<?php

/**
 * Asset Management for Flywheel Framework
 *  'assets' => array(
 *         'environment' => 'dev', // dev||product
 * 
 * *       'base_url' => '',
 * *       'cache_dir' => '',
 * *       'cache_path' => '',  
 * *       'cache_url' => '',      
 * *       'js_path' => '',
 * *       'js_dir' => '',
 * *       'js_url' => '', 
 * *       'css_path' => '', 
 * *       'css_dir' => '',
 * *       'css_url' => '',
 * *       'minify_css' => '',
 * *       'minify_js' => '',
 *    
 * 
 * );
 * @author tradade
 */

namespace Flywheel\Asset;

use Flywheel\Factory;
use Flywheel\Config\ConfigHandler as ConfigHandler;
use Flywheel\Loader as Loader;

require_once('cssmin.php');
require_once('jsmin.php');

class Asset {

    public $debug = true;
    public $config;
    public $base_url;
    public $cache_dir, $cache_path, $cache_url;
    public $js_dir, $js_path, $js_url;
    public $css_dir, $css_path, $css_url;
    public $asset_uri = '';
    public $minify = false;
    public $combine = true;
    private $_assets = array();
    private $_css = array();
    private $_js = array();
    private $js_array = array('main' => array());
    private $css_array = array('main' => array());
    private $js_str, $css_str;

    function __construct($config = array()) {
        $this->_path();
        $site_config = \ConfigHandler::get('assets');
        $this->_config($config);
    }

    function _config($config) {
        foreach ($config as $key => $value) {
            if ($key == 'groups') {
                foreach ($value as $group_name => $assets) {
                    $this->group($group_name, $assets);
                }
                break;
            }
            $this->$key = $value;
        }


        if (!$this->base_url) {
            $base_url = "http://" . $_SERVER['HTTP_HOST'];
            $base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);

            $this->base_path = reduce_double_slashes(realpath($this->assets_dir));
            $this->base_url = $base_url . $this->asset_dir;
        } else {
            $this->base_url = $this->base_url . $this->assets_dir;
        }
    }

    static function display($type, $group = NULL) {
        switch (strtolower($type)) {
            case 'js':
                $this->_display_js();
                $this->_display_js_string();
                break;
            case 'css':
                $this->_display_css();
                $this->_display_css_string();
                break;
            case 'both':
                $this->_display_js();
                $this->_display_js_string();
                $this->_display_css();
                $this->_display_css_string();
                break;
            default:
                break;
        }
    }

    private function _display_js($group = 'main') {
        if (empty($this->js)) {
            return;
        }
        if (!isset($this->js[$group])) {
            return;
        }
        $jsfiles = $this->js[$group];
        if ($this->envi == 'dev') {
            foreach ($jsfiles AS $jsf) {
                $this->print_tag($jsf, 'js', '', $echo);
            }
        } elseif ($this->combine == true && $this->minify == true) {
            $now = time();
            $cache_name = '';
            $last_modified = 0;

            foreach ($jsfiles AS $file) {
                $lastmodified = max($lastmodified, filemtime(realpath($this->js_path . $file)));
                $cache_name .= $file;
            }
            $cache_name = $this->cache_path . $lastmodified . '.' . md5($cache_name) . '.js';
            $jsr = $this->_combine('js', $cache_name);
        }
    }

    //Add assets to a group
    public function group($group_name = '', $assets) {
        if (!isset($assets['js']) && !isset($assets['css'])) {
            return;
        }
        if (isset($assets['js']))
            $this->js($assets['js'], $group_name);

        if (isset($assets['css']))
            $this->css($assets['css'], $group_name);
    }

    //Add css
    static function css($files, $group = 'main') {
        if (is_string($files)) {
            $files = array($files);
        }
        foreach ($files AS $file) {
            $this->_assets('css', $file);
        }
    }

    //add js
    static function js() {
        if (is_string($files)) {
            $files = array($files);
        }
        foreach ($files AS $file) {
            $this->_assets('js', $file);
        }
    }

    private function _assets($type, $file, $group = 'main') {
        if ($type == 'css') {
            $this->_css[$group][] = $file;
        }
        if ($type == 'js') {
            $this->_js[$group][] = $file;
        }
    }

    private function _get_file_string($file) {
        if ($this->_is_url($file) && function_exists('curl_version')) {
            $file_data = $this->_get_url($file);
        } else {
            $file_data = file_get_contents($file);
        }
        return $file_data;
    }

    public function print_tag($file = '', $type = 'css', $attributes = '', $echo = true) {

        if ($type === 'css') {
            $str = '<link type="text/css" href="' . $file . '"' . $attributes . ' />' . PHP_EOL;
        } elseif ($type === 'js') {
            $str = '<script src="' . $file . '" type="text/javascript"' . $attributes . '></script>' . PHP_EOL;
        }
        if ($echo) {
            echo $str;
        } else {
            return $str;
        }
    }

    public function clear_css_cache() {
        return $this->clear_cache('css');
    }

    public function clear_js_cache() {
        return $this->clear_cache('js');
    }

    public function clear_cache($type = '') {
        if ($type === 'css') {
            foreach (new DirectoryIterator($this->css_path) as $file) {
                if (!$file->isDot() && $file->getExtension() === 'css') {
                    unlink($file->getFilename());
                }
            }
        }
        if ($type === 'js') {
            foreach (new DirectoryIterator($this->js_path) as $file) {
                if (!$file->isDot() && $file->getExtension() === 'js') {
                    unlink($file->getFilename());
                }
            }
        }
        return true;
    }

    private function _js_string($str, $group = 'main') {
        if (is_string($str)) {
            $str = array($str);
        }
        foreach ($str AS $st) {
            $this->js_str[$group][] = $st;
        }
    }

    private function _css_string($str, $group = 'main') {
        if (is_string($str)) {
            $str = array($str);
        }
        foreach ($str AS $st) {
            $this->css_str[$group][] = $st;
        }
    }

    private function _minify($type, $file_path) {
        if (!file_exists($file_path)) {
            throw new Exception('File ' . $file_path . ' does not exist');
        }
        $file_content = $this->_get_file_string($file_path);
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

    private function _combine($type, $files) {
        $file_content = '';
        $file_path = '';

        switch ($type) {
            case 'css':
                $file_path = $this->css_path;
                foreach ($files AS $file) {
                    if ($this->minify_css) {
                        $file_content .= $this->_minify($file_path);
                    } else {
                        $file_content .= $this->_get_file_string($file_path);
                    }
                }
                break;
            case 'js':
                $file_path = $this->js_path;
                foreach ($files AS $file) {
                    if ($this->minify_js) {
                        $file_content .= $this->_minify($file_path);
                    } else {
                        $file_content .= $this->_get_file_string($file_path);
                    }
                }
                break;
            default:
                break;
        }
        $this->_save_cache($file_name, $file_content);
    }

//    private function _path() {
//
//        $this->base_url = "http://" . $_SERVER['HTTP_HOST'];
//        $this->base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
//
//        $this->base_path = reduce_double_slashes(realpath($this->assets_dir));
//
//        // Now set the assets base URL
//        if (!$this->base_url) {
//            $this->base_url = reduce_double_slashes(\ConfigHandler::get('assets.base_url') . '/' . $this->assets_dir);
//        } else
//            $this->base_url = $this->base_url . $this->assets_dir;
//
//        // Auto protocol
//        if (stripos($this->base_url, '//') === 0)
//            $slash = '/';
//        else
//            $slash = '';
//
//        // And finally the paths and URL's to the css and js assets
//        $this->js_path = reduce_double_slashes($this->base_path . '/' . $this->js_dir);
//        $this->js_url = $slash . reduce_double_slashes($this->base_url . '/' . $this->js_dir);
//        $this->css_path = reduce_double_slashes($this->base_path . '/' . $this->css_dir);
//        $this->css_url = $slash . reduce_double_slashes($this->base_url . '/' . $this->css_dir);
//        $this->img_path = reduce_double_slashes($this->base_path . '/' . $this->img_dir);
//        $this->img_url = $slash . reduce_double_slashes($this->base_url . '/' . $this->img_dir);
//        $this->cache_path = reduce_double_slashes($this->base_path . '/' . $this->cache_dir);
//        $this->cache_url = $slash . reduce_double_slashes($this->base_url . '/' . $this->cache_dir);
//    }

    private function _get_url($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function _is_url($str) {
        $pattern = '@(((https?|ftp):)?//([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@';
        return preg_match($pattern, $string);
    }

    public function _save_cache($file_name, $file_data) {
        $filepath = $this->cache_path . $filename;
        $result = file_put_contents($filepath, $file_data);
        return $result;
    }

}

?>
