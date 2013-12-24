<?php
namespace Flywheel\Session\Storage;


interface ISessionHandler {
    /**
     * Opens session
     *
     * @param string $savePath ignored
     * @param string $sessName ignored
     * @return bool
     */
    public function open($savePath, $sessName);

    /**
     * Fetches session data
     *
     * @param  string $sid
     * @return string
     */
    public function read($sid);

    /**
     * Closes session
     *
     * @return bool
     */
    public function close();

    /**
     * Updates session.
     *
     * @param  string $sid Session ID
     * @param  string $data
     * @return bool
     */
    public function write($sid, $data);

    /**
     * Destroys session provided with ID.
     *
     * @param  string $sid
     * @return bool
     */
    public function destroy($sid);

    /**
     * Garbage collection
     *
     * @param  int $sessMaxLifeTime
     * @return bool
     */
    public function gc($sessMaxLifeTime);
}