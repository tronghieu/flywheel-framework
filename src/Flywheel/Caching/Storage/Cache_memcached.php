<?php

namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Cache_memcached extends Storage implements IStorage {
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
        if (!class_exists("Memcached")) {
            throw new \Exception ("Cannot load Memcached driver. Make sure Memcached has been installed on your system.");
        }
        $this->set_option($option);
    }

    private function _setup() {
        $this->mc = new \Memcached();
        $this->connect();
    }

    function connect() {
        foreach ($this->option['servers'] as $key => $server) {
            if (!$this->add_server($server)) {
                throw new \Exception("Memcached: Could not connect to the server named $key");
            }
        }
    }

    function add_server($server) {
        extract($server);
        return $this->mc->addServer($host, $port, $weight);
    }

    function set($key, $value = "", $lifetime = 300) {
        //$this->_setup();
        return $this->mc->set($this->_key_name($key), $value, $lifetime);
    }

    function get($key) {
        //$this->_setup();
        return $this->mc->get($this->_key_name($key));
    }

    function delete($key, $option = array()) {
        //$this->_setup();
        return $this->mc->delete($this->_key_name($key));
    }

    function clear() {
        //$this->_setup();
        return $this->mc->flush();
    }

    private function _key_name($key) {
        return md5($this->option['hash'] . $key);
    }

}
