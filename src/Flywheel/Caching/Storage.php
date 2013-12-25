<?php

namespace Flywheel\Caching;

use Flywheel\Config\ConfigHandler;
use Flywheel\Object;

class Storage extends Object {

    protected $_lifetime = 900; //
    protected $_hash;
    protected $_group;
    protected $_key;
    protected $_path;
    protected static $_instances = array();
    var $option;
    var $storage;

    public function __construct($key, $option = array()) {
        $hash = (isset($option['hash'])) ?
                $option['hash'] : null;

        if (!$hash) {
            $hash = $config['__hash__'];
        }
        //$this->storage = $config['storage'];
        //$this->option = $config['option'];
        $this->_hash = md5($hash);
        $this->_group = (isset($option['group'])) ? $option['group'] : $key;
        $this->_key = $key;
    }

    /**
     * return IStorage
     */
    public static function factory($key = null) {
        $configs = ConfigHandler::get('caching');
        $configs = array(
            '__enable__' => true,
            '__default__' => 'widget',
            '__hash__' => '-8/RsLPePPy54BtNGBm*MqX7=vn8>j6QHJGG~49AN',
            'file' => array(
                '__path__' => '',
            ),
            'widget' => array(
                'storage' => 'Apc',
                'option' => array(
                    'group' => 'html'
                ),
            ),
            'apc' => array(
                'storage' => 'Apc',
                'option' => array(
                    'group' => 'html',
                    'timeout' => 300
                ),
            ),
            'memcache' => array(
                'storage' => 'Memcache',
                'option' => array(
                    'servers' => array('default' => array(
                            'host' => 'localhost',
                            'port' => 11211,
                            'weight' => 1,
                            'timeout' => 300
                        ))
                ),
            ),
        );

        $key = $key ? $key : $configs['__default__'];
        $option = $configs[$key];
        if (!isset(self::$_instances[$option['storage']])) {
            $class = "\\Flywheel\Caching\\Storage\\" . $option['storage'];
            self::$_instances[$key] = new $class($key, $option['option']);
        }

        return self::$_instances[$key];
    }

    /**
     * Get a cache_id string from an id/group pair
     *
     * @param $id
     * @return string
     */
    protected function _getCacheId($id) {
        $name = md5($id);
        return $this->_hash . '-cache-' . $this->_group . '-' . $name;
    }

    public function set_option($option = array()) {
        $this->option = array_merge($this->option, $option);
    }

}
