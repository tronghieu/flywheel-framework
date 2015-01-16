<?php
/**
 * Created by PhpStorm.
 * User: nobita
 * Date: 12/10/13
 * Time: 2:17 PM
 */

namespace Flywheel\Queue\Adapter;


abstract class BaseAdapter {
    protected $_config = array();

    protected $_name;

    public function __construct($config) {
        $this->_config = $config;
        $this->_name = $config['name']? $config['name'] : 'default';
        $this->_init();
    }

    protected function _init() {}

    public function getConfig() {
        return $this->_config;
    }

    /**
     * add member to head of queue
     * @param $member
     * @return mixed
     */
    public function prepend($member) {}

    /**
     * shift the first member of queue
     * @return mixed
     */
    public function shift() {}

    /**
     * push new member to end of queue
     * @param $member
     * @return mixed
     */
    public function push($member) {}

    /**
     * Push if member's not exist in queue
     * @param $member
     * @return mixed
     */
    public function pushIfNotExist($member) {}

    /**
     * pop the last member of queue
     * @return mixed
     */
    public function pop() {}

    /**
     * get all queue's members
     * @return mixed
     */
    public function members() {}

    /**
     * get queue's member by index
     *
     * @param $index
     * @return mixed
     */
    public function getIndex($index) {}

    /**
     * count the number of message in a queue
     * @return integer
     * @deprecated since 1.1, use @BaseApdater::length() instead
     */
    public function count() {
        return $this->len();
    }

    /**
     * get queue's length
     * @return integer
     * @since 1.1
     */
    public function length() {}

    /**
     * checks the existence of a queue
     *
     * @param $member
     * @return boolean
     * @api
     */
    public function isExists($member) {}
}