<?php
namespace Flywheel\Event;
interface IDispatcher
{
    /**
     * Dispatches an event to all registered listeners.
     *
     * @param string $eventName The name of the event to dispatch. The name of
     *                          the event is the name of the method that is
     *                          invoked on listeners.
     * @param \Flywheel\Event\Event $event The event to pass to the event handlers/listeners.
     *                          If not supplied, an empty Event instance is created.
     *
     * @return \Flywheel\Event\Event
     *
     * @api
     */
    public function dispatch($eventName, \Flywheel\Event\Event $event = null);

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string   $eventName The event to listen on
     * @param callable $listener  The listener
     * @param integer  $priority  The higher this value, the earlier an event
     *                            listener will be triggered in the chain (defaults to 0)
     *
     * @api
     */
    public function addListener($eventName, $listener, $priority = 0);

    /**
     * Adds an event subscriber.
     *
     * The subscriber is asked for all the events he is
     * interested in and added as a listener for these events.
     *
     * @param \Flywheel\Event\ISubscriber $subscriber The subscriber.
     *
     * @return
     * @api
     */
    public function addSubscriber(ISubscriber $subscriber);

    /**
     * Removes an event listener from the specified events.
     *
     * @param string|array $eventName The event(s) to remove a listener from
     * @param callable     $listener  The listener to remove
     */
    public function removeListener($eventName, $listener);

    /**
     * Removes an event subscriber.
     *
     * @param \Flywheel\Event\ISubscriber $subscriber The subscriber
     * @return
     */
    public function removeSubscriber(ISubscriber $subscriber);

    /**
     * Gets the listeners of a specific event or all listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return array The event listeners for the specified event, or all event listeners by event name
     */
    public function getListeners($eventName = null);

    /**
     * Checks whether an event has any registered listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return Boolean true if the specified event has any listeners, false otherwise
     */
    public function hasListeners($eventName = null);
}
