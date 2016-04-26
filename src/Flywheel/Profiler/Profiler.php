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
    public function writeProfileData()
    {
        foreach($this->_writers as $writer) {
            $writer->write();
        }
    }
}