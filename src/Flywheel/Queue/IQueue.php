<?php
namespace Flywheel\Queue;
interface IQueue {
    public function push($member);

    public function pop();

    public function members();

    public function getIndex($index);

    public function prepend($member);

    public function shift($member);

    /**
     * count the number of message in a queue
     * @return integer
     */
    public function count();

    /**
     * checks the existence of a queue
     *
     * @param $member
     * @return boolean
     * @api
     */
    public function isExists($member);
}
