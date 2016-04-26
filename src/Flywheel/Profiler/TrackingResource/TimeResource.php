<?php
/**
 * Created by PhpStorm.
 * User: Tester-Ali
 * Date: 25-04-2016
 * Time: 5:32 PM
 */

namespace Flywheel\Profiler\TrackingResource;


use Flywheel\Profiler\IProfiler;

class TimeResource extends BaseResource
{
    private $_start;
    private $_prevTime = 0.0;
    private $_profiler;

    public function __construct()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $this->_start = $_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            $this->_start = $_SERVER['REQUEST_TIME'];
        }
    }

    /**
     * Set TrackingResource's Profile Owner
     * Using Owner for TrackingResource access profile's data
     *
     * @param IProfiler $profiler
     * @return mixed
     */
    public function setOwner(IProfiler $profiler)
    {
        $this->_profiler = $profiler;
    }

    /**
     * Mark profile system's information
     *
     * @return mixed
     */
    public function mark()
    {
        $current = microtime(true) - $this->_start;
        $mark = array(
            'microtime' => microtime(true),
            'time' => microtime(true) - $this->_start,
            'next_time' => $current - $this->_prevTime
        );
        $this->_prevTime = $current;
        return $mark;
    }

    /**
     * Return tracking resource's name
     *
     * @return mixed
     */
    public function getName()
    {
        return __CLASS__;
    }

}