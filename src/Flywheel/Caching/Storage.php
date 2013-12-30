<?php

namespace Flywheel\Caching;

use Flywheel\Config\ConfigHandler;
use Flywheel\Object;

class Storage extends Object {

    protected $_hash;
    protected $_group;
    protected $_path;
    protected $_cachePath;
    protected static $_instances = array();
    public static $config;
    public static $storage;
    var $option = array();
    var $tmp = array();

    public function __construct($option = array()) {

        $hash = (isset($option['hash'])) ?
                $option['hash'] : null;

        if (!$hash) {
            $hash = $option['hash'];
        }
        self::$storage = $option['storage'];

        $this->option = array_merge($this->option, self::$config, $option);
        $this->_hash = md5($hash);
        $this->_group = (isset($option['group'])) ? $option['group'] : $key;
    }

    /**
     * return IStorage
     */
    public static function factory($key = null) {
        $configs = ConfigHandler::get('caching');
      
        self::$config = $configs;

        $key = $key ? $key : $configs['default'];
        $option = $configs[$key];


        if (!isset(self::$_instances[$option['storage']])) {
            $class = "\\Flywheel\Caching\\Storage\\Cache_" . $option['storage'];
            self::$_instances[$key] = new $class($option['option']);
        }

        return self::$_instances[$key];
    }

    function option($name, $value = null) {
        if ($value == null) {
            if (isset($this->option[$name])) {
                return $this->option[$name];
            } else {
                return null;
            }
        } else {
            self::$config[$name] = $value;
            $this->option[$name] = $value;
            return $this;
        }
    }

    public function setOption($option = array()) {
        $this->option = array_merge($this->option, $option);
    }

    public function getPath($path = false) {
        if ($this->option['path'] == "" && self::$config['path'] != "") {
            $this->option("path", self::$config['path']);
        }


        if ($this->option['path'] == '') {
            if ($this->isPHPModule()) {
                $tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
                $this->option("path", $tmp_dir);
            } else {
                $this->option("path", dirname(__FILE__));
            }

            if (self::$config['path'] == "") {
                self::$config['path'] = $this->option("path");
            }
        }

        $full_path = $this->option("path") . "/" . $this->option("hash") . "/";

        if ($path == false) {

            if (!file_exists($full_path) || !is_writable($full_path)) {
                if (!file_exists($full_path)) {
                    @mkdir($full_path, 0777);
                }
                if (!is_writable($full_path)) {
                    @chmod($full_path, 0777);
                }
                if (!file_exists($full_path) || !is_writable($full_path)) {
                    throw new Exception("You will need create a folder named " . $this->option("path") . "/" . $this->option("hash") . "/ and chmod 0777 to use file cache");
                }
            }
        }

        $this->option['cachePath'] = $full_path;
        return $this->option['cachePath'];
    }

    function keyName($key) {
        return $this->_hash.'-'.md5($key);
    }

}
