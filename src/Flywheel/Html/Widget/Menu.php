<?php

namespace Flywheel\Html\Widget;

use Flywheel\Controller\Web;
use Flywheel\Controller\Widget;
use Flywheel\Factory;
use Flywheel\Html\DataGrid\Base;

class Menu extends Widget {
    public $items = array();

    public $deep = 0;
    public $hideEmptyItems = false;
    public $activateParents = true;

    protected function _init() {
    }

    public function begin() {
        $this->items = $this->_normalizeItems($this->items, $hasActiveChild);
        $this->deep = (0 <= $this->deep)? 9999 : $this->deep;
    }

    protected function _makeUrl($url) {
        if (!is_array($url)) {
            return $url;
        }

        if (0 === stripos($url[0], 'http') || '#' == $url[0]) {
            return $url[0];
        }

        if (($coll = \Flywheel\Base::getApp()->getController())
            && $coll instanceof Web) {
            return $coll->createUrl($url[0],array_splice($url,1));
        }

        return Factory::getRouter()->createUrl($url[0],array_splice($url,1));
    }

    protected function _normalizeItems($items, &$active, $level = 1) {
        foreach($items as $i=>$item) {
            if(isset($item['visible']) && !$item['visible']) {
                unset($items[$i]);
                continue;
            }

            $items[$i]['level'] = $level;

            $hasActiveChild = false;

            if(isset($item['items'])) {
                $items[$i]['items'] = $this->_normalizeItems($item['items'], $hasActiveChild, $level+1);
                if(empty($items[$i]['items']) && $this->hideEmptyItems) {
                    unset($items[$i]['items']);
                    if(!isset($item['url'])) {
                        unset($items[$i]);
                        continue;
                    }
                }
            }

            $items[$i]['url'] = $this->_makeUrl($item['url']);

            if(!isset($item['active'])) {
                if(($this->activateParents && $hasActiveChild) || $this->_isItemActive($item)){
                    $active = $items[$i]['active'] = true;
                } else {
                    $items[$i]['active'] = false;
                }
            } else if($item['active']) {
                $active = true;
            }
        }

        return array_values($items);
    }

    /**
     * @param $item
     * @return bool
     */
    protected function _isItemActive($item) {
        $router = Factory::getRouter();
        if(isset($item['url']) && is_array($item['url']) && !strcasecmp(trim($item['url'][0],'/'), $router->getRoute())) {
            unset($item['url']['#']);
            if(count($item['url'])>1) {
                foreach(array_splice($item['url'],1) as $name=>$value) {
                    if(!isset($_GET[$name]) || $_GET[$name]!=$value)
                        return false;
                }
            }

            return true;
        }

        return false;
    }
}