<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 5/10/13
 * Time: 1:44 AM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Html\Widget;


use Flywheel\Controller\Widget;
use Flywheel\Factory;
use Flywheel\Html\Html;

class Breadcrumbs extends Widget {
    public $links=array();

    public $htmlOptions = array();

    protected $_actives = array();

    protected $_inactive;

    public $activeLinkTemplate='<a href="{url}" {htmlOptions}>{label}</a>';

    public $inactiveLinkTemplate='<span>{label}</span>';

    public $separator = ' &raquo; ';

    public function begin() {
        foreach ($this->links as $label => $link) {
            if (is_string($label)) {
                $link = array_merge_recursive(array('htmlOptions' => array()), $link);
                $this->_actives[] = array('label' => $label,
                                    'url' => is_array($link['url'])?
                                        Factory::getRouter()->createUrl($link['url'][0], array_slice($link['url'], 1))
                                        : $link['url'],
                                    'htmlOptions' => $link['htmlOptions']);
            } else {
                $this->_inactive[] = $link;
            }
        }
    }

    public function end() {
        $s = array();
        for ($i = 0, $size = sizeof($this->_actives); $i < $size; ++$i) {
            $s[] = strtr($this->activeLinkTemplate, array(
                '{url}' => $this->_actives[$i]['url'],
                '{label}' => $this->_actives[$i]['label'],
                '{htmlOptions}' => Html::serializeHtmlOption($this->_actives[$i]['htmlOptions'])
            ));
        }

        for ($i = 0, $size = sizeof($this->_inactive); $i < $size; ++$i) {
            $s[] = strtr($this->inactiveLinkTemplate, array(
                '{label}' => $this->_inactive[$i]
            ));
        }

        echo implode($s, $this->separator);
    }
}