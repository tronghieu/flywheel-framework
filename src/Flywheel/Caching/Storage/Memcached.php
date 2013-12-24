<?php
namespace Flywheel\Caching\Storage;

use Flywheel\Caching\IStorage;
use Flywheel\Caching\Storage;

class Memcached extends Storage implements IStorage {
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
    public $option;

    function _construct($option = array()) {
        if ($this->_check_driver()) {
            throw new Exception("Cannot load Memcached driver. Make sure Memcached extension has been installed on your system.");
        }

        $this->mc = new Memcached();
        $this->connect();
    }

    function connect() {
        foreach ($this->option['servers'] as $key => $server) {
            if (!$this->add_server($server)) {
                throw new Exception("Memcached: Could not connect to the server $key");
            }
        }
    }

    function add_server($server) {
        extract($server);
        return $this->mc->addServer($host, $port, $weight);
    }

    function set($key, $value = "", $lifetime = 300, $option = array()) {
        return $this->mc->set($key, $value, $lifetime);
    }

    function get($key, $option = array()) {
        $data = $this->mc->get($key);
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
        if (class_exists('Memcached')) {
            return true;
        }
        return false;
    }

}
