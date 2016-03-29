<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:09 PM
 */
namespace Flywheel\OAuth2\Controllers;

use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\Server;
use Flywheel\OAuth2\Storage\IClient;
use Flywheel\OAuth2\OAuth2Exception;

abstract class BaseAuthorizeController extends OAuth2Controller {
    /** @var Server */
    private $_server;

    /**
     * Handle authorize request and redirect to corresponding page
     * We could handle THE request because user accepted must be posted to the same url
     * and we need to know if user accepted or not
     * @param $is_authorized
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function handleAuthorizeRequest($is_authorized) {
        if (!is_bool($is_authorized)) {
            throw new \InvalidArgumentException('Argument "is_authorized" must be a boolean.  This method must know if the user has granted access to the client.');
        }

        $server = $this->getOAuthServer();
        $this->_server = $server;

        $client_id = $this->get($server->getConfig(BaseServerConfig::CLIENT_ID_PARAM, 'client_id'));
        $scopes = $this->get($server->getConfig(BaseServerConfig::SCOPE_PARAM, 'scope'));
        $redirect_uri = $this->get($server->getConfig(BaseServerConfig::REDIRECT_URI_PARAM, 'redirect_uri'));
        $response_type = $this->get($server->getConfig(BaseServerConfig::RESPONSE_TYPE_PARAM, 'response_type'));

        $response_types = $server->getResponseTypes();
        if (!isset($response_types[$response_type])) {
            $this->response()->setStatusCode(400, 'Invalid response type');
            throw new OAuth2Exception(OAuth2Exception::INVALID_RESPONSE_TYPE);
        }

        //TODO: $signature and many other validate

        if (!$server->isValidClient($client_id)) {
            $this->response()->setStatusCode(400, 'Invalid client ID');
            throw new OAuth2Exception(OAuth2Exception::INVALID_CLIENT_ID);
        }

        if (!$server->isValidScope($scopes, $client_id)) {
            $this->response()->setStatusCode(400, 'Invalid scope');
            throw new OAuth2Exception(OAuth2Exception::INVALID_SCOPE);
        }

        $client = $server->getClient($client_id);

        if (!empty($redirect_uri)) {
            if (!$server->isValidRedirectUri($client_id, $redirect_uri)) {
                $this->response()->setStatusCode(400, 'Invalid redirect uri');
                throw new OAuth2Exception(OAuth2Exception::INVALID_REDIRECT_URI);
            }
        }

        if (!$is_authorized) {
            $this->setNotAuthorizedResponse($client);
            return;
        }

        if (!$params = $this->buildAuthorizeParameters($this->request(), $this->response(), $this->getUserId())) {
            throw new \Exception('Authorize Parameters could not be built');
        }

        $authResult = $response_types[$response_type]->getAuthorizeResponse($server, $params, $this->getUserId());

        list($redirect_uri, $uri_params) = $authResult;

        if (empty($redirect_uri)) {
            $redirect_uri = $client->getAuthorizeRedirectUri();
        }

        $uri = $this->buildUri($redirect_uri, $uri_params);

        $this->redirect($uri);
        exit();
    }

    /**
     * Build authorize parameter
     * @param $request
     * @param $response
     * @param $user_id
     * @return array
     */
    protected function buildAuthorizeParameters($request, $response, $user_id)
    {
        $params = array(
            $this->_server->getConfig(BaseServerConfig::SCOPES_PARAM,'scope')
            => $this->get($this->_server->getConfig(BaseServerConfig::SCOPES_PARAM, 'scope')),
            //'state'         => '', (we will use this in later time
            $this->_server->getConfig(BaseServerConfig::CLIENT_ID_PARAM,'client_id')
            => $this->get($this->_server->getConfig(BaseServerConfig::CLIENT_ID_PARAM, 'client_id')),
            $this->_server->getConfig(BaseServerConfig::REDIRECT_URI_PARAM,'redirect_uri')
            => $this->get($this->_server->getConfig(BaseServerConfig::REDIRECT_URI_PARAM, 'redirect_uri')),
            $this->_server->getConfig(BaseServerConfig::RESPONSE_TYPE_PARAM,'response_type')
            => $this->get($this->_server->getConfig(BaseServerConfig::RESPONSE_TYPE_PARAM, 'response_type')),
        );
        return $params;
    }

    /**
     * Set response to not authorized and redirect
     * @param IClient $client
     */
    protected function setNotAuthorizedResponse(IClient $client) {
        $uri = $this->get($this->_server->getConfig(BaseServerConfig::FAILURE_REDIRECT_URI_PARAM, 'failure_redirect_uri'));

        if (empty($uri)) {
            $uri = $client->getNotAuthorizedRedirectUri();
        }

        $this->redirect($uri);
    }
}