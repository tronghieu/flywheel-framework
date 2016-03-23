<?php
namespace Flywheel\OAuth2\DataStore;
use Flywheel\OAuth2\Storage\IClient;

/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/22/16
 * Time: 5:47 PM
 */

interface IClientRepository extends IDataStore{
    /**
     * @param $client_id
     * @return boolean
     */
    function isValidClient($client_id);

    /**
     * @param $client_id
     * @return IClient
     */
    function getClientById($client_id);
} 