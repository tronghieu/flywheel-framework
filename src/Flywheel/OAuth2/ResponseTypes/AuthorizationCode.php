<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 4:28 PM
 */

namespace Flywheel\OAuth2\ResponseTypes;

class AuthorizationCode implements IResponseType {
    public function getAuthorizeResponse($params, $user_id = null)
    {
        $result = array('query' => array());
        $params += array('scope' => null, 'state' => null);
        $result['query']['code'] = $this->createAuthorizationCode($params['client_id'], $user_id, $params['redirect_uri'], $params['scope']);
        /*if (isset($params['state'])) {
            $result['query']['state'] = $params['state'];
        }*/
        return array($params['redirect_uri'], $result);
    }

    public function createAuthorizationCode() {
        return 'todo'; //TODO: create AuthorizationCode
    }
}