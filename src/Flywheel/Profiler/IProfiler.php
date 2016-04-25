<?php
/**
 * Created by PhpStorm.
 * User: luuhieu
 * Date: 4/25/16
 * Time: 16:00
 */

namespace Flywheel\Profiler;


use Flywheel\Profiler\TrackingResource\ITrackingResource;
use Flywheel\Profiler\Writer\IWriter;

interface IProfiler
{
    /**
     * Setup profiler listen to event
     *
     * @param $event_name
     * @return void
     */
    public function listenToEvent($event_name);

    /**
     * Register tracking resource
     *
     * @param ITrackingResource $trackingResource
     * @return void
     */
    public function registerTrackingResource(ITrackingResource $trackingResource);

    /**
     * @param IWriter $writer
     * @return void
     */
    public function registerWriter(IWriter $writer);

    /**
     * Start profile
     *
     * @return void
     */
    public function on();

    /**
     * Turnoff profile
     *
     * @return void
     */
    public function off();

    /**
     * Mark system's information
     *
     * @param string $label profile label
     * @return void
     */
    public function tracking($label);

    /**
     * Dispatch profile data
     *
     * @return void
     */
    public function dispatch();
}