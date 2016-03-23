<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/22/16
 * Time: 5:48 PM
 */
namespace Flywheel\OAuth2\DataStore;

interface IAccessTokenRepository extends IDataStore {
    public function isGranted($user_id, $client_id);
}