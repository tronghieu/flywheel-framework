<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:09 PM
 */

namespace Flywheel\OAuth2\Controllers;


use Flywheel\Controller\Web;
use Flywheel\OAuth2\Server;

abstract class OAuth2Controller extends Web {
    /**
     * Check if user is authenticated or not
     * @return boolean
     */
    protected abstract function isUserAuthenticated();

    /**
     * Redirect to login page
     * @return mixed
     */
    protected abstract function redirectLogin();

    /**
     * return Server
     * @return Server
     */
    protected abstract function getOAuthServer();

    /**
     * Get user identity
     * @return mixed
     */
    protected abstract function getUserId();

    public function handleError($http_code, $message){

    }
} 