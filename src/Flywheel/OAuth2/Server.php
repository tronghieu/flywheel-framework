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

/**
 * Class Server; create this object to maintain everything of OAuth2 frameworks
 * @package Flywheel\OAuth2
 */
class Server {
    private $configHandler;
    private $dataStore;
    private $grantTypes;
    private $configValues = [];
    private $responseTypes;

    /**
     * Hold every config ever needed for an OAuth2 server
     * @param BaseServerConfig $config
     * @param DataStore $dataStore
     */
    public function __construct(BaseServerConfig $config, DataStore $dataStore) {
        $this->configHandler = $config;
        $this->$dataStore = $dataStore;
    }

    /**
     * @param $key
     * @param $default
     * @return mixed
     */
    public function getConfig($key, $default) {
        if (!isset($this->configValues[$key])) {
            $value = $this->configHandler->get($key);
            if ($value === null) {
                $this->configValues[$key] = $default;
            }
            $this->configValues[$key] = $value;
        }

        return $this->configValues[$key];
    }

    /**
     * Check if client id is valid
     * @param $client_id
     * @return bool
     */
    public function isValidClient($client_id) {
        return $this->dataStore->getClientRepository()->isValidClient($client_id);
    }

    /**
     * Validate scope; if client_id is omitted check only for all scopes
     * @param $scope
     * @param int $client_id
     * @return bool
     */
    public function isValidScope($scope, $client_id = 0) {
        if (!$client_id) {
            return $this->dataStore->getScopeRepository()->isValidScope($scope);
        }
        $client = $this->dataStore->getClientRepository()->getClientById($client_id);
        return $client->hasScope($scope);
    }

    /**
     * @param $client_id
     * @param $uri
     * @return bool
     */
    public function isValidRedirectUri($client_id, $uri) {
        $client = $this->dataStore->getClientRepository()->getClientById($client_id);
        return $client->isValidUri($uri);
    }

    /**
     * Return array of grant types
     * @return IGrantType[]
     */
    public function getGrantTypes() {
        if (!is_array($this->grantTypes)) {
            $this->grantTypes = $this->configHandler->getGrantTypes();
        }
        return $this->grantTypes;
    }

    /**
     * @return IResponseType[]
     */
    public function getResponseTypes() {
        if (!is_array($this->responseTypes)) {
            $this->responseTypes = $this->configHandler->getGrantTypes();
        }
        return $this->responseTypes;
    }
} 