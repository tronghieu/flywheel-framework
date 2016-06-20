<?php
/**
 * Created by PhpStorm.
 * User: luuhieu
 * Date: 4/25/16
 * Time: 16:04
 */

namespace Flywheel\Profiler\TrackingResource;


use Flywheel\Profiler\IProfiler;

interface ITrackingResource
{
    /**
     * Set TrackingResource's Profile Owner
     * Using Owner for TrackingResource access profile's data
     *
     * @param IProfiler $profiler
     * @return void
     */
    public function setOwner(IProfiler $profiler);

    /**
     * Mark profile system's information
     *
     * @return mixed
     */
    public function mark();

    /**
     * Return tracking resource's name
     *
     * @return mixed
     */
    public function getName();
}