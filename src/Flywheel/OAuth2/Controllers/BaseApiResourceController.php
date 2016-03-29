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
use Flywheel\OAuth2\OAuth2Exception;
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
     * @throws OAuth2Exception
     */
    public function getAccessTokenData()
    {
        if (!isset($this->_accessToken)) {
            $access_token = $this->get("access_token");
            if(empty($access_token)) {
                $access_token = $this->request()->getHttpHeader($this->getServer()->getConfig(BaseServerConfig::TOKEN_BEARER_KEY, 'flywheel_token_bearer'));
            }

            $accessToken = $this->getServer()->getDataStore()->getAccessToken($access_token);

            if (!$accessToken) {
                throw new OAuth2Exception(OAuth2Exception::INVALID_ACCESS_TOKEN);
            }
            else if (!$accessToken->isValidToken()) {
                throw new OAuth2Exception(OAuth2Exception::INVALID_ACCESS_TOKEN);
            }
            else if ($accessToken->isExpired()) {
                throw new OAuth2Exception(OAuth2Exception::ACCESS_TOKEN_EXPIRED);
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
     * @return bool|IAccessToken
     * @throws OAuth2Exception
     */
    public function verifyResourceRequest($scope = null, &$failure_message) {

        $non_secured_protocol_allowed = $this->getServer()->getConfig(BaseServerConfig::HTTP_ALLOWED, false);

        if (!$non_secured_protocol_allowed && !$this->request()->isSecure()) {
            $failure_message = 'Request is not secured';
            throw new OAuth2Exception(OAuth2Exception::SECURED_REQUIRED);
        }

        $nonce_enabled = $this->getServer()->getConfig(BaseServerConfig::CHECK_NONCE, false);
        if ($nonce_enabled) {
            $nonce = $this->request()->get('nonce');
            $existed = $this->getServer()->getDataStore()->lookupNonce($nonce);

            if ($existed) {
                throw new OAuth2Exception(OAuth2Exception::NONCE_REQUIRED);
            }
        }

        $accessToken = $this->getAccessTokenData();

        if (!$accessToken) {
            throw new OAuth2Exception(OAuth2Exception::INVALID_ACCESS_TOKEN);
        }

        if ($scope) {
            if (!$accessToken->hasScope($scope)) {
                throw new OAuth2Exception(OAuth2Exception::INVALID_SCOPE);
            }
        }

        return $accessToken;
    }
} 