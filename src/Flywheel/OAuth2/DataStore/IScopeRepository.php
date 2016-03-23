<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/22/16
 * Time: 5:50 PM
 */
namespace Flywheel\OAuth2\DataStore;

interface IScopeRepository extends IDataStore{
    function isValidScope($scope);
}