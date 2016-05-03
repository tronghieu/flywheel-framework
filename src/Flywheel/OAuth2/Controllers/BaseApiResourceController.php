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
     * Get Access Token; only token authorize code bearer is supported; we'll need implement more type in the futures
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
     * @param $failure_message
     * @return bool
     */
    public function verifyResourceRequest($scope = null, &$failure_message) {

        $non_secured_protocol_allowed = $this->getServer()->getConfig(BaseServerConfig::HTTP_ALLOWED, false);

        if ($non_secured_protocol_allowed && !$this->request()->isSecure()) {
            $failure_message = 'Request is not secured';
            $this->response()->setStatusCode(401, 'invalid request');
            return false;
        }

        $nonce_enabled = $this->getServer()->getConfig(BaseServerConfig::CHECK_NONCE, false);
        if ($nonce_enabled) {
            $nonce = $this->request()->get('nonce');
            $existed = $this->getServer()->getDataStore()->lookupNonce($nonce);

            if ($existed) {
                $this->response()->setStatusCode(401, 'invalid nonce');
                return false;
            }
        }

        $accessToken = $this->getAccessTokenData();

        if (!$accessToken) {
            $failure_message = 'Invalid access token';
            $this->response()->setStatusCode(401, 'invalid token');
            return false;
        }

        if ($scope) {
            if (!$accessToken->hasScope($scope)) {
                $this->response()->setStatusCode(401, 'invalid scope');
                return false;
            }
        }

        return true;
    }
} 