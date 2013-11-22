<?php
namespace Flywheel\Queue;
use Flywheel\Redis\Connection;

class Redis extends BaseQueue {
    /**
     * @var Connection;
     */
    protected $_adapter;

    /**
     * @return Connection
     */
    public function getAdapter() {
        if (null == $this->_adapter) {
            $t = explode('/',$this->_config['dsn']);
            $db = isset($t[1])? $t[1] : 0;
            $t = explode(':', $t[0]);
            $redis = new Connection();
            $redis->connect($t[0], $t[1]);
            if (isset($this->_config['auth'])) {
                $redis->auth($this->_config['auth']);
            }
            $redis->select($db);
            $this->_adapter = $redis;
        }
        return $this->_adapter;
    }

    public function prepend($member) {
        return $this->getAdapter()->rPush($this->_name, $member);
    }

    public function push($member) {
        return $this->getAdapter()->lPush($this->_name, $member);
    }

    public function shift($member) {
        return $this->getAdapter()->lPop($this->_name);
    }

    public function pop() {
        return $this->getAdapter()->rPop($this->_name);
    }

    public function count() {
        return $this->getAdapter()->lLen($this->_name);
    }

    public function members() {
        return $this->getAdapter()->lRange($this->_name, 0, -1);
    }

    public function getIndex($index) {
        return $this->getAdapter()->lIndex($this->_name, $index);
    }

    public function isExists($member) {
        //not support
    }
}
