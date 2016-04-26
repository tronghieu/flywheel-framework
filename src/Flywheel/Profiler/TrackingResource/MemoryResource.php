<?php
/**
 * Created by PhpStorm.
 * User: Tester-Ali
 * Date: 26-04-2016
 * Time: 11:27 AM
 */

namespace Flywheel\Profiler\TrackingResource;


use Flywheel\Profiler\IProfiler;

class MemoryResource extends BaseResource
{
    private $_prevMemory = 0.0;
    private $_profiler;

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
        $currentMem = memory_get_usage() / 1048576;
        $mark = [
            'memory_MB' => $currentMem,
            'next_memory_MB' => $currentMem - $this->_prevMemory,
            'memory_get_usage'      => memory_get_usage(),
            'memory_get_peak_usage' => memory_get_peak_usage(),
        ];
        $this->_prevMemory = $currentMem;
        return $mark;
    }

    /**
     * Get Memory Usage
     *
     * @return float MB
     */
    public function getMemUsage() {
        $mem = sprintf('%0.3f', memory_get_usage() / 1048576 );
        return $mem;
    }

    /**
     * Return tracking resource's name
     *
     * @return mixed
     */
    public function getName()
    {
        return 'MemoryResource';
    }

}