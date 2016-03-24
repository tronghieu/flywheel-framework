<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 4:28 PM
 */

namespace Flywheel\OAuth2\ResponseTypes;

use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\Server;

class AuthorizationCode implements IResponseType {
    /**
     * @param Server $server
     * @param $params
     * @param null $user_id
     * @return array
     */
    public function getAuthorizeResponse($server, $params, $user_id = null)
    {
        $result = array('query' => array());
        $params += array('scope' => null, 'state' => null);
        $result['query']['code'] = $this->createAuthorizationCode(
            $server, $user_id,
            $params[$server->getConfig(BaseServerConfig::CLIENT_ID_PARAM,'client_id')],
            $params[$server->getConfig(BaseServerConfig::REDIRECT_URI_PARAM,'redirect_uri')],
            $params[$server->getConfig(BaseServerConfig::SCOPES_PARAM, 'scope')]);
        /*if (isset($params['state'])) {
            $result['query']['state'] = $params['state'];
        }*/
        return array($params['redirect_uri'], $result);
    }

    /**
     * @param Server $server
     * @param $user_id
     * @param $client_id
     * @param $redirect_uri
     * @param $scope
     * @return string
     */
    public function createAuthorizationCode($server, $user_id, $client_id, $redirect_uri, $scope) {
        return $server->getDataStore()->createAuthorizeCode(
            $user_id,
            $client_id,
            $scope,
            $redirect_uri
        );
    }
}