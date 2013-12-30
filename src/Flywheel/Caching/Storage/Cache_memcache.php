<?php

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Cache_memcache extends Storage implements IStorage {
    /*
     * $config['servers'] = array(
     *  'default' => array(
     *  'host' => '',
     *  'port' =>  '',
     *  'weight' => '1',
     *  'persistent' => FALSE
     * )
     * ); */

    var $mc;

    function _construct($option = array()) {
        if (!function_exists("memcache_connect")) {
            throw new Exception("Cannot load Memcache driver. Make sure Memcache extension has been installed on your system.");
        }
        $this->setOption($option);
        $this->mc = new \Memcache;
        $this->connect();
    }

    private function _setup() {
        $this->mc = new Memcache;
        $this->connect();
    }

    function connect() {
        foreach ($this->option['servers'] as $key => $server) {
            if (!$this->add_server($server)) {
                throw new \Exception("Memcache: Could not connect to the server named $key");
            }
        }
    }

    function add_server($server) {
        extract($server);
        return $this->mc->addServer($host, $port, $weight);
    }

    function set($key, $value = "", $lifetime = 300) {
        $this->_setup();
        return $this->mc->set($this->keyName($key), $value, $lifetime);
    }

    function get($key) {
        $this->_setup();
        return $this->mc->get($this->keyName($key));
    }

    function delete($key, $option = array()) {
        $this->_setup();
        return $this->mc->delete($this->keyName($key));
    }

    function clear() {
        $this->_setup();
        return $this->mc->flush();
    }

}
