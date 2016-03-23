<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 4:35 PM
 */

namespace Flywheel\OAuth2\ResponseTypes;


interface IResponseType {
    public function getAuthorizeResponse($params, $user_id = null);
} 