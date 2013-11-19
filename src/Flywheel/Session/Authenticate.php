<?php
/* 
 * To change this templates, choose Tools | Templates
 * and open the templates in the editor.
 */
//require_once LIBRARIES_DIR.'OAuthClient/ClientAuthen.php';
//require_once LIBRARIES_DIR.'OAuthClient/oauth.php';
/**
 * Description of Auth
 *
 * @author		Thinh Le Khac<thinhlekhac@vccorp.vn>
 * @author      Trong Hieu, Luu <tronghieu.luu@gmail.com>
 * @version		$Id: Auth.php 19571 2011-12-31 05:15:16Z hieult $
 */
namespace Flywheel\Session;
use Flywheel\Object;

abstract class Authenticate extends Object {
    const ERROR_IDENTITY_INVALID = -1;
    const ERROR_CREDENTIAL_INVALID = -2;
    const ERROR_UNKNOWN_IDENTITY = -3;

    protected $_error = array();
    protected $_identity;
    protected $_credential;

    private $_authenticated = false;

    public function __construct() {
        $this->init();
    }



    public function init() {}

    protected function _setIsAuthenticated($b) {
        $this->_authenticated = (bool) $b;
    }

    public function isAuthenticated() {
        return $this->_authenticated;
    }

    public function setCredential($credential)
    {
        $this->_credential = $credential;
    }

    public function getCredential()
    {
        return $this->_credential;
    }

    public function setIdentity($identify)
    {
        $this->_identity = $identify;
    }

    public function getIdentity()
    {
        return $this->_identity;
    }

    public function getError()
    {
        return $this->_error;
    }
}