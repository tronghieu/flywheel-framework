<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:13 PM
 */

namespace Flywheel\OAuth2\DataStore;


interface IUserCredentials {
    /**
     * return user id
     * @return mixed
     */
    function getUserId();
} 