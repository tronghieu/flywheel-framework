<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:11 PM
 */

namespace Flywheel\OAuth2\Resources;
use Flywheel\OAuth2\Controllers\BaseApiResourceController;

/**
 * Interface IResourceRepository implement a resource object repository
 * @package Flywheel\OAuth2\Resources
 */
interface IResourceRepository {
    /**
     * @param BaseApiResourceController $controller
     * @return mixed
     */
    function getOwnedResources($controller);
} 