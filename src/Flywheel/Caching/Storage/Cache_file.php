<?php

//based on khoaofgod cache lib

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Cache_file extends Storage implements IStorage {

    function __construct($option = array()) {
        $this->set_option($option);

        if (!$this->_checkDriver()) {
            throw new \Exception("Your cache directory is not writable.");
        }
        $this->getPath();
    }

    private function _filePath($key, $create_folder = false) {
        $path = $this->getPath();
        $code = md5($key);
        $folder = substr($code, 0, 2);
        $path = $path . "/" . $folder;

        if ($create_folder == false) {
            if (!file_exists($path)) {
                if (!@mkdir($path, 0777)) {
                    throw new \Exception($this->getPath() . " cannot writable");
                }
            } elseif (!is_writeable($path)) {
                @chmod($path, 0777);
            }
        }

        $file_path = $path . "/" . $code . ".txt";
        return $file_path;
    }

    private function _readFile($file) {
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

    function set($key, $value = "", $time = 300, $option = array()) {
        $file_path = $this->_filePath($this->keyName($key));
        $data = serialize($value);
        $write = true;

        if (file_exists($file_path)) {
            $content = $this->_readFile($file_path);
            $old = @unserialize($content);
            $write = false;
            if ($this->isExpired($old)) {
                $write = true;
            }
        }

        if ($write == true) {
            $f = fopen($file_path, "w+");
            fwrite($f, $data);
            fclose($f);
        }
    }

    function get($key) {
        $file_path =  $this->_filePath($this->keyName($key));;
        if (!file_exists($file_path)) {
            return null;
        }

        $content = $this->_readFile($file_path);
        $object = @unserialize($content);
        if ($this->isExpired($object)) {
            @unlink($file_path);
            $this->_cleanExpired();
            return null;
        }

        return $object;
    }

    function delete($key, $option = array()) {
        $file_path = $this->_filePath($key, true);
        if (@unlink($file_path)) {
            return true;
        } else {
            return false;
        }
    }

    function checkFiles() {
        $res = array(
            "info" => "",
            "size" => "",
            "data" => "",
        );

        $path = $this->getPath();
        $dir = @opendir($path);
        if (!$dir) {
            throw new \Exception("Can't read path:" . $path);
        }

        $total = 0;
        $removed = 0;
        while ($file = readdir($dir)) {
            if ($file != "." && $file != ".." && is_dir($path . "/" . $file)) {

                $subdir = @opendir($path . "/" . $file);
                if (!$subdir) {
                    throw new \Exception("Can't read path:" . $path . "/" . $file);
                }

                while ($f = readdir($subdir)) {
                    if ($f != "." && $f != "..") {
                        $file_path = $path . "/" . $file . "/" . $f;
                        $size = filesize($file_path);
                        $object = @unserialize($this->_readFile($file_path));
                        if ($this->isExpired($object)) {
                            unlink($file_path);
                            $removed = $removed + $size;
                        }
                        $total = $total + $size;
                    }
                }
            }
        }

        $res['size'] = $total - $removed;
        $res['info'] = array(
            "total" => $total,
            "removed" => $removed,
            "current" => $res['size'],
        );
        return $res;
    }

    function _cleanExpired() {
        $autoclean = $this->get("timeout");
        if ($autoclean == null) {
            $this->set("timeout", 3600 * 24);
            $res = $this->checkFiles();
        }
    }

    function clear() {

        $path = $this->getPath();
        $dir = @opendir($path);
        if (!$dir) {
            throw new \Exception("Can't read path:" . $path);
        }

        while ($file = readdir($dir)) {
            if ($file != "." && $file != ".." && is_dir($path . "/" . $file)) {
                $subdir = @opendir($path . "/" . $file);
                if (!$subdir) {
                    throw new \Exception("Can't read path:" . $path . "/" . $file);
                }

                while ($f = readdir($subdir)) {
                    if ($f != "." && $f != "..") {
                        $file_path = $path . "/" . $file . "/" . $f;
                        unlink($file_path);
                    }
                }
            }
        }
    }

    function isExisting($key) {
        $file_path = $this->_filePath($key, true);
        if (!file_exists($file_path)) {
            return false;
        } else {
            $value = $this->get($key);
            if ($value == null) {
                return false;
            } else {
                return true;
            }
        }
    }

    function isExpired($object) {

        if (isset($object['expired_time']) && @date("U") >= $object['expired_time']) {
            return true;
        } else {
            return false;
        }
    }

    function _checkDriver() {
        if (is_writable($this->getPath())) {
            return true;
        }
        return false;
    }

}
