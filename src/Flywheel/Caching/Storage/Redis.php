<?php

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;
use \Redis AS Red;
class Redis extends Storage implements IStorage {

    public $option;
    var $_redis = NULL;
    var $_host = '127.0.0.1';
    var $_port = 6379;
    var $_persistant = true;
    var $_index = 0;

    function _construct($option = array()) {
        if ($this->_checkDriver()) {
            throw new \Exception("Cannot load Redis driver. Make sure Redis has been installed and running on your system.");
        }

        $this->_redis = new \Red;
        foreach ($option['servers'] AS $server) {
            if (!array_key_exists('port', $server)) {
                $server['port'] = self::$_port;
            }
            if (!array_key_exists('host', $server)) {
                $server['host'] = self::$_host;
            }
            if (!array_key_exists('persistent', $server)) {
                $server['persistent'] = self::$_persistant;
            }
            if (!array_key_exists('index', $server)) {
                $server['index'] = self::$_index;
            }
            if ($server['persistent']) {
                $result = $this->_redis->pconnect($server['host'], $server['port']);
            } else {
                $result = $this->_redis->connect($server['host'], $server['port']);
            }

            if ($result) {
                $this->_redis->select($server['index']);
            }
        }
    }

    function set($key, $value = "", $lifetime = 300, $option = array()) {
        return $this->_redis->set($this->keyName($key), $value, $lifetime);
    }

    function get($key, $option = array()) {
        $data = $this->_redis->get($this->keyName($key));
        return $data;
    }

    function delete($key) {
        if (empty($key)) {
            return false;
        }
        if (is_string($key)) {
            $key = array($key);
        }
        $id_array = array();
        foreach ($key as $id) {
            $id_array = $this->keyName($id);
        }
        return $this->_redis->delete($id_array);
    }

    function clear() {
        return $this->_redis->flush();
    }

    private function _checkDriver() {
        if (class_exists('Redis')) {
            return true;
        }
        return false;
    }

    private function _checkConnected() {
        if ($this->_redis) {
            return true;
        }
        return false;
    }

}
