<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 3/23/16
 * Time: 2:02 PM
 */

namespace Flywheel\OAuth2;


use Flywheel\OAuth2\DataStore\IAccessTokenRepository;
use Flywheel\OAuth2\DataStore\IClientRepository;
use Flywheel\OAuth2\DataStore\IScopeRepository;

abstract class DataStore {
    /**
     * @return IAccessTokenRepository
     */
    public abstract function getAccessTokenRepository();

    /**
     * @return IClientRepository
     */
    public abstract function getClientRepository();

    /**
     * @return IScopeRepository
     */
    public abstract function getScopeRepository();
} 