<?php
namespace Flywheel\Queue;

use Flywheel\Config\ConfigHandler;
use Flywheel\Loader;
use Flywheel\Queue\Adapter\BaseAdapter;

class Queue implements IQueue {
    protected static $_instances = array();

    /**
     * @var Adapter\BaseAdapter
     */
    protected $_adapter;

    protected $_name;

    public function __construct($name, $adapter = null) {
        $this->_name = $name;
        if ($adapter) {
            $this->setAdapter($adapter);
        }
    }

    /**
     * factory queue object by config
     *
     * @param $config
     * @return Queue
     * @throws Exception
     */
    public static function factory($config) {
        if (is_string($config)) {
            $c = ConfigHandler::get('queue');
            if (!isset($c[$config])) {
                throw new Exception("Config '{$config}' not found!");
            }
            $config = $c[$config];
        }

        $name = $config['name']? $config['name'] : 'default';

        if (!isset(self::$_instances[$name])) {
            if (!$config['adapter']) {
                throw new Exception("Adapter not found in config");
            }
            $adapter = $config['adapter'];
            self::$_instances[$name] = new Queue($name, new $adapter($config));
        }

        return self::$_instances[$name];
    }

    /**
     * @param mixed $adapter
     * @param array $config
     */
    public function setAdapter($adapter, $config = array()) {
        if (is_string($adapter)) {
            Loader::import($adapter);
            $adapter = new $adapter($config);
        }

        if (is_object($adapter) && $adapter instanceof BaseAdapter) {
            $this->_adapter = $adapter;
        }
    }

    /**
     * @return Adapter\BaseAdapter
     */
    public function getAdapter() {
        return $this->_adapter;
    }

    /**
     * {@inheritDoc}
     */
    public function prepend($member) {
        return $this->getAdapter()->prepend($member);
    }

    /**
     * {@inheritdoc}
     */
    public function shift() {
        return $this->getAdapter()->shift();
    }

    /**
     * {@inheritDoc}
     */
    public function push($member) {
        return $this->getAdapter()->push($member);
    }

    /**
     * {@inheritDoc}
     */
    public function pop() {
        return $this->getAdapter()->pop();
    }

    /**
     * {@inheritDoc}
     */
    public function members() {
        return $this->getAdapter()->members();
    }

    /**
     * {@inheritDoc}
     */
    public function getIndex($index) {
        return $this->getAdapter()->getIndex($index);
    }

    /**
     * {@inheritDoc}
     */
    public function length() {
        return $this->getAdapter()->length();
    }


    /**
     * {@inheritDoc}
     */
    public function isExists($member) {
        return $this->getAdapter()->isExists($member);
    }
} 