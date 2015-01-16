<?php
namespace Flywheel\Queue;
interface IQueue {
    /**
     * add member to head of queue
     * @param $member
     * @return mixed
     */
    public function prepend($member);

    /**
     * shift the first member of queue
     * @return mixed
     */
    public function shift();

    /**
     * push new member to end of queue
     * @param $member
     * @return mixed
     */
    public function push($member);

    /**
     * Push if member's not exist in queue
     * @param $member
     * @return mixed
     */
    public function pushIfNotExist($member);

    /**
     * pop the last member of queue
     * @return mixed
     */
    public function pop();

    /**
     * get all queue's members
     * @return mixed
     */
    public function members();

    /**
     * get queue's length
     * @return integer
     * @since 1.1
     */
    public function length();

    /**
     * get queue's member by index
     *
     * @param $index
     * @return mixed
     */
    public function getIndex($index);

    /**
     * checks the existence of a queue
     *
     * @param $member
     * @return boolean
     * @api
     */
    public function isExists($member);
}
