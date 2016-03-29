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

    /**
     * Get user identity
     * @return mixed
     */
    protected abstract function getUserId();

    /**
     * Build final url from redirect_uri and params
     * @param $uri
     * @param $params
     * @return string
     */
    protected function buildUri($uri, $params)
    {
        $parse_url = parse_url($uri);
        // Add our params to the parsed uri
        if (empty($parse_url["query"])) {
            $parse_url["query"] = http_build_query($params, '', '&');
        } else {
            $parse_url["query"] .= '&' . http_build_query($params, '', '&');
        }
        // Put humpty dumpty back together
        return
            ((isset($parse_url["scheme"])) ? $parse_url["scheme"] . "://" : "")
            . ((isset($parse_url["user"])) ? $parse_url["user"]
                . ((isset($parse_url["pass"])) ? ":" . $parse_url["pass"] : "") . "@" : "")
            . ((isset($parse_url["host"])) ? $parse_url["host"] : "")
            . ((isset($parse_url["port"])) ? ":" . $parse_url["port"] : "")
            . ((isset($parse_url["path"])) ? $parse_url["path"] : "")
            . ((isset($parse_url["query"]) && !empty($parse_url['query'])) ? "?" . $parse_url["query"] : "")
            . ((isset($parse_url["fragment"])) ? "#" . $parse_url["fragment"] : "")
            ;
    }
} 