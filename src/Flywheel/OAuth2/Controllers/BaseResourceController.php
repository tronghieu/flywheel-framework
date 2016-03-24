<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/24/16
 * Time: 11:47 AM
 */

namespace Flywheel\OAuth2\Controllers;
use Flywheel\Controller\Web;
use Flywheel\OAuth2\Server;

abstract class BaseResourceController extends Web {
    /**
     * return Server
     * @return Server
     */
    protected abstract function getOAuthServer();

    private $_server_cache;
    protected function getServer() {
        if (!isset($this->_server_cache)) {
            $this->_server_cache = $this->getOAuthServer();
        }
        return $this->_server_cache;
    }
} 