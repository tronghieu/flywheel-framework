<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 5/3/16
 * Time: 3:50 PM
 */

namespace Flywheel\OAuth2\GrantTypes;


use Flywheel\Http\WebRequest;
use Flywheel\Http\WebResponse;
use Flywheel\OAuth2\DataStore;
use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\Storage\IAccessToken;

/**
 * Class ClientCredentials, not implemented yet, TODO: add more logic
 * @package Flywheel\OAuth2\GrantTypes
 */
class ClientCredentials implements IGrantType {

    public function __construct(DataStore $dataStore, BaseServerConfig $config) {
        $this->_dataStore = $dataStore;
        $this->_config = $config;
    }

    /**
     * Name of this grant types to pass in parameter
     * @return mixed
     */
    public function getCode()
    {
        return "client_credentials";
    }

    /**
     * Validate if request for grant type is valid or not
     * @param \Flywheel\Http\WebRequest $request
     * @param \Flywheel\Http\WebResponse $response
     * @return boolean
     */
    public function validateRequest(WebRequest $request, WebResponse $response)
    {
        if (!$request->post('username')) {
            $response->setStatusCode(400, 'invalid request');
            return false;
        }

        if (!$request->post('password')) {
            $response->setStatusCode(400, 'invalid request');
            return false;
        }


    }

    /**
     * Return client ID (which client request this grant type?)
     * @return mixed
     */
    public function getClientId()
    {
        // TODO: Implement getClientId() method.
    }

    /**
     * Return user ID (request grant type permission for which user?)
     * @return mixed
     */
    public function getUserId()
    {
        // TODO: Implement getUserId() method.
    }

    /**
     * Return scope which client request permission of user
     * @return mixed
     */
    public function getScope()
    {
        // TODO: Implement getScope() method.
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
        // TODO: Implement createAccessToken() method.
    }

    /**
     * @param \Flywheel\Http\WebRequest $request
     * @param \Flywheel\Http\WebResponse $response
     * @return \Flywheel\OAuth2\DataStore\IUserCredentials
     */
    private function getClientCredentials($request, $response) {
        if (!is_null($request->getHttpHeader('PHP_AUTH_USER')) && !is_null($request->getHttpHeader('PHP_AUTH_PW'))) {
            return array('client_id' => $request->getHttpHeader('PHP_AUTH_USER'), 'client_secret' => $request->getHttpHeader('PHP_AUTH_PW'));
        }
//        if ($this->config['allow_credentials_in_request_body']) {
//            // Using POST for HttpBasic authorization is not recommended, but is supported by specification
//            if (!is_null($request->request('client_id'))) {
//                /**
//                 * client_secret can be null if the client's password is an empty string
//                 * @see http://tools.ietf.org/html/rfc6749#section-2.3.1
//                 */
//                return array('client_id' => $request->request('client_id'), 'client_secret' => $request->request('client_secret'));
//            }
//        }
//        if ($response) {
//            $message = $this->config['allow_credentials_in_request_body'] ? ' or body' : '';
//            $response->setError(400, 'invalid_client', 'Client credentials were not found in the headers'.$message);
//        }
        return null;

        $username = $request->post('username');
        $password = $request->post('password');

        return $this->_dataStore->getUser($username, $password);
    }
}