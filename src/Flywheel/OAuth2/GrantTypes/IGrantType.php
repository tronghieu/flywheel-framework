<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:18 PM
 */

namespace Flywheel\OAuth2\GrantTypes;

use Flywheel\Http\WebRequest;
use Flywheel\Http\WebResponse;
use Flywheel\OAuth2\Request;
use Flywheel\OAuth2\Responses\IResponse;
use Flywheel\OAuth2\Storage\IAccessToken;

/**
 * Interface IGrantType interface for all grant types
 * @package Flywheel\OAuth2\GrantTypes
 */
interface IGrantType {
    /**
     * Name of this grant types to pass in parameter
     * @return mixed
     */
    public function getCode();

    /**
     * Validate if request for grant type is valid or not
     * @param \Flywheel\Http\WebRequest $request
     * @param \Flywheel\Http\WebResponse $response
     * @return boolean
     */
    public function validateRequest(WebRequest $request, WebResponse $response);

    /**
     * Return client ID (which client request this grant type?)
     * @return mixed
     */
    public function getClientId();

    /**
     * Return user ID (request grant type permission for which user?)
     * @return mixed
     */
    public function getUserId();

    /**
     * Return scope which client request permission of user
     * @return mixed
     */
    public function getScope();

    /**
     * Create access token for client in scope of user
     * @param IAccessToken $accessToken
     * @param $client_id
     * @param $user_id
     * @param $scope
     * @return mixed
     */
    public function createAccessToken(IAccessToken $accessToken, $client_id, $user_id, $scope);
} 