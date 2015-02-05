<?php

namespace Flywheel\Session\Storage;
use Flywheel\Session\Exception;
use Flywheel\Session\ISessionHandler;

class Memcached implements ISessionHandler {
    private $_config;
    /**
     * Memcached driver
     *
     * @var \Memcached
     */
    protected $_driver;

    public function __construct($config) {
        $this->_config = $config;
    }

    /**
     * Get Memcached driver
     * @return \Memcached
     */
    public function getDriver() {
        return $this->_driver;
    }

    /**
     * Opens session
     *
     * @param string $savePath ignored
     * @param string $sessionName
     * @return bool
     * @throws Exception
     * @internal param string $sessName ignored
     */
    public function open($savePath, $sessionName) {
        if (!class_exists('Memcached')) {
            throw new Exception('Extension Memcached not found');
        }

        $this->_driver = new \Memcached();
        if (isset($this->_config['handler_config'])) {
            $servers = $this->_config['handler_config'];

            for($i = 0, $size = sizeof($servers); $i < $size; ++$i) {
                $t = explode(':', $servers[$i]);
                $this->_driver->addserver($t[0], $t[1]);
            }
        }
        else {
            $this->_driver->addserver('127.0.0.1', 11211);
        }

        $this->_driver->setOption(\Memcached::OPT_COMPRESSION, true);
        $this->_driver->setOption(\Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_JSON);
        $this->_driver->setOption(\Memcached::OPT_HASH, \Memcached::HASH_MD5);
        $this->_driver->setOption(\Memcached::OPT_PREFIX_KEY, 'SESSION');

        return true;
    }

    /**
     * Fetches session data
     *
     * @param  string $sid
     * @return string
     */
    public function read($sid) {
        $value = $this->_driver->get($sid);
        if ($value !== false) {
            if ($value['last_modified'] + (int) @$this->_config['lifetime'] > time()) {
                return $value['data'];
            }
            $this->destroy($sid);
        }

        return null;
    }

    /**
     * Write session.
     *
     * @param  string $sid Session ID
     * @param  string $data
     * @return bool
     */
    public function write($sid, $data) {
        $data = array (
            'last_modified' => time(),
            'data'	=> $data
        );

        $expirationTime = isset($this->_config['lifetime'])?
            $this->_config['lifetime']:
            0;

        $this->_driver->set($sid, $data, $expirationTime);
    }

    /**
     * Closes session
     *
     * @return bool
     */
    public function close() {
        $this->_driver->quit();
    }

    /**
     * Destroy Session Id
     *
     * @param $sid
     * @return bool|void
     */
    public function destroy($sid) {
        $this->_driver->delete($sid);
    }

    /**
     * Garbage Collection
     * @param integer $sessMaxLifeTime
     * @return bool
     */
    public function gc($sessMaxLifeTime) {
        return true;
    }
}