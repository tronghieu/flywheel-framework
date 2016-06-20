<?php
/**
 * Created by PhpStorm.
 * User: luuhieu
 * Date: 4/25/16
 * Time: 16:09
 */

namespace Flywheel\Profiler;


use Flywheel\Event\Event;
use Flywheel\Object;
use Flywheel\Profiler\TrackingResource\BaseResource;
use Flywheel\Profiler\TrackingResource\ITrackingResource;
use Flywheel\Profiler\Writer\BaseWriter;
use Flywheel\Profiler\Writer\IWriter;

abstract class BaseProfiler extends Object implements IProfiler
{
    /**
     * System's Activity information
     *
     * @var array
     */
    protected $_systemInfo = [];

    /**
     * List event listened
     * @var array
     */
    protected $_eventsListened = [];

    /**
     * List tracking resource
     * @var ITrackingResource[]
     */
    protected $_trackingResources = [];

    /**
     * List writer
     * @var Writer\IWriter[]
     */
    protected $_writers = [];

    /**
     * Profile data
     *
     * @var array
     */
    protected $_results = [];

    /**
     * Set system activity's info
     *
     * @param $info
     * @param $data
     *
     * @author LuuHieu
     */
    public function setSysActivityInfo($info, $data)
    {
        $this->_systemInfo[$info] = $data;
    }

    /**
     * Get system activity's info
     *
     * @return array
     *
     * @author LuuHieu
     */
    public function getSystemActivityInfo()
    {
        return $this->_systemInfo;
    }

    /**
     * Get profiler's data
     * Profiler's data is array, each index have schema
     * - label: Name of info's marked
     * - resources: array of TrackingResources's info marked
     *
     * @return array
     *
     * @author LuuHieu
     */
    public function getResults() {
        return $this->_results;
    }

    /**
     * Setup profiler listen to event
     *
     * @param $event_name
     * @return mixed
     *
     * @author LuuHieu
     */
    public function listenToEvent($event_name)
    {
        $this->_eventsListened[] = $event_name;
        $this->getEventDispatcher()->addListener($event_name, [$this, 'handleEvent']);
    }

    /**
     * Handling event and mark profile's data
     *
     * @param Event $event
     *
     * @author LuuHieu
     */
    public function handleEvent(Event $event)
    {
        $this->tracking($event->getName());
    }

    /**
     * Register tracking resource
     *
     * @param ITrackingResource $trackingResource
     * @return void
     *
     * @author LuuHieu
     */
    public function registerTrackingResource(ITrackingResource $trackingResource)
    {
        if($trackingResource instanceof BaseResource) {
            $this->_trackingResources[$trackingResource->getName()] = $trackingResource;
            $trackingResource->setOwner($this);
        }
    }

    /**
     * @param IWriter $writer
     * @return void
     */
    public function registerWriter(IWriter $writer)
    {
        if ($writer instanceof BaseWriter) {
            $this->_writers[] = $writer;
            $writer->setOwner($this);
        }
    }
}