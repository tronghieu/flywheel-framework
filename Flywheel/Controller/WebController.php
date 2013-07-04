<?php
namespace Flywheel\Controller;
use Flywheel\Exception\NotFound404;
use Flywheel\Factory;
use Flywheel\Base;
use Flywheel\Loader;
use Flywheel\Util\Inflection;
use Flywheel\Config\ConfigHandler;
use Flywheel\Event\Event;
use Flywheel\Document\Html;

abstract class WebController extends BaseController
{
    /**
     * Page layout
     *
     * @var $_layout string
     */
    protected $_layout = 'default';

    protected $_name;

    protected $_path;

    protected $_template = 'default';

    protected $_viewPath;
    /**
     * Render Mode
     *
     * @var string
     */
    protected $_renderMode = 'NOT_SET';

    protected $_view;

    protected $_isCustomView = false;

    protected $time;

    protected $_buffer;

    protected $_action;

    public function __construct($name, $path) {
        parent::__construct($name, $path);
        $this->time		= time();
        $this->setViewPath(ConfigHandler::get('view_path'));
        if (ConfigHandler::has('template')) {
            $this->setTemplate(ConfigHandler::get('template'));
        }
        Loader::setPathOfAlias('template', $this->_viewPath .DIRECTORY_SEPARATOR .$this->_template);
    }

    /**
     * validation ajax request
     *
     * @param bool $end
     * @param string $endMess
     * @return bool|void
     */
    public function validAjaxRequest($end = true, $endMess = 'Invalid request!') {
        $valid = $this->request()->isXmlHttpRequest();
        if ($valid) {
            return true;
        }

        if ($end) {
            Base::end($endMess);
        }
    }

    public function setViewPath($viewPath) {
        $this->_viewPath = $viewPath;
    }

    public function getViewPath() {
        return $this->_viewPath;
    }

    public function setTemplate($template) {
        if (!is_dir($this->_viewPath .DIRECTORY_SEPARATOR .$template)) {
            throw new Exception("'{$template}' not existed in {$this->_viewPath} or 'viewPath' not set");
        }

        $this->_template = $template;
    }

    /**
     * shortcut call \Flywheel\Router\WebRouter::createUrl() method
     * @see \Flywheel\Router\WebRouter::createUrl()
     * @param $route
     * @param array $params
     * @param string $ampersand
     * @return mixed
     */
    public function createUrl($route, $params = array(), $ampersand = '&') {
        return Factory::getRouter()->createUrl($route, $params, $ampersand);
    }

    /**
     * @return string
     */
    public function getTemplatePath() {
        return $this->_viewPath .DIRECTORY_SEPARATOR .$this->_template;
    }

    /**
     * @return string
     */
    public function getTemplateName() {
        return $this->_template;
    }

	/**
     * @return \Flywheel\Document\Html
     */
    public function document() {
        return Factory::getDocument('html');
    }

    /**
     * @return \Flywheel\View\Render
     */
    public function view() {
        return Factory::getView();
    }

    public function beforeExecute() {}

    /**
     * Execute
     *
     * @param $action
     * @throws \Flywheel\Exception\NotFound404
     * @return string component process result
     */
    final public function execute($action) {
        $this->getEventDispatcher()->dispatch('onBeginControllerExecute', new Event($this));
        /* @var \Flywheel\Router\WebRouter $router */
        $router = Factory::getRouter();
        $this->_action = $action;

        //set view file with action name
        $this->_view = $this->_path .$action;

        $action = 'execute' . Inflection::camelize($action);

        if (!method_exists($this, $action))
            throw new NotFound404("Controller: Action \"". $router->getController().'/'.$action ."\" doesn't exist");

        $this->beforeExecute();
        $this->filter();
        $this->_beforeRender();
        $this->_buffer = $this->$action();

        $this->_afterRender();
        $this->afterExecute();
        $this->getEventDispatcher()->dispatch('onAfterControllerExecute', new Event($this));
    }

    public function afterExecute() {}


    /**
     * forward request handler to another action
     *     rule:     - action's forwarded in same application
     *             - cannot use component define or remap
     *
     * @param string    $controllerPath path to controllers
     *                         (not include "controllers"). example: 'User/Following'
     * @param string    $action action name
     * @param array        $params
     *
     * @throws Exception
     * @throws NotFound404
     * @return string
     */
    final public function forward($controllerPath, $action, $params = array()) {
        $this->getEventDispatcher()->dispatch('onBeginForwardingRequest', new Event($this));
        $controllerPath = rtrim($controllerPath, '/');
        $controllerPath = explode('/', $controllerPath);
        $path = array();
        for ($i = 0, $size = sizeof($controllerPath); $i < $size; ++$i) {
            if ($controllerPath[$i] != null) {
                $controllerPath[$i] = preg_replace('/[^a-z0-9_]+/i', '', $controllerPath[$i]);
                $controllerPath[$i] = Inflection::camelize($controllerPath[$i]);
                if ($i !== ($size-1)) {
                    $path[] = $controllerPath[$i];
                } else {
                    $class  = $controllerPath[$i] .'Controller';
                    $name   = $controllerPath[$i];
                }
            }
        }

        $path = implode(DIRECTORY_SEPARATOR, $path);

        // replace unwanted action's characters
        $action = preg_replace('/[^a-z0-9_]+/i', '', $action);
        $action = 'execute' .$action;

        $file = Base::getAppPath().DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR .$class .'.php';
        if (!file_exists($file))
            throw new Exception("Controller: \"{$path}\" [{$file}] does not exist!");

        require_once $file;
        $controller = new $class($name);
        $controller->_path = $path .DIRECTORY_SEPARATOR;
        $controller->_view = $controller->_path .'default';
        Base::getApp()->setController($controller);

        if (!method_exists($controller, $action))
            throw new NotFound404("Action \"{$name}/{$action}\" doesn't exist");
        $buffer = $controller->$action();
        Base::getApp()->setController($controller);

        if (null !== $buffer)
            return $buffer;

        if ('COMPONENT' == $controller->_renderMode) { //Default render Component
            $buffer = $controller->renderComponent();
            Base::getApp()->setController($controller);
            return $buffer;
        }
        $this->getEventDispatcher()->dispatch('onAfterForwardingRequest', new Event($this));
    }

    protected function _beforeRender() {
        $this->getEventDispatcher()->dispatch('onBeforeControllerRender', new Event($this));
    }
    protected function _afterRender() {
        $this->getEventDispatcher()->dispatch('onAfterControllerRender', new Event($this));
    }

    /**
     * Render Partial
     *      only render a web page's partial.
     *
     * @param string
     *
     * @return string
     */
    protected function renderPartial($vars = null) {
        $this->_renderMode = 'PARTIAL';
        $view = Factory::getView();
        $viewFile = $this->getTemplatePath() .'/controllers/' .$this->_view;
        if (!$this->_isCustomView && !$view->checkViewFileExist($viewFile)) {
            $this->setView('default');
            $viewFile = $this->getTemplatePath() .'/controllers/' .$this->_view;
        }

        return $view->render($viewFile, $vars);
    }

    /**
     * Render Component
     *      render web page
     *
     * @return string
     */
    protected function renderComponent() {
        $buffer = $this->renderPartial();
        $this->_renderMode = 'COMPONENT';

        return $buffer;
    }

    /**
     * Render Text
     *
     * @param string $text
     * @return string
     */
    protected function renderText($text) {
        $this->_renderMode = 'TEXT';
        return $text;
    }

    /**
     * set no render
     * set action not render view
     *
     * @param boolean $render
     */
    protected function setNoRender($render) {
        if (true === $render) {
            $this->_renderMode = 'NO_RENDER';
        }
    }

    /**
     * Set Layout
     *      set layout templates
     *
     * @param string $layout
     */
    public function setLayout($layout) {
        $this->_layout = $layout;
    }

    /**
     * Get current layout templates
     *
     * @return mixed: string|null
     *
     */
    public function getLayout() {
        return $this->_layout;
    }

    /**
     * Set view
     *
     * @param string $view
     */
    protected function setView($view) {
        $this->_isCustomView = true;
        $this->_view = $this->_path.$view;
    }

    /**
     * set full path of view file
     *
     * @param string $view
     */
    protected function setFullPathView($view) {
        $this->_view = $view;
    }

    abstract public function executeDefault();

    /**
     * Get Render Mode
     *
     * @return string
     */
    final public function getRenderMode() {
        return $this->_renderMode;
    }

    /**
     * render action data
     * @return null|string
     */
    final public function render() {
        if ('NO_RENDER' == $this->_renderMode) {
            return null;
        }

        switch ($this->_renderMode) {
            case 'PARTIAL':
            case 'TEXT':
                return $this->_buffer;
            default:
                return $this->_renderPage($this->_buffer);
        }
    }

    /**
     * @param $buffer
     * @throws \Exception
     * @return string
     */
    protected function _renderPage($buffer) {
        //@TODO Addition document object and register it in factory
        $document = $this->document();

        //@TODO same as view
        $view = Factory::getView();
        $view->assign('controller', $buffer);

        if ($this->_layout == null) {
            $config = ConfigHandler::get('template');
            $this->_layout = (ConfigHandler::has('default_layout'))?
                ConfigHandler::get('default_layout') : 'default'; //load default layout
        }

        $content = $view->render($this->getTemplatePath() .'/' .$this->_layout);

        if ($document->getMode() == Html::MODE_ASSETS_END) {
            $content = $document->disbursement($content);
        }

        return $content;
    }

    /**
     * redirect @see \Flywheel\Http\WebRequest::redirect()
     * @param $url
     * @param int $code
     * @param bool $end
     */
    public function redirect($url, $code = 302, $end = true) {
        if ($end == true) {
            $this->afterExecute();
        }
        Factory::getRequest()->redirect($url, $code, $end);
    }
}
