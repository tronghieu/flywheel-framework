<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/24/16
 * Time: 11:51 AM
 */

namespace Flywheel\OAuth2\Controllers;


use Flywheel\Controller\Api;
use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\OAuth2Exception;
use Flywheel\OAuth2\Server;
use Flywheel\OAuth2\Storage\IAccessToken;

abstract class BaseApiResourceController extends Api {
    /**
     * return Server
     * @return Server
     */
    protected abstract function getOAuthServer();

    private $_server_cache;
    protected function getServer() {
        if (!isset($this->_server_cache)) {
            $this->_server_cache = $this->getOAuthServer();
        }
        return $this->_server_cache;
    }

    private $_accessToken;
    /**
     * Get Access Token; only token authorize code bearer is supported; we'll need implement more type in the futures
     * @return IAccessToken|null
     * @throws OAuth2Exception
     */
    public function getAccessTokenData()
    {
        if (!isset($this->_accessToken)) {
            $access_token = $this->get("access_token");
            if(empty($access_token)) {
                if (defined('PHP_VERSION_ID'))
                {
                    $version = PHP_VERSION_ID;
                    if ($version >= 50400) {
                        $headers = getallheaders();
                        $access_token = $headers[$this->getServer()->getConfig(BaseServerConfig::TOKEN_BEARER_KEY, 'Flywheel-Token-Bearer')];
                    }
                }
                else {
                    $access_token = $this->request()->getHttpHeader($this->getServer()->getConfig(BaseServerConfig::TOKEN_BEARER_KEY, 'Flywheel-Token-Bearer'), '');
                }
            }

            $accessToken = $this->getServer()->getDataStore()->getAccessToken($access_token);

            if (!$accessToken) {
                throw new OAuth2Exception(OAuth2Exception::INVALID_ACCESS_TOKEN);
            }
            else if (!$accessToken->isValidToken()) {
                throw new OAuth2Exception(OAuth2Exception::INVALID_ACCESS_TOKEN);
            }
            else if ($accessToken->isExpired()) {
                throw new OAuth2Exception(OAuth2Exception::ACCESS_TOKEN_EXPIRED);
            }

            $this->_accessToken = $accessToken;
        }

        return $this->_accessToken;
    }

    /**
     * Basic verify resource quest, included but not limited to:
     * - access_token
     * - scope (if specified)
     * - resource owner
     * @param null $scope
     * @param $failure_message
     * @return bool|IAccessToken
     * @throws OAuth2Exception
     */
    public function verifyResourceRequest($scope = null, &$failure_message) {
        $non_secured_protocol_allowed = $this->getServer()->getConfig(BaseServerConfig::HTTP_ALLOWED, false);

        if (!$non_secured_protocol_allowed && !$this->request()->isSecure()) {
            $failure_message = 'Request is not secured';
            throw new OAuth2Exception(OAuth2Exception::SECURED_REQUIRED);
        }

        $accessToken = $this->getAccessTokenData();

        if (!$accessToken) {
            throw new OAuth2Exception(OAuth2Exception::INVALID_ACCESS_TOKEN);
        }

        $nonce_enabled = $this->getServer()->getConfig(BaseServerConfig::CHECK_NONCE, false);
        if ($nonce_enabled) {
            $this->_checkNonce($accessToken);
        }

        if ($scope) {
            if (!$accessToken->hasScope($scope)) {
                throw new OAuth2Exception(OAuth2Exception::INVALID_SCOPE);
            }
        }

        return $accessToken;
    }

    /**
     * Use fields params to restrict fields in return data
     * @param array $data
     * @param array $defaultFields
     * @return array
     */
    public function restrictFields(array $data, array $defaultFields = []) {
        $fields = $this->get('fields');

        if (empty($fields)) {
            $fields = $defaultFields;
        }
        else {
            $fields = explode(',',$fields);
        }

        if (empty($fields)) {
            return $data;
        }

        $data = $this->_restrictDataFields($data, $fields);

        return $data;
    }

    /**
     * Use restrict fields for an entire array of objects
     * @param array $dataArray
     */
    public function restrictFieldsForArray(array $dataArray) {
        foreach ($dataArray as $key => $value) {
            $dataArray[$key] = $this->restrictFields($value);
        }
    }

    /**
     * Support json_encode object with pretty options in the get params
     * @param $data
     * @return string
     */
    public function jsonResult($data) {
        $pretty = $this->get('pretty');

        if ($pretty) {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        return json_encode($data);
    }

    /**
     * Restrict data fields in a assoc array with prefix support for deep data
     * @param $data
     * @param $fields
     * @param string $prefix
     * @return mixed
     */
    private function _restrictDataFields($data, $fields, $prefix = '') {
        foreach ($data as $field_name => $value) {
            if (is_array($value)) {
                $data[$field_name] = $this->_restrictDataFields($value, $fields, $field_name.'.');
            }
            else if (!in_array($prefix.$field_name, $fields)) {
                unset($data[$field_name]);
            }
        }
        return $data;
    }

    /**
     * Return json data in raw post or put data using assoc array
     * @return mixed
     */
    public function getJson() {
        $args = json_decode(file_get_contents('php://input'), true);

        return $args;
    }

    /**
     * @param IAccessToken $accessToken
     * @throws \Flywheel\OAuth2\OAuth2Exception
     */
    private function _checkNonce($accessToken)
    {
        $client = $accessToken->getClient();

        if ($client->isNonceEnabled()) {
            $nonce = $this->request()->get('nonce');
            $existed = $this->getServer()->getDataStore()->lookupNonce($nonce);

            if ($existed) {
                throw new OAuth2Exception(OAuth2Exception::NONCE_REQUIRED);
            }

            $this->getServer()->getDataStore()->insertNonce($nonce);
        }
    }
}