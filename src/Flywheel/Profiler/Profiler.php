<?php
/**
 * Created by PhpStorm.
 * User: luuhieu
 * Date: 4/25/16
 * Time: 16:08
 */

namespace Flywheel\Profiler;
use Flywheel\Event\Event;
use Flywheel\Profiler\TrackingResource\ITrackingResource;
use Flywheel\Profiler\Writer\IWriter;

/**
 * Class Profiler
 * @package Flywheel\Profiler
 *
 */
class Profiler extends BaseProfiler
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
     * Init profiler, listen framework event
     * @return void
     *
     * @author LuuHieu
     */
    protected function _init() {
        $this->listenToEvent('onBeginRequest');
        $this->listenToEvent('onBeginWebRouterParsingUrl');
        $this->listenToEvent('onAfterWebRouterParsingUrl');
        $this->listenToEvent('onAfterWebRouterParsingUrl');
        $this->listenToEvent('onAfterInitSessionConfig');
        $this->listenToEvent('onBeginControllerExecute');
        $this->listenToEvent('onBeforeControllerRender');
        $this->listenToEvent('onAfterControllerRender');
        $this->listenToEvent('onAfterControllerExecute');
        $this->listenToEvent('afterCreateMasterConnection');
        $this->listenToEvent('afterCreateSlaveConnection');
        $this->listenToEvent('onAfterSendHttpHeader');
        $this->listenToEvent('onAfterSendContent');
        $this->listenToEvent('onEndRequest');
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
        $this->_trackingResources[$trackingResource->getName()] = $trackingResource;
        $trackingResource->setOwner($this);
    }

    /**
     * @param IWriter $writer
     * @return void
     */
    public function registerWriter(IWriter $writer)
    {
        $this->_writers[] = $writer;
        $writer->setOwner($this);
    }

    /**
     * Start profile
     *
     * @return void
     *
     * @author LuuHieu
     */
    public function on()
    {
        $this->tracking('Profiler on');
        $this->_init();
    }

    /**
     * Turnoff profile
     *
     * @return void
     *
     * @author LuuHieu
     */
    public function off()
    {
        for ($i = 0, $size = sizeof($this->_eventsListened); $i < $size; ++$i) {
            $this->getEventDispatcher()->removeListener($this->_eventsListened[$i], [$this, 'handleEvent']);
        }
    }

    /**
     * Mark system's information
     *
     * @param string $label
     * @return void
     *
     * @author LuuHieu
     */
    public function tracking($label)
    {
        $profile_data = [];
        $profile_data['label'] = $label;
        foreach($this->_trackingResources as $name => $trackingResource) {
            $profile_data['resources'][$name] = $trackingResource->mark();
        }
        $this->_results[] = $profile_data;
    }

    /**
     * Dispatch profile data
     * Call writers and write profile data
     *
     * @return void
     * @author LuuHieu
     */
    public function dispatch()
    {
        foreach($this->_writers as $writer) {
            $writer->write();
        }
    }
}