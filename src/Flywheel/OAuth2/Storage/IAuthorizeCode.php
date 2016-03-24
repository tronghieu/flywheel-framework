<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/24/16
 * Time: 11:31 AM
 */

namespace Flywheel\OAuth2\Storage;


interface IAuthorizeCode {
    function getRedirectUri();

    /**
     * @return \DateTime
     */
    function getExpiredDate();

    function getScope();

    function getClientId();

    function getUserId();
} 