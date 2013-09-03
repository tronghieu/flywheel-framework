<?php

namespace Flywheel\Cache;

interface IStorage {

    /**
     * Get cached data from cache by id and group
     *
     * @param $id
     * @return mixed
     */
    public function get($id);

    /**
     * Set data to cache by id and group
     * @param $id
     * @param $data
     * @param null $lifetime
     * @return array|bool
     */
    public function set($id, $data, $lifetime = null);

    /**
     * Remove a cached data entry by id and group
     *
     * @param $id
     * @return bool|\string[]
     */
    public function delete($id);

    /**
     * Force garbage collect expired cache data as items are removed only on fetch!
     */
    public function gc();
}