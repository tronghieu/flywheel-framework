<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:36 PM
 */

namespace Flywheel\OAuth2\Storage;

interface IAccessToken {
    /**
     * @return bool
     */
    function isExpired();

    /**
     * @return bool
     */
    function isValidToken();

    /**
     * @param $scope
     * @return bool
     */
    function hasScope($scope);
} 