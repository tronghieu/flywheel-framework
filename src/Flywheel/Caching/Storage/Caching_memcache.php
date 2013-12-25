<?php
namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Memcache extends Storage implements IStorage {
    /*
     * $config['servers'] = array(
     *  'default' => array(
     *  'host' => '',
     *  'port' =>  '',
     *  'weight' => '1',
     *  'persistent' => FALSE
     * )
     * ); */

    var $mc = NULL;

    function _construct($options = array()) {
        if ($this->_check_driver()) {
            throw new Exception("Cannot load Memcache driver. Make sure Memcache extension has been installed on your system.");
        }
        
        $this->mc = new Memcache();
        $this->connect();
    }

    function connect() {
        foreach ($this->option['servers'] as $key => $server) {
            if (!$this->add_server($server)) {
                throw new Exception("Memcached Library: Could not connect to the server named $key");
            }
        }
    }

    function add_server($server) {
        extract($server);
        return $this->mc->addServer($host, $port, $weight);
    }

    function set($key, $value = "", $lifetime = 300, $option = array()) {
        return $this->mc->set($this->_key_name($key), $value, $lifetime);
    }

    function get($key, $option = array()) {
        $data = apc_fetch($key, $success);
        if ($success === false) {
            return null;
        }
        return $data;
    }

    function delete($key, $option = array()) {
        return $this->mc->delete($key);
    }

    function clear() {
         return $this->mc->flush();
    }

    private function _check_driver() {
        if (function_exists("memcache_connect")) {
            return true;
        }
        return false;
    }

    private function _key_name($key) {
        return md5($this->options['hash'] . $key);
    }
    
   
}