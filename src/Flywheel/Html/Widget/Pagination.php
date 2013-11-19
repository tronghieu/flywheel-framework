<?php

namespace Flywheel\Html\Widget;

use Flywheel\Controller\Widget;
use Flywheel\Factory;

class Pagination extends Widget {
    public $htmlOptions = array();
    public $pageSize;
    public $total;
    public $currentPage = 1;
    public $numPageShow	=7;
    public $showTotalPage	= false;
    public $showJumpPage	= true;
    public $showSelectPage	= false;
    public $totalPage;
    public $startPage;
    public $items = '';
    public $route = array();
    /**
     * @var \Flywheel\Router\WebRouter;
     */
    public $router;

    protected function _init() {}

    public function begin() {
        if (null == $this->viewFile) {
            $this->viewFile = 'pagination';
        }

        if (!$this->viewPath) {
            $this->viewPath = __DIR__ .'/template/';
        }

        $this->router = Factory::getRouter();

        $this->totalPage = ceil($this->total / $this->pageSize);
        if (($this->currentPage < 0) || ($this->currentPage > $this->totalPage)) {
            $this->currentPage = 1;
        }

        if ($this->currentPage > $this->numPageShow / 2) {
            $this->startPage = $this->currentPage - floor($this->numPageShow / 2);
            if(($this->totalPage - $this->startPage) < $this->numPageShow) {
                $this->startPage = $this->totalPage - $this->numPageShow + 1;
            }
        } else {
            $this->startPage = 1;
        }

        if ($this->startPage < 1) {
            $this->startPage = 1;
        }
    }

    public function createLink($page) {
        $params = array_slice($this->route, 1);
        $params['page'] = $page;
        return $this->router->createUrl($this->route[0], $params);
    }

    public function end() {
        $this->getRender()->assign('pagination', $this);
        return parent::end();
    }
}