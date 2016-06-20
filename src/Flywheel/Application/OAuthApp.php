<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 5/9/16
 * Time: 2:20 PM
 */

namespace Flywheel\Application;


use Flywheel\Exception;
use Flywheel\Factory;

class OAuthApp extends ApiApp {
    protected function _loadMethod() {
        /* @var \Flywheel\Router\WebRouter $router */
        $router = Factory::getRouter();
        $name = $router->getCamelControllerName();
        $apiMethod = $router->getAction();
        $class = $this->getAppNamespace() ."\\Controller\\{$name}";

        if (!file_exists(($file = $this->_basePath.DIRECTORY_SEPARATOR
            .'Controller'.DIRECTORY_SEPARATOR .str_replace("\\", DIRECTORY_SEPARATOR, $name) .'.php'))){
            throw new Exception("Api's method {$class}/{$apiMethod} not found!", 404);
        }

        $this->_controller = new $class($name, $router->getParams());
        return $this->_controller->execute($this->_requestMethod, $apiMethod);
    }
} 