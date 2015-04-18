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

abstract class Web extends BaseController
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
     * @param bool $absolute
     * @param string $ampersand
     * @return mixed
     */
    public function createUrl($route, $params = array(), $ampersand = '&', $absolute = true) {
        /*if ($absolute === null)
        {
            $defaultAbsolute = ConfigHandler::get('use_absolute_url');
            if ($defaultAbsolute === null)
            {
                $defaultAbsolute = true;

            }
            $absolute = $defaultAbsolute;
        }*/
        return Factory::getRouter()->createUrl($route, $params, $ampersand, $absolute);
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
        $this->getEventDispatcher()->dispatch('onBeginControllerExecute', new Event($this, array('action' => $action)));
        $csrf_auto_protect = ConfigHandler::get('csrf_protection');
        if(null === $csrf_auto_protect || $csrf_auto_protect) {
            if (!$this->request()->validateCsrfToken()) {
                Base::end('Invalid token');
            }
        }

        /* @var \Flywheel\Router\WebRouter $router */
        $router = Factory::getRouter();
        $this->_action = $action;

        /* removed from version 1.1
         * //set view file with action name
         * $this->_view = $this->_path .$action;
         */

        $action = 'execute' . Inflection::camelize($action);

        if (!method_exists($this, $action)) {
            throw new NotFound404("Controller: Action \"". $router->getController().'/'.$action ."\" doesn't exist");
        }

        $this->beforeExecute();
        $this->filter();
        $this->_beforeRender();
        $this->view()->assign('controller', $this);
        $this->_buffer = $this->$action();

        $this->_afterRender();
        $this->afterExecute();
        // assign current controller
        $this->view()->assign('controller', $this);
        $this->getEventDispatcher()->dispatch('onAfterControllerExecute', new Event($this, array('action' => $action)));
    }

    public function afterExecute() {}

    protected function _beforeRender() {
        $this->getEventDispatcher()->dispatch('onBeforeControllerRender', new Event($this));
    }

    protected function _afterRender() {
        $this->getEventDispatcher()->dispatch('onAfterControllerRender', new Event($this));
    }

    /**
     * Render Partial only render a web page's partial.
     *
     * @param string
     * @throws Exception
     * @return string
     */
    public function renderPartial($vars = null) {
        $this->_renderMode = 'PARTIAL';
        $view = $this->view();
        $viewFile = $this->getTemplatePath() .'/Controller/' .$this->_view;
        if (!$this->_isCustomView && !$view->checkViewFileExist($viewFile)) {
            throw new Exception('Controller view file not found or not set!');
            /* removed from version 1.1
             * $this->setView('default');
             * $viewFile = $this->getTemplatePath() .'/Controller/' .$this->_view;
             */
        }
        return $view->render($viewFile, $vars);
    }

    /**
     * Render Component render web page
     * @return string
     */
    public function renderComponent() {
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
    public function renderText($text) {
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
     * Set Layout set layout templates
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
        $this->_view = $view;
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
        $view = $this->view();
        $view->assign('buffer', $buffer);

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
        $this->request()->redirect($url, $code, $end);
    }
}
