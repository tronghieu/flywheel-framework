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
    const INVALID_REQUEST = "invalid_request";
    const INVALID_ACCESS_TOKEN = "invalid_access_token";
    const INVALID_AUTHORIZE_CODE = "invalid_authorize_code";
    const ACCESS_TOKEN_EXPIRED = "access_token_expired";
    const UNSUPPORTED_GRANT_TYPE = "unsupported_grant_type";
    const UNSUPPORTED_GRANT_TYPE_CLIENT = "unsupported_grant_type_client";
    const REDIRECT_URI_MISMATCH = "redirect_uri_mismatch";
    const EXPIRED_AUTHORIZE_CODE = "expired_authorize_code";
    const MISSING_EXPIRED_TIME = "missing_expired_time";
    const SECURED_REQUIRED = "secured_required";
    const NONCE_REQUIRED = "nonce_required";

    public static $errors = array(
        self::INVALID_CLIENT_ID => "Invalid client id",
        self::INVALID_RESPONSE_TYPE => "Invalid response type",
        self::INVALID_SCOPE => "Invalid scope",
        self::INVALID_REDIRECT_URI => "Invalid redirect uri",
        self::INVALID_REQUEST => "Invalid request",
        self::INVALID_AUTHORIZE_CODE => "Invalid authorize code",
        self::INVALID_ACCESS_TOKEN => "Invalid access token",
        self::ACCESS_TOKEN_EXPIRED => "Access token expired",
        self::UNSUPPORTED_GRANT_TYPE => "Unsupported grant type",
        self::UNSUPPORTED_GRANT_TYPE_CLIENT => "Unsupported grant type for client",
        self::REDIRECT_URI_MISMATCH => "Redirect uri mismatch",
        self::EXPIRED_AUTHORIZE_CODE => "Expired authorize code",
        self::MISSING_EXPIRED_TIME => "Storage must return authcode with a value for \"expires\"",
        self::SECURED_REQUIRED => "Secured protocol is required",
        self::NONCE_REQUIRED => "Nonce is required",
    );
}