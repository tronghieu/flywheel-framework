<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:42 PM
 */

namespace Flywheel\OAuth2\GrantTypes;

use Flywheel\Http\WebRequest;
use Flywheel\Http\WebResponse;
use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\DataStore;
use Flywheel\OAuth2\OAuth2Exception;
use Flywheel\OAuth2\Server;
use Flywheel\OAuth2\Storage\IAccessToken;
use Flywheel\OAuth2\Storage\IAuthorizeCode;

/**
 * AuthorizationCode grant type - the default grant types and first one to implement
 * @package Flywheel\OAuth2\GrantTypes
 */
class AuthorizationCode implements IGrantType {
    private $_dataStore;
    private $_config;

    /** @var IAuthorizeCode */
    private $_authCode;

    public function __construct(DataStore $dataStore, BaseServerConfig $config) {
        $this->_dataStore = $dataStore;
        $this->_config = $config;
    }

    public function getDataStore() {
        return $this->_dataStore;
    }

    public function getConfig() {
        return $this->_config;
    }

    /**
     * Name of this grant types to pass in parameter
     * @return mixed
     */
    public function getCode()
    {
        return "authorization_code";
    }

    /**
     * Return client ID (which client request this grant type?)
     * @return mixed
     */
    public function getClientId()
    {
        return $this->_authCode ? $this->_authCode->getClientId() : null;
    }

    /**
     * Return user ID (request grant type permission for which user?)
     * @return mixed
     */
    public function getUserId()
    {
        return $this->_authCode ? $this->_authCode->getUserId() : null;
    }

    /**
     * Return scope which client request permission of user
     * @return mixed
     */
    public function getScope()
    {
        return $this->_authCode ? $this->_authCode->getScope() : null;
    }

    /**
     * Create access token for client in scope of user
     * @param $client_id
     * @param $user_id
     * @param $scope
     * @return IAccessToken
     */
    public function createAccessToken($client_id, $user_id, $scope)
    {
        $token = $this->_dataStore->createAccessToken($client_id, $user_id, $scope);
        $this->_dataStore->expireAuthorizationCode($this->_authCode);

        return $token;
    }

    /**
     * Validate if request for grant type is valid or not
     * @param \Flywheel\Http\WebRequest $request
     * @param \Flywheel\Http\WebResponse $response
     * @throws \Exception
     * @return boolean
     */
    public function validateRequest(WebRequest $request, WebResponse $response)
    {
        if (!$request->post('code')) {
            throw new OAuth2Exception(OAuth2Exception::INVALID_REQUEST);
        }
        $code = $request->request('code');
        if (!$authCode = $this->_dataStore->getAuthorizationCode($code)) {
            throw new OAuth2Exception(OAuth2Exception::INVALID_REQUEST);
        }

        $redirect_uri = $authCode->getRedirectUri();
        /*
         * 4.1.3 - ensure that the "redirect_uri" parameter is present if the "redirect_uri" parameter was included in the initial authorization request
         * @uri - http://tools.ietf.org/html/rfc6749#section-4.1.3
         */
        if (!empty($redirect_uri)) {
            $requested_uri = $request->post($this->_config->get(BaseServerConfig::REDIRECT_URI_PARAM, 'redirect_uri'));
            if (empty($redirect_uri)) {
                $request->get($this->_config->get(BaseServerConfig::REDIRECT_URI_PARAM, 'redirect_uri'));
            }

            $requested_uri = urldecode($requested_uri);

            if ($requested_uri != $redirect_uri) {
                throw new OAuth2Exception(OAuth2Exception::REDIRECT_URI_MISMATCH);
            }
        }

        $expired = $authCode->getExpiredDate();

        if (!($expired instanceof \DateTime)) {
            throw new OAuth2Exception(OAuth2Exception::MISSING_EXPIRED_TIME);
        }

        if ($expired->getTimestamp() < time()) {
            throw new OAuth2Exception(OAuth2Exception::EXPIRED_AUTHORIZE_CODE);
        }

        /*if (!isset($authCode['code'])) {
            $authCode['code'] = $code; // used to expire the code after the access token is granted
        }*/
        $this->_authCode = $authCode;
        return true;
    }
}