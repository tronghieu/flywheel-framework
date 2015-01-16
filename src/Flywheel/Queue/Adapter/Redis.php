<?php
namespace Flywheel\Queue\Adapter;
use Flywheel\Config\ConfigHandler;
use Flywheel\Queue\Exception;
use Flywheel\Redis\Connection;

class Redis extends BaseAdapter {
    /**
     * @var Connection;
     */
    protected $_conn;

    protected function _init() {
        try {
            if (null == $this->_conn) {
                $config = $this->_config['config'];
                $t = explode('/',$config['dsn']);
                $db = isset($t[1])? $t[1] : 0;
                $t = explode(':', $t[0]);
                $redis = new Connection();
                $redis->connect($t[0], $t[1]);
                if (isset($config['auth'])) {
                    $redis->auth($config['auth']);
                }
                $redis->select($db);
                $this->_conn = $redis;
            }
        } catch (\RedisException $re) {
            throw new Exception($re->getMessage(), $re->getCode(), $re);
        }
    }

    /**
     * @return Connection
     * @throws Exception
     */
    public function getConnection() {
        if (null == $this->_conn) {
            $this->_init();
        }
        return $this->_conn;
    }

    /**
     * {@inheritDoc}
     */
    public function prepend($member) {
        return $this->getConnection()->rPush($this->_name, $member);
    }

    /**
     * {@inheritDoc}
     */
    public function push($member) {
        return $this->getConnection()->lPush($this->_name, $member);
    }

    /**
     * {@inheritDoc}
     */
    public function pushIfNotExist($member) {
        if (!$this->isExists($member)) {
            $this->push($member);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function shift() {
        return $this->getConnection()->lPop($this->_name);
    }

    /**
     * {@inheritDoc}
     */
    public function pop() {
        return $this->getConnection()->rPop($this->_name);
    }

    /**
     * {@inheritDoc}
     */
    public function count() {
        return $this->length();
    }

    /**
     * {@inheritDoc}
     */
    public function length() {
        return $this->getConnection()->lLen($this->_name);
    }

    /**
     * {@inheritDoc}
     */
    public function members() {
        return $this->getConnection()->lRange($this->_name, 0, -1);
    }

    /**
     * {@inheritDoc}
     */
    public function getIndex($index) {
        return $this->getConnection()->lIndex($this->_name, $index);
    }

    /**
     * {@inheritDoc}
     */
    public function isExists($member) {
        $members = $this->members();
        return in_array($member, $members);
    }
}
