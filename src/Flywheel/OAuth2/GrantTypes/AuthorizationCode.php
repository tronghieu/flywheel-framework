<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:42 PM
 */

namespace Flywheel\OAuth2\GrantTypes;

use Flywheel\OAuth2\Request;
use Flywheel\OAuth2\Responses\IResponse;
use Flywheel\OAuth2\Storage\IAccessToken;

/**
 * AuthorizationCode grant type - the default grant types and first one to implement
 * @package Flywheel\OAuth2\GrantTypes
 */
class AuthorizationCode implements IGrantType {

    /**
     * Name of this grant types to pass in parameter
     * @return mixed
     */
    public function getCode()
    {
        return "authorization_code";
    }

    /**
     * Validate if request for grant type is valid or not
     * @param Request $request
     * @param IResponse $response
     * @return boolean
     */
    public function validateRequest(Request $request, IResponse $response)
    {
        // TODO: Implement validateRequest() method.
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
     * @param IAccessToken $accessToken
     * @param $client_id
     * @param $user_id
     * @param $scope
     * @return mixed
     */
    public function createAccessToken(IAccessToken $accessToken, $client_id, $user_id, $scope)
    {
        // TODO: Implement createAccessToken() method.
    }
}