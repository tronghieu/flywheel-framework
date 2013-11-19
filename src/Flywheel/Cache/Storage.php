<?php
namespace Flywheel\Cache;

use Flywheel\Config\ConfigHandler;
use Flywheel\Object;

class Storage extends Object {
    protected $_lifetime = 900;//
    protected $_hash;
    protected $_group;
    protected $_key;

    protected static $_instances = array();

    public function __construct($key, $options = array()) {
        $hash = (isset($options['hash']))?
                        $options['hash'] : null;

        $config = ConfigHandler::load('global.config.cache', 'cache', true);
        if (!$hash) {
            $hash = $config['__hash__'];
        }

        $this->_hash = md5($hash);
        $this->_group = (isset($options['group']))? $options['group'] : $key;
        $this->_key = $key;
    }

    /**
     * return IStorage
     */
    public static function factory($key = null) {
        $config = ConfigHandler::load('global.config.cache', 'cache', true);
        if (!$key || !isset($config[$key])) {
            $key = $config['__default__'];
        }

        if (!isset(self::$_instances[$key])) {
            $options = $config[$key];
            $class = "\\Flywheel\Cache\\Storage\\" .$options['storage'];
            self::$_instances[$key] = new $class($key, $options);
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
}