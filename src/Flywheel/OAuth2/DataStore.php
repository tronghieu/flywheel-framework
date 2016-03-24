<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:02 PM
 */

namespace Flywheel\OAuth2;


use Flywheel\OAuth2\DataStore\IAccessTokenRepository;
use Flywheel\OAuth2\DataStore\IAuthorizeCodeRepository;
use Flywheel\OAuth2\DataStore\IClientRepository;
use Flywheel\OAuth2\DataStore\IScopeRepository;
use Flywheel\OAuth2\Storage\IAccessToken;
use Flywheel\OAuth2\Storage\IAuthorizeCode;
use Flywheel\OAuth2\Storage\IClient;

abstract class DataStore {
    /**
     * Check if user is granted for client yet
     * @param $user_id
     * @param $client_id
     * @return mixed
     */
    public abstract function isGranted($user_id, $client_id);

    /**
     * @param $client_id
     * @return boolean
     */
    public abstract function isValidClient($client_id);

    /**
     * @param $client_id
     * @return IClient
     */
    public abstract function getClientById($client_id);

    /**
     * @param $scope
     * @return bool
     */
    public abstract function isValidScope($scope);

    /**
     * @param $user_id
     * @param $client_id
     * @param $scope
     * @param $redirect_uri
     * @param null|\DateTime $expired
     * @return string
     */
    public abstract function createAuthorizeCode($user_id, $client_id, $scope, $redirect_uri, $expired = null);

    /**
     * @param $code
     * @return IAuthorizeCode
     */
    public abstract function getAuthorizationCode($code);

    /**
     * @param $client_id
     * @param $user_id
     * @param $scope
     * @return IAccessToken
     */
    public abstract function createAccessToken($client_id, $user_id, $scope);

    /**
     * @param Storage\IAuthorizeCode $code
     * @return mixed
     */
    public abstract function expireAuthorizationCode(IAuthorizeCode $code);

    /**
     * @param $token
     * @return IAccessToken
     */
    public abstract function getAccessToken($token);
} 