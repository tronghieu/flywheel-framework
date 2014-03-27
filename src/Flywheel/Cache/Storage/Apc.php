<?php

namespace Flywheel\Cache\Storage;

use Flywheel\Cache\Exception;
use Flywheel\Cache\IStorage;
use Flywheel\Cache\Storage;

class Apc extends Storage implements IStorage {
    public function __construct() {
        if (!$this->isSupported()) {
            throw new Exception('Could not load APC extension. Make sure APC extension has been installed on your system.');
        }
    }


    /**
     * Get cached data from APC by id
     *
     * @param $id
     * @return mixed
     */
    public function get($id) {
        $cache_id = $this->_getCacheId($id);
        return apc_fetch($cache_id);
    }

    /**
     * Set data to APC Cache by id
     * @param $id
     * @param $data
     * @param null $lifetime
     * @return array|bool
     */
    public function set($id, $data, $lifetime = null) {
        $cache_id = $this->_getCacheId($id);
        if (!$lifetime) {
            $lifetime = $this->_lifetime;
        }
        return apc_store($cache_id, $data, $this->_lifetime);
    }

    /**
     * Remove a cached data entry by id and group
     *
     * @param $id
     * @return bool|\string[]
     */
    public function delete($id)
    {
        $cache_id = $this->_getCacheId($id);
        return apc_delete($cache_id);
    }

    /**
     * Force garbage collect expired cache data as items are removed only on fetch!
     */
    public function gc() {
        $allinfo = apc_cache_info('user');
        $keys = $allinfo['cache_list'];
        $secret = $this->_hash;

        foreach ($keys as $key) {
            if (strpos($key['info'], $secret . '-cache-')) {
                apc_fetch($key['info']);
            }
        }
    }

    /**
     * Test to see if the cache storage is available.
     */
    public static function isSupported() {
        return extension_loaded('apc') && ini_get('apc.enabled');
    }
}