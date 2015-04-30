<?php
namespace Flywheel\Application;
use Flywheel\Debug\Profiler;
use Flywheel\Exception;
use Flywheel\Factory;
use Flywheel\Base;
use Flywheel\Event\Event;
use Flywheel\Config\ConfigHandler;
use Flywheel\Loader;
use \Flywheel\Exception\NotFound404;
use Flywheel\Session\Session;

class WebApp extends BaseApp
{

    /**
     * Initialize
     *
     * @return void
     */
    protected function _init() {
        parent::_init();
        ini_set('display_errors',
            (Base::ENV_DEV == Base::getEnv() || Base::ENV_TEST == Base::getEnv())
                ? 'on' : 'off');

        if (Base::getEnv() == Base::ENV_DEV)
            error_reporting(E_ALL);
        else if (Base::getEnv() == Base::ENV_TEST)
            error_reporting(E_ALL ^ E_NOTICE);

        if (ConfigHandler::has('timezone'))
            date_default_timezone_set(ConfigHandler::get('timezone'));
        else
            date_default_timezone_set(@date_default_timezone_get());
    }

    /**
     * running application
     * @return void
     */
    public function run() {
        $this->beforeRun();
        $this->getEventDispatcher()->dispatch('onBeginRequest', new Event($this));

        //Session start
        Session::getInstance()->start();
        $buffer = $this->_loadController();
        $response = Factory::getResponse();
        $response->setBody($buffer);
        $response->send();

        $this->getEventDispatcher()->dispatch('onEndRequest', new Event($this));
        $this->afterRun();
    }

    /**
     * @param bool $isRemap
     * @throws \Flywheel\Exception\NotFound404
     * @param bool $isRemap
     * @return mixed
     */
    protected function _loadController($isRemap = false) {
        /* @var \Flywheel\Router\WebRouter $router */
        $router 		= Factory::getRouter();
        $controllerName	= $router->getCamelControllerName();
        $className		= $this->getAppNamespace() ."\\Controller\\{$controllerName}";
        $controllerPath	= $router->getControllerPath();

        if (!file_exists(($file = $this->_basePath.DIRECTORY_SEPARATOR
            .'Controller'.DIRECTORY_SEPARATOR .str_replace("\\", DIRECTORY_SEPARATOR, $controllerName) .'.php'))){
            throw new NotFound404("Application: Controller \"{$controllerName}\"[{$file}] does not existed!");
        }

        /* @var \Flywheel\Controller\Web _controller */
        $this->_controller = new $className($controllerName, $router->getControllerPath());

        $this->_controller->execute($router->getAction());
        return $this->_controller->render();
    }
}
