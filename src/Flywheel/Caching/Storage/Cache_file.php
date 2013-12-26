<?php

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Cache_file extends Storage implements IStorage {

    function __construct($key, $option = array()) {
        //print_r($this->option);die;
        $this->set_option($option);
        $this->get_path();
        if (!$this->_check_storage()) {
            throw new \Exception("Your cache directory is not writable.");
        }
    }

    private function file_path($keyword, $skip = false) {
        $path = $this->getPath();
        $code = md5($keyword);
        $folder = substr($code, 0, 2);
        $path = $path . "/" . $folder;
        /*
         * Skip Create Sub Folders;
         */
        if ($skip == false) {
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

    function set($keyword, $value = "", $time = 300, $option = array()) {
        $file_path = $this->getFilePath($keyword);
        //  echo "<br>DEBUG SET: ".$keyword." - ".$value." - ".$time."<br>";
        $data = $this->encode($value);

        $toWrite = true;
        /*
         * Skip if Existing Caching in Options
         */
        if (file_exists($file_path)) {
            $content = $this->readfile($file_path);
            $old = $this->decode($content);
            $toWrite = false;
            if ($this->is_expired($old)) {
                $toWrite = true;
            }
        }

        if ($toWrite == true) {
            $f = fopen($file_path, "w+");
            fwrite($f, $data);
            fclose($f);
        }
    }

    function get($keyword, $option = array()) {

        $file_path = $this->getFilePath($keyword);
        if (!file_exists($file_path)) {
            return null;
        }

        $content = $this->readfile($file_path);
        $object = $this->decode($content);
        if ($this->is_expired($object)) {
            @unlink($file_path);
            $this->auto_clean_expired();
            return null;
        }

        return $object;
    }

    function delete($keyword, $option = array()) {
        $file_path = $this->getFilePath($keyword, true);
        if (@unlink($file_path)) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * Return total cache size + auto removed expired files
     */

    function stats($option = array()) {
        $res = array(
            "info" => "",
            "size" => "",
            "data" => "",
        );

        $path = $this->getPath();
        $dir = @opendir($path);
        if (!$dir) {
            throw new Exception("Can't read PATH:" . $path, 94);
        }

        $total = 0;
        $removed = 0;
        while ($file = readdir($dir)) {
            if ($file != "." && $file != ".." && is_dir($path . "/" . $file)) {
                // read sub dir
                $subdir = @opendir($path . "/" . $file);
                if (!$subdir) {
                    throw new Exception("Can't read path:" . $path . "/" . $file);
                }

                while ($f = readdir($subdir)) {
                    if ($f != "." && $f != "..") {
                        $file_path = $path . "/" . $file . "/" . $f;
                        $size = filesize($file_path);
                        $object = $this->decode($this->readfile($file_path));
                        if ($this->is_expired($object)) {
                            unlink($file_path);
                            $removed = $removed + $size;
                        }
                        $total = $total + $size;
                    }
                } // end read subdir
            } // end if
        } // end while

        $res['size'] = $total - $removed;
        $res['info'] = array(
            "Total" => $total,
            "Removed" => $removed,
            "Current" => $res['size'],
        );
        return $res;
    }

    function auto_clean_expired() {
        $autoclean = $this->get("timeout");
        if ($autoclean == null) {
            $this->set("timeout", 3600 * 24);
            $res = $this->stats();
        }
    }

    function clear($option = array()) {

        $path = $this->getPath();
        $dir = @opendir($path);
        if (!$dir) {
            throw new Exception("Can't read path:" . $path);
        }

        while ($file = readdir($dir)) {
            if ($file != "." && $file != ".." && is_dir($path . "/" . $file)) {
                // read sub dir
                $subdir = @opendir($path . "/" . $file);
                if (!$subdir) {
                    throw new Exception("Can't read path:" . $path . "/" . $file, 93);
                }

                while ($f = readdir($subdir)) {
                    if ($f != "." && $f != "..") {
                        $file_path = $path . "/" . $file . "/" . $f;
                        unlink($file_path);
                    }
                } // end read subdir
            } // end if
        } // end while
    }

    function is_existing($keyword) {
        $file_path = $this->getFilePath($keyword, true);
        if (!file_exists($file_path)) {
            return false;
        } else {
            // check expired or not
            $value = $this->get($keyword);
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
        echo $this->getPath();die;
        if (is_writable($this->getPath())) {
            return true;
        } else {
            
        }
        return false;
    }


}
