<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:05 PM
 */

namespace Flywheel\OAuth2;


class OAuth2Exception extends \Exception {
    const INVALID_CLIENT_ID = "invalid_client_id";
    const INVALID_RESPONSE_TYPE = "invalid_response_type";
    const INVALID_SCOPE = "invalid_scope";
    const INVALID_REDIRECT_URI = "invalid_redirect_uri";

    public static $errors = array(
        self::INVALID_CLIENT_ID => "Invalid client id",
        self::INVALID_RESPONSE_TYPE => "Invalid response type",
        self::INVALID_SCOPE => "Invalid scope",
        self::INVALID_REDIRECT_URI => "Invalid redirect uri",
    );
}