<?php
namespace Flywheel;
use Flywheel\Application\BaseApp;
use Flywheel\Config\ConfigHandler;
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
     * @return \Flywheel\Router\BaseRouter
     */
    public static function getRouter() {

        if (isset(self::$_registry['router'])) {
            return self::$_registry['router'];
        }
        //echo Base::getApp()->getType();exit;
        switch(Base::getApp()->getType()) {
            case BaseApp::TYPE_API:
                $class = self::$_classesList['ApiRouter'];
                break;
            default:
                $class = self::$_classesList['WebRouter'];
                break;
        }

        self::$_registry['router'] = new $class();

        return self::$_registry['router'];
    }

    /**
     * @return \Flywheel\Http\Request
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
     * @return \Flywheel\Http\Response
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
     *
     * @throws Exception
     * @return \Flywheel\Storage\Session
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
            self::getSession(); //make s$ure that session initialized
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
     * @return \Flywheel\Queue\BaseQueue;
     * @throws Exception
     */
    public static function getQueue($configKey) {
        if (!isset(self::$_registry['queue_' .$configKey])) {
            $config = ConfigHandler::load('global.config.queue', 'queue', true);
            if (!isset($config[$configKey])) {
                throw new Exception("Could not found config match with {$configKey}. Check global/config/queue.cfg.php and add it");
            }

            $adapter = Inflection::camelize($config[$configKey]['adapter']);
            if (!$adapter) {
                throw new Exception("Adapter not found in config");
            }
            $class = "\\Flywheel\\Queue\\{$adapter}";
            $class = new $class($config[$configKey]['name'], $config[$configKey]['config']);
            self::$_registry['queue_' .$configKey] = $class;
        }

        return self::$_registry['queue_' .$configKey];
    }

    /**
     * @return null|Translator
     */
    public static function getTranslator() {
        $i18nCfg = ConfigHandler::get('i18n');
        if (!$i18nCfg['enable']) {
            return null;
        }

        if (!isset(self::$_registry['translator'])) {
            $translator = new Translator($i18nCfg['default_locale'], new MessageSelector());
            $translator->setFallbackLocale($i18nCfg['default_fallback']);
            $translator->addLoader('array', new ArrayLoader());

            //add init resource
            if (isset($i18nCfg['resource']) && is_array($i18nCfg['resource'])) {
                foreach($i18nCfg['resource'] as $locale => $files) {
                    for ($i = 0, $size = sizeof($files); $i < $size; ++$i) {
                        $translator->addResourceFromFile($files[$i], $locale);
                    }
                }
            }

            self::$_registry['translator'] = $translator;
        }

        return self::$_registry['translator'];
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
