<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:09 PM
 */
namespace Flywheel\OAuth2\Controllers;

use Flywheel\OAuth2\DataStore\BaseServerConfig;

abstract class BaseAuthorizeController extends OAuth2Controller {
    public function handleAuthorizeRequest() {
        if ($this->isUserAuthenticated()) {
            $this->redirectLogin();
        }

        $server = $this->getOAuthServer();

        $client_id = $this->get($server->getConfig(BaseServerConfig::CLIENT_ID_PARAM, 'client_id'));
        $scopes = $this->get($server->getConfig(BaseServerConfig::SCOPES_PARAM, 'scopes'));
        $redirect_uri = $this->get($server->getConfig(BaseServerConfig::REDIRECT_URI_PARAM, 'scopes'));
        $response_type = $this->get($server->getConfig(BaseServerConfig::RESPONSE_TYPE_PARAM, 'response_type'));

        $response_types = $server->getResponseTypes();
        if (!isset($response_types[$response_type])) {
            $this->response()->setStatusCode(400, 'Invalid response type');
        }

        //TODO: $signature and many other validate

        if (!$server->isValidClient($client_id)) {
            $this->response()->setStatusCode(400, 'Invalid client ID');
            return;
        }

        if (!$server->isValidScope($scopes, $client_id)) {
            $this->response()->setStatusCode(400, 'Invalid scope');
            return;
        }

        if (!empty($redirect_uri)) {
            if (!$server->isValidRedirectUri($client_id, $redirect_uri)) {
                $this->response()->setStatusCode(400, 'Invalid redirect uri');
                return;
            }
        }
        //TODO: If no redirect_uri is passed in the request, use client's registered one

        //TODO: the user declined access to the client's application

        if (!$params = $this->buildAuthorizeParameters($this->request(), $this->response(), $this->getUserId())) {
            return;
        }

        $authResult = $response_types[$response_type]->getAuthorizeResponse($params, $this->getUserId());
        list($redirect_uri, $uri_params) = $authResult;

        $uri = $this->buildUri($redirect_uri, $uri_params);

        $this->redirect($uri);
        return;
    }

    /*
     * We have made this protected so this class can be extended to add/modify
     * these parameters
     */
    protected function buildAuthorizeParameters($request, $response, $user_id)
    {
        // @TODO: we should be explicit with this in the future
        $params = array(
            /*'scope'         => $this->scope,
            'state'         => $this->state,
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => $this->response_type,*/
        );
        return $params;
    }

    /**
     * Build final url from redirect_uri and params
     * @param $uri
     * @param $params
     * @return string
     */
    private function buildUri($uri, $params)
    {
        $parse_url = parse_url($uri);
        // Add our params to the parsed uri
        foreach ($params as $k => $v) {
            if (isset($parse_url[$k])) {
                $parse_url[$k] .= "&" . http_build_query($v, '', '&');
            } else {
                $parse_url[$k] = http_build_query($v, '', '&');
            }
        }
        // Put humpty dumpty back together
        return
            ((isset($parse_url["scheme"])) ? $parse_url["scheme"] . "://" : "")
            . ((isset($parse_url["user"])) ? $parse_url["user"]
                . ((isset($parse_url["pass"])) ? ":" . $parse_url["pass"] : "") . "@" : "")
            . ((isset($parse_url["host"])) ? $parse_url["host"] : "")
            . ((isset($parse_url["port"])) ? ":" . $parse_url["port"] : "")
            . ((isset($parse_url["path"])) ? $parse_url["path"] : "")
            . ((isset($parse_url["query"]) && !empty($parse_url['query'])) ? "?" . $parse_url["query"] : "")
            . ((isset($parse_url["fragment"])) ? "#" . $parse_url["fragment"] : "")
            ;
    }
}