<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:10 PM
 */

namespace Flywheel\OAuth2\Controllers;


use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\OAuth2Exception;
use Flywheel\OAuth2\Storage\IAccessToken;

abstract class BaseTokenController extends OAuth2Controller {

    /**
     * Handle token request
     * @return IAccessToken|null
     * @throws OAuth2Exception
     */
    public function handleTokenRequest() {
        if (!$this->request()->isPostRequest()) {
            throw new OAuth2Exception(OAuth2Exception::INVALID_REQUEST);
        }

        $grant_type_code = $this->post($this->getServer()->getConfig(BaseServerConfig::GRANT_TYPE_PARAM, 'grant_type'));

        if (empty($grant_type_code)) {
            throw new OAuth2Exception(OAuth2Exception::INVALID_REQUEST);
        }

        $grant_types = $this->getServer()->getGrantTypes();

        if (!isset($grant_types[$grant_type_code])) {
            throw new OAuth2Exception(OAuth2Exception::UNSUPPORTED_GRANT_TYPE);
        }

        $grantType = $grant_types[$grant_type_code];

        if (!$grantType->validateRequest($this->request(), $this->response())) {
            return null;
        }

        $client_id = $this->post($this->getServer()->getConfig(BaseServerConfig::CLIENT_ID_PARAM, 'client_id'));
        $client = $this->getServer()->getClient($client_id);

        if (!$client->hasGrantType($grant_type_code)) {
            throw new OAuth2Exception(OAuth2Exception::UNSUPPORTED_GRANT_TYPE_CLIENT);
        }

        $requested_scope = $grantType->getScope();
        if (empty($requested_scope)) {
            $requested_scope = $this->post($this->getServer()->getConfig(BaseServerConfig::SCOPE_PARAM, 'scope'));
        }

        if (!empty($requested_scope)) {
            if (!$client->hasScopeInGrantType($grant_type_code, $requested_scope)) {
                throw new OAuth2Exception(OAuth2Exception::INVALID_SCOPE);
            }
        } else {
            $requested_scope = $client->getDefaultScope();
        }

        return $grantType->createAccessToken($client_id, $grantType->getUserId(), $requested_scope);
    }
} 