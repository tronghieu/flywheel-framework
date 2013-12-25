<?php

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Apc extends Storage implements IStorage {

    function _construct($options = array()) {
        if ($this->_check_driver()) {
            throw new Exception("Cannot load APC driver. Make sure APC extension has been installed on your system.");
        }
    }

    public function set($key, $value = "", $lifetime = 300, $option = array()) {
        return apc_add($key, $value, $lifetime);
    }

    public function get($key, $option = array()) {
        $data = apc_fetch($key, $success);
        if ($success === false) {
            return null;
        }
        return $data;
    }

    public function delete($key, $option = array()) {
        return apc_delete($key);
    }

    public function clear() {
        return @apc_clear_cache();
    }

    private function _check_driver() {
        if (extension_loaded('apc') && ini_get('apc.enabled')) {
            return true;
        } else {
            return false;
        }
    }

}
