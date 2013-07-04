<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/3/13
 * Time: 5:35 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Storage\Handler;


use Flywheel\Storage\Exception;

class Mongo implements ISessionHandler {

    private $_config;
    /**
     * Redis driver
     *
     * @var \MongoClient
     */
    protected $_driver;

    /**
     * @var \MongoCollection;
     */
    protected $_collection;

    protected $_collectionName;

    public function __construct($config) {
        $this->_config = $config;
    }

    public function getDriver() {
        if (!$this->_driver) {
            if (!isset($this->_config['handler_config'])) {
                throw new Exception("Cannot found config for Mongo Session Handler");
            }

            $cfg = $this->_config['handler_config'];
            $cfg = (isset($cfg['options']))? $cfg['options'] : array("connect" => true);

            if (class_exists('Mongo')) {
                $this->_driver = new \Mongo($cfg['dsn'], $cfg['options']);
            } else if (class_exists('MongoClient')) {
                $this->_driver = new \MongoClient($cfg['dsn'], $cfg['options']);
            }

            if (isset($cfg['options']['db'])) {
                $db = $this->_driver->selectDB($cfg['options']['db']);
            } else {
                $db = $this->_driver->selectDB('session');
            }

            $this->_collectionName = isset($cfg['collection'])? $cfg['collection'] : 'session_data';

            $this->_collection = $db->selectCollection($this->_collectionName);
        }

        return $this->_driver;
    }

    /**
     * Opens session
     *
     * @param string $savePath ignored
     * @param string $sessName ignored
     * @return bool
     */
    public function open($savePath, $sessName) {
        $this->getDriver();
    }

    /**
     * Fetches session data
     *
     * @param  string $sid
     * @return string
     */
    public function read($sid) {
        $doc = $this->_collection->findOne(array(
            '_id' => new \MongoId($sid)
        ));
        return $doc['sessionData'];
    }

    /**
     * Closes session
     *
     * @return bool
     */
    public function close() {}

    /**
     * Updates session.
     *
     * @param  string $sid Session ID
     * @param  string $data
     * @return bool
     */
    public function write($sid, $data) {
        return $this->_collection->save(array(
            '_id' => new \MongoId($sid),
            'sessionData' => $data,
            'timeStamp' => new \MongoDate()
        ));
    }

    /**
     * Destroys session provided with ID.
     *
     * @param  string $sid
     * @return bool
     */
    public function destroy($sid) {
        return $this->_collection->remove(array(
            '_id' => new \MongoId($sid)
        ));
    }

    /**
     * Garbage collection
     *
     * @param  int $sessMaxLifeTime
     * @return bool
     */
    public function gc($sessMaxLifeTime) {
        $agedTime = time() - @$this->_config['lifetime'];
        $this->_collection->remove(array(
            'timeStamp' => array('$lt' => new \MongoDate($agedTime))
        ));
    }
}