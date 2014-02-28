<?php
namespace Flywheel;
use Flywheel\Application\BaseApp;
use Flywheel\Config\ConfigHandler;
use Flywheel\Queue\Queue;
use Flywheel\Translation\Translator;
use Flywheel\Util\Inflection;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\MessageSelector;

class Factory
{
    public static $_classesList = array(
        'WebRouter' => '\Flywheel\Router\WebRouter',
        'ApiRouter' => '\Flywheel\Router\ApiRouter',
        'WebRequest' => '\Flywheel\Http\WebRequest',
        'RESTfulRequest' => '\Flywheel\Http\RESTfulRequest',
        'WebResponse' => '\Flywheel\Http\WebResponse',
        'RESTfulResponse' => '\Flywheel\Http\RESTfulResponse',
        'DocumentHtml' => '\Flywheel\Document\Html',
        'Session' => '\Flywheel\Storage\Session',
        'Cookie' => '\Flywheel\Storage\Cookie',
        'Render' => '\Flywheel\View\Render'
    );

    private static $_registry = array();

    public static function init($overwrite = array()) {
        self::$_classesList = array_merge(self::$_classesList, $overwrite);
    }

    /**
     * get response
     * @param null $name
     * @return \Flywheel\Router\WebRouter | \Flywheel\Router\ApiRouter
     */
    public static function getRouter($name = null) {
        if (!isset(self::$_registry['router'])) {
            self::$_registry['router'] = array();
        }

        if (null == $name) {
            if (Base::getApp()) {
                $name = ConfigHandler::get('app_name');
            }

            if (!$name) {
                $name = 'FOREVER_AUTUMN';
            }
        }

        if (isset(self::$_registry['router'][$name])) {
            return self::$_registry['router'][$name];
        }

        switch(Base::getApp()->getType()) {
            case BaseApp::TYPE_API:
                $class = self::$_classesList['ApiRouter'];
                break;
            default:
                $class = self::$_classesList['WebRouter'];
                break;
        }

        $router = new $class();
        $router->init();

        self::$_registry['router'][$name] = $router;

        return self::$_registry['router'][$name];
    }

    /**
     * @return \Flywheel\Http\WebRequest | \Flywheel\Http\RESTfulRequest
     */
    public static function getRequest() {
        if (isset(self::$_registry['request'])) {
            return self::$_registry['request'];
        }

        switch(Base::getApp()->getType()) {
            case BaseApp::TYPE_WEB:
                $class = self::$_classesList['WebRequest'];
                break;
            case BaseApp::TYPE_API:
                $class = self::$_classesList['RESTfulRequest'];
                break;
        }
        if (isset($class)) {
            self::$_registry['request'] = new $class();
            return self::$_registry['request'];
        }

        return false;
    }

    /**
     * get response
     * @return \Flywheel\Http\WebResponse | \Flywheel\Http\RESTfulResponse
     */
    public static function getResponse() {
        if (isset(self::$_registry['response'])) {
            return self::$_registry['response'];
        }

        switch(Base::getApp()->getType()) {
            case BaseApp::TYPE_API:
                $class = self::$_classesList['RESTfulResponse'];
                break;
            default:
                $class = self::$_classesList['WebResponse'];
                break;

        }
        self::$_registry['response'] = new $class();
        return self::$_registry['response'];
    }

/**
     * Get Document
     *
     * @param string $type. Document type default 'html'
     * @return \Flywheel\Document\Html
     */
    public static function getDocument($type = 'html') {
        if (!isset(self::$_registry['document'][$type])) {
            $class = self::$_classesList['Document'.ucfirst($type)];
            self::$_registry['document'][$type] = new $class();
        }

        return self::$_registry['document'][$type];
    }

    /**
     * Get Session
     * @deprecated Change method to \Flywheel\Session\Session::getInstance()
     *
     * @throws Exception
     * @return \Flywheel\Session\Session
     */
    public static function getSession() {
        if (!Base::getApp()) {
            throw new Exception('Factory: Session must start after the application is initialized!');
        }
        if (!isset(self::$_registry['session'])) {
            ($config = ConfigHandler::load('app.config.session', 'session', false)
                or ($config = ConfigHandler::load('global.config.session', 'session')));

            if (false == $config) {
                throw new Exception('Session: config file not found, "session.cfg.php" must be exist at globals/config or '
                    .Base::getAppPath() .' config directory');
            }

            $class = self::$_classesList['Session'];

            self::$_registry['session'] = new $class($config);
        }
        return self::$_registry['session'];
    }

    /**
     * get Cookie handler
     *
     * @return \Flywheel\Storage\Cookie
     */
    public static function getCookie() {
        if (!isset(self::$_registry['cookie'])) {
            \Flywheel\Session\Session::getInstance()->start();
            $class = self::$_classesList['Cookie'];
            self::$_registry['cookie'] = new $class(ConfigHandler::get('session', false));
        }
        return self::$_registry['cookie'];
    }

    /**
     * Get View
     *
     * @param $name null
     * @return \Flywheel\View\Render
     */
    public static function getView($name = null) {
        if (!isset(self::$_registry['view'.$name])) {
            self::$_registry['view'.$name] = new self::$_classesList['Render']();
        }

        return self::$_registry['view'.$name];
    }

    /**
     * @param $configKey
     * @return \Flywheel\Queue\Queue
     * @throws Exception
     *
     * @deprecated since 1.1 to be removed in 1.2. Use \Flywheel\Queue\Queue::factory() instead.
     */
    public static function getQueue($configKey) {
        return \Flywheel\Queue\Queue::factory($configKey);
    }

    /**
     *
     * @deprecated since 1.1 to be removed in 1.2. Use \Flywheel\Translation\Translator::getInstance() instead.
     *
     * @return null|Translator
     */
    public static function getTranslator() {
        return \Flywheel\Translation\Translator::getInstance();
    }

    /**
     * @param $class
     * @param null $params
     * @param null $render
     * @return \Flywheel\Controller\Widget
     */
    public static function getWidget($class, $params = null, $render = null) {
        $k = 'widget_' .$class;
        if (!isset(self::$_registry[$k])) {
            $class = Loader::import($class, true);
            self::$_registry[$k] = new $class($render);
        }

        if (!empty($params)) {
            foreach($params as $p => $value) {
                self::$_registry[$k]->$p = $value;
            }
        }

        return self::$_registry[$k];
    }
}
