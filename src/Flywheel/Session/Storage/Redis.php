<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/3/13
 * Time: 4:43 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Session\Storage;
use Flywheel\Session\Exception;
use Flywheel\Session\Storage\ISessionHandler;

class Redis implements ISessionHandler{
    private $_config;
    /**
     * Redis driver
     *
     * @var \Redis
     */
    protected $_driver;

    public function __construct($config) {
        $this->_config = $config;
    }

    public function getDriver() {
        if (!$this->_driver) {
            $this->_driver = new \Redis();
            if (!isset($this->_config['handler_config'])) {
                throw new Exception("Cannot found config for Redis Session Handler");
            }

            $cfg = array_merge(array(
                'host' => '127.0.0.1',
                'port' => 6379,
                'db' => 0
            ), $this->_config['handler_config']);

            $this->_driver->connect($cfg['host'], $cfg['port'], 30);
            $this->_driver->select($cfg['db']);
            if (isset($cfg['auth']) && !empty($cfg['auth'])) {
                $this->_driver->auth($cfg['auth']);
            }
        }

        return $this->_driver;
    }

    public function open($savePath, $sessionName) {
        $this->getDriver();
    }

    public function write($id, $data) {
        $lifeTime = (int) @$this->_config['lifetime'];
        $this->getDriver()->set('SESSION_' .$id, $data);
        if ($lifeTime) {
            $this->_driver->expire('SESSION_' .$id, $lifeTime);
        }
    }

    public function read($id) {
        return $this->getDriver()->get('SESSION_' .$id);
    }

    public function destroy($id) {
        $this->getDriver()->delete('SESSION_' .$id);
    }

    public function close() {
        $this->getDriver()->close();
    }

    public function gc($maxLifeTime) {}
}