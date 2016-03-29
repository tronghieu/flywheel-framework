<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:01 PM
 */

namespace Flywheel\OAuth2;
use Flywheel\OAuth2\DataStore\BaseServerConfig;
use Flywheel\OAuth2\GrantTypes\IGrantType;
use Flywheel\OAuth2\ResponseTypes\IResponseType;
use Flywheel\OAuth2\Storage\IClient;

/**
 * Class Server; create this object to maintain everything of OAuth2 frameworks
 * @package Flywheel\OAuth2
 */
class Server {
    private $_configHandler;
    private $_dataStore;
    private $_grantTypes;
    private $_configValues = [];
    private $_responseTypes;
    private $_clients =[];

    /**
     * Hold every config ever needed for an OAuth2 server
     * @param BaseServerConfig $config
     * @param DataStore $dataStore
     */
    public function __construct(BaseServerConfig $config, DataStore $dataStore) {
        $this->_configHandler = $config;
        $this->_dataStore = $dataStore;
    }

    /**
     * @param $key
     * @param $default
     * @return mixed
     */
    public function getConfig($key, $default) {
        if (!isset($this->_configValues[$key])) {
            $value = $this->_configHandler->get($key);
            if ($value === null) {
                $this->_configValues[$key] = $default;
            }
            $this->_configValues[$key] = $value;
        }

        return $this->_configValues[$key];
    }

    /**
     * Check if client id is valid
     * @param $client_id
     * @return bool
     */
    public function isValidClient($client_id) {
        return $this->_dataStore->isValidClient($client_id);
    }

    /**
     * Validate scope; if client_id is omitted check only for all scopes
     * @param $scope
     * @param int $client_id
     * @return bool
     */
    public function isValidScope($scope, $client_id = 0) {
        if (!$client_id) {
            return $this->_dataStore->isValidScope($scope);
        }
        $client = $this->getClient($client_id);
        return $client->hasScope($scope);
    }

    /**
     * @param $client_id
     * @param $uri
     * @return bool
     */
    public function isValidRedirectUri($client_id, $uri) {
        $client = $this->getClient($client_id);
        return $client->isValidUri($uri);
    }

    /**
     * Return array of grant types
     * @return IGrantType[]
     */
    public function getGrantTypes() {
        if (!is_array($this->_grantTypes)) {
            $this->_grantTypes = $this->_configHandler->getGrantTypes();
        }
        return $this->_grantTypes;
    }

    /**
     * @return IResponseType[]
     */
    public function getResponseTypes() {
        if (!is_array($this->_responseTypes)) {
            $this->_responseTypes = $this->_configHandler->getResponseTypes();
        }
        return $this->_responseTypes;
    }

    /**
     * @return DataStore
     */
    public function getDataStore() {
        return $this->_dataStore;
    }

    /**
     * @param $client_id
     * @return IClient
     */
    public function getClient($client_id) {
        if (!isset($this->_clients[$client_id])) {
            $client = $this->_dataStore->getClientById($client_id);
            $this->_clients[$client_id] = $client;
        }

        return $this->_clients[$client_id];
    }
} 