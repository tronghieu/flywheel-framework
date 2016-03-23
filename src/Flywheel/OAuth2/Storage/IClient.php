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
} 