<?php
namespace Flywheel\Controller;
use Flywheel\Factory;
use Flywheel\Object;

abstract class BaseController extends Object
{
    protected $_name;
    protected $_path;

    public function __construct($name, $path) {
        $this->_name = $name;
        $this->_path = $path;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @return \Flywheel\Http\Request
     */
    public function request() {
        return Factory::getRequest();
    }

    /**
     * @return \Flywheel\Http\Response
     */
    public function response() {
        return Factory::getResponse();
    }

    public function filter() {}
}
