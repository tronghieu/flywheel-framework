<?php

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Cache_file extends Storage implements IStorage {

    function __construct($option = array()) {
        $this->set_option($option);

        if (!$this->_check_storage()) {
            throw new \Exception("Your cache directory is not writable.");
        }
        $this->get_path();
    }

    private function _file_path($key, $create_folder = false) {
        $path = $this->get_path();
        $code = md5($key);
        $folder = substr($code, 0, 2);
        $path = $path . "/" . $folder;

        if ($create_folder == false) {
            if (!file_exists($path)) {
                if (!@mkdir($path, 0777)) {
                    throw new \Exception($this->get_path() . " cannot writable");
                }
            } elseif (!is_writeable($path)) {
                @chmod($path, 0777);
            }
        }

        $file_path = $path . "/" . $code . ".txt";
        return $file_path;
    }

    private function _read_file($file) {
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
        $file_path = $this->_file_path($key);
        $data = serialize($value);
        $write = true;

        if (file_exists($file_path)) {
            $content = $this->_read_file($file_path);
            $old = @unserialize($content);
            $write = false;
            if ($this->is_expired($old)) {
                $write = true;
            }
        }

        if ($write == true) {
            $f = fopen($file_path, "w+");
            fwrite($f, $data);
            fclose($f);
        }
    }

    function get($key, $option = array()) {

        $file_path = $this->_file_path($key);
        if (!file_exists($file_path)) {
            return null;
        }

        $content = $this->_read_file($file_path);
        $object = @unserialize($content);
        if ($this->is_expired($object)) {
            @unlink($file_path);
            $this->_clean_expired();
            return null;
        }

        return $object;
    }

    function delete($key, $option = array()) {
        $file_path = $this->_file_path($key, true);
        if (@unlink($file_path)) {
            return true;
        } else {
            return false;
        }
    }

    function check_files($option = array()) {
        $res = array(
            "info" => "",
            "size" => "",
            "data" => "",
        );

        $path = $this->get_path();
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
                        $object = @unserialize($this->_read_file($file_path));
                        if ($this->is_expired($object)) {
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
            "Total" => $total,
            "Removed" => $removed,
            "Current" => $res['size'],
        );
        return $res;
    }

    function _clean_expired() {
        $autoclean = $this->get("timeout");
        if ($autoclean == null) {
            $this->set("timeout", 3600 * 24);
            $res = $this->check_files();
        }
    }

    function clear($option = array()) {

        $path = $this->get_path();
        $dir = @opendir($path);
        if (!$dir) {
            throw new \Exception("Can't read path:" . $path);
        }

        while ($file = readdir($dir)) {
            if ($file != "." && $file != ".." && is_dir($path . "/" . $file)) {
                // read sub dir
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

    function is_existing($key) {
        $file_path = $this->_file_path($key, true);
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

    function is_expired($object) {

        if (isset($object['expired_time']) && @date("U") >= $object['expired_time']) {
            return true;
        } else {
            return false;
        }
    }

    function _check_storage() {
        if (is_writable($this->get_path())) {
            return true;
        }
        return false;
    }

}
