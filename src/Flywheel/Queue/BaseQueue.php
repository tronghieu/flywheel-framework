<?php
namespace Flywheel\Queue;
/**
 *
 */
abstract class BaseQueue implements IQueue {
    protected $_name;
    protected $_config;
    protected $_adapter;
    public function __construct($name, $config) {
        $this->_name = $name;
        $this->_config = $config;
    }

    public function getAdapter()
    {
        return $this->_adapter;
    }

    public function getConfig()
    {
        return $this->_config;
    }

    public function getName()
    {
        return $this->_name;
    }
}
