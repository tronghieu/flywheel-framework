<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:10 PM
 */

namespace Flywheel\OAuth2\Controllers;


use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\Storage\IAccessToken;

abstract class BaseTokenController extends OAuth2Controller {

    /**
     * Handle token request
     * @return IAccessToken|null
     */
    public function handleTokenRequest() {
        if (!$this->request()->isPostRequest()) {
            $this->response()->setStatusCode(405, 'Invalid request');
            $this->response()->setHeader('Allow', 'POST');
            return null;
        }

        $grant_type_code = $this->post($this->getServer()->getConfig(BaseServerConfig::GRANT_TYPE_PARAM, 'grant_type'));

        if (empty($grant_type_code)) {
            $this->response()->setStatusCode(400, 'Invalid request');
            return null;
        }

        $grant_types = $this->getServer()->getGrantTypes();

        if (!isset($grant_types[$grant_type_code])) {
            $this->response()->setStatusCode(501 , 'unsupported_grant_type');
            return null;
        }

        $grantType = $grant_types[$grant_type_code];

        if (!$grantType->validateRequest($this->request(), $this->response())) {
            return null;
        }

        $client_id = $this->post($this->getServer()->getConfig(BaseServerConfig::CLIENT_ID_PARAM, 'client_id'));
        $client = $this->getServer()->getClient($client_id);

        if (!$client->hasGrantType($grant_type_code)) {
            $this->response()->setStatusCode(400, 'Invalid request, grant type is not supported for this client');
        }

        $requested_scope = $this->post($this->getServer()->getConfig(BaseServerConfig::SCOPES_PARAM, 'scope'));

        if (!empty($requested_scope)) {
            if (!$client->hasScopeInGrantType($grant_type_code, $requested_scope)) {
                $this->response()->setStatusCode(400, 'Invalid scope');
                //TODO: specify which scope is invalid and why
            }
        } else {
            $requested_scope = $client->getDefaultScope();
        }

        return $grantType->createAccessToken($client_id, $grantType->getUserId(), $requested_scope);
    }
} 