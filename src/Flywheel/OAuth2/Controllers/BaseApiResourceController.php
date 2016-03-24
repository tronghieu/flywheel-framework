<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/24/16
 * Time: 11:51 AM
 */

namespace Flywheel\OAuth2\Controllers;


use Flywheel\Controller\Api;
use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\Server;
use Flywheel\OAuth2\Storage\IAccessToken;

abstract class BaseApiResourceController extends Api {
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

    private $_accessToken;
    /**
     * Get Access Token; only token authorize code bearer is supported yet; we'll need implement more type in the futures
     * @return IAccessToken|null
     */
    public function getAccessTokenData()
    {
        if (!isset($this->_accessToken)) {
            $access_token = $this->request()->getHttpHeader($this->getServer()->getConfig(BaseServerConfig::TOKEN_BEARER_KEY, 'flywheel_token_bearer'));

            $accessToken = $this->getServer()->getDataStore()->getAccessToken($access_token);

            if (!$accessToken) {
                $this->response()->setStatusCode(401, 'invalid token');
                return false;
            }
            else if (!$accessToken->isValidToken()) {
                $this->response()->setStatusCode(401, 'invalid token');
                return false;
            }
            else if ($accessToken->isExpired()) {
                $this->response()->setStatusCode(401, 'access token is expired');
                return false;
            }

            $this->_accessToken = $accessToken;
        }

        return $this->_accessToken;
    }

    /**
     * Basic verify resource quest, included but not limited to:
     * - access_token
     * - scope (if specified)
     * - resource owner
     * @param null $scope
     * @return bool
     */
    public function verifyResourceRequest($scope = null) {
        $accessToken = $this->getAccessTokenData();

        if (!$accessToken) {
            return false;
        }

        if ($scope) {
            if (!$accessToken->hasScope($scope)) {
                return false;
            }
        }

        return false;
    }
} 