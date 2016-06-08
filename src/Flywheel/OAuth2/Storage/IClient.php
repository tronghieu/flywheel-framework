<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 3:19 PM
 */
namespace Flywheel\OAuth2\Storage;

interface IClient {
    /**
     * check if client is allowed to use all scopes in $scope
     * @param $scope
     * @return bool
     */
    function hasScope($scope);

    /**
     * Check if this client is allowed to use this uri to redirect to
     * @param $uri
     * @return bool
     */
    function isValidUri($uri);

    /**
     * default url to redirect to if user authorize client access
     * @return string
     */
    function getAuthorizeRedirectUri();

    /**
     * default url to redirect to if user do not authorize client access
     * @return string
     */
    function getNotAuthorizedRedirectUri();

    /**
     * Check if this client is allowed to use this grant type or not
     * @param $grant_type_code
     * @return bool
     */
    function hasGrantType($grant_type_code);

    /**
     * @param $grant_type_code
     * @param $scope
     * @return bool
     */
    function hasScopeInGrantType($grant_type_code, $scope);

    /**
     * @return mixed
     */
    function getDefaultScope();

    /**
     * @return bool
     */
    function isNonceEnabled();
} 