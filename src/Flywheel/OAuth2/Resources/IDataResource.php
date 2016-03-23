<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:10 PM
 */
namespace Flywheel\OAuth2\Resources;

use Flywheel\OAuth2\DataStore\IUserCredentials;

/**
 * Interface IDataResource implement a resource object
 * @package Flywheel\OAuth2\Resources
 */
interface IDataResource {
    /**
     * Check if resource is owned by user or not
     * @param IUserCredentials $userCredential
     * @return boolean
     */
    function isOwnedBy(IUserCredentials $userCredential);
} 