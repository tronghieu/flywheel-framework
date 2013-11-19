<?php
namespace Flywheel\Controller;


use Flywheel\Factory;
use Flywheel\Object;

abstract class Widget extends Object {

    public $viewFile;

    public $viewPath;

    public $params = array();

    /**
     * @var \Flywheel\View\Render
     */
    private $_render;

    public function __construct($render = null) {
        if (null == $render) {
            $this->_render = Factory::getView();
        } else {
            $this->_render = $render;
        }

        $this->_init();
    }

    /**
     * @return \Flywheel\View\Render
     */
    public function getRender() {
        return $this->_render;
    }

    protected function _init() {}

    /**
     * beginning widget process
     */
    public function begin() {}

    /**
     * finishing widget process
     */
    public function end() {
        return $this->render($this->params);
    }

    public function getViewFile() {
        return $this->viewFile;
    }

    public function getViewPath() {
        return $this->viewPath;
    }

    /**
     * render widget view
     *
     * @param array $params
     * @param string $viewFile path/to/viewFile
     * @internal param bool $return returning view's buffer
     * @return string
     */
    public function render($params = null, $viewFile = null) {
        if (null == $viewFile) {
            $viewFile = $this->viewFile;
        } else {
            $this->viewFile = $viewFile;
        }

        return $this->_render->render($this->viewPath .DIRECTORY_SEPARATOR .$this->viewFile, $params);
    }

    public function __get($name) {
        return (isset($this->params[$name])? $this->params[$name] : null);
    }

    public function __set($name, $value) {
        $this->params[$name] =  $value;
    }
}