<?php

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Apc extends Storage implements IStorage {

    function _construct($option = array()) {
        if ($this->_checkDriver()) {
            throw new \Exception("Cannot load APC driver. Make sure APC extension has been installed on your system.");
        }
    }

    public function set($key, $value = "", $lifetime = 300, $option = array()) {
        return apc_add($this->keyName($key), $value, $lifetime);
    }

    public function get($key) {
        $data = apc_fetch($this->keyName($key), $success);
        if ($success === false) {
            return null;
        }
        return $data;
    }

    public function delete($key) {
        return apc_delete($this->keyName($key));
    }

    public function clear() {
        return @apc_clear_cache();
    }

    private function _checkDriver() {
        if (extension_loaded('apc') && ini_get('apc.enabled')) {
            return true;
        } else {
            return false;
        }
    }

}
