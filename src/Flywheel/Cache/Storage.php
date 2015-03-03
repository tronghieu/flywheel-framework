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

        $config = ConfigHandler::get('caching');

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
    public static function factory($config = null) {
        $settings = ConfigHandler::get('caching');
        if (!$settings) {
            throw new Exception('Config "caching" not found!');
        }

        if (!isset($settings[$config])) {
            $config = $settings['__default__'];
        }

        if (!isset(self::$_instances[$config])) {
            $options = $settings[$config];
            $class = "\\Flywheel\\Cache\\Storage\\" .$options['storage'];
            self::$_instances[$config] = new $class($config, $options);
        }

        return self::$_instances[$config];
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