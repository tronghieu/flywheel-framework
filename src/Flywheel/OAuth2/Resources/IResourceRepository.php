<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:11 PM
 */

namespace Flywheel\OAuth2\Resources;

/**
 * Interface IResourceRepository implement a resource object repository
 * @package Flywheel\OAuth2\Resources
 */
interface IResourceRepository {
    /**
     * Quick generate "owned by" condition for resource repository class to use
     * @return mixed
     */
    function getIsOwnedByCondition();
} 