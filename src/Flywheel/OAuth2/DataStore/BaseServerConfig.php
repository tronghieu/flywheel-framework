<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:55 PM
 */

namespace Flywheel\OAuth2\DataStore;

abstract class BaseServerConfig {
    /**
     * Get config by key; return null to use framework's default
     * @param string $key
     * @return mixed
     */
    abstract function get($key);

    /**
     * Return array of grant type like [ 'authorize_code' => new \GrantType ]
     * @return mixed
     */
    abstract function getGrantTypes();

    /**
     * Return array of response type
     * @return array
     */
    abstract function getResponseTypes();

    const CLIENT_ID_PARAM = 'client_id';
    const SCOPES_PARAM = 'scope_id';
    const SCOPE_PARAM = 'scope';
    const REDIRECT_URI_PARAM = 'redirect_uri';
    const GRANT_TYPE_PARAM = 'grant_type';
    const RESPONSE_TYPE_PARAM = 'response_type';
    const FAILURE_REDIRECT_URI_PARAM = 'failure_redirect_uri';
    const TOKEN_BEARER_KEY = 'token_bearer_key';

    const HTTP_ALLOWED = 'http_allowed';
    const CHECK_NONCE = 'check_nonce';

} 