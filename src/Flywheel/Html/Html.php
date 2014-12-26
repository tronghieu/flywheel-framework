<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/25/13
 * Time: 10:06 AM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Html;

class Html {
    public $html5 = true;

    /**
     * Html id property
     * @var
     */
    protected $_htmlId;

    /**
     * Html class property
     * @var
     */
    protected $_htmlClass = [];

    /**
     * @var array
     */
    protected $_htmlOptions = array();

    /**
     * Add a class
     * @param $class
     */
    public function addClass($class) {
        $this->_htmlClass[$class] = true;
    }

    /**
     * Remove a html class
     * @param $class
     */
    public function removeClass($class) {
        unset($this->_htmlClass[$class]);
    }

    /**
     * set html id
     * @param $id
     */
    public function setId($id) {
        $this->_htmlId = $id;
    }

    /**
     * Set custom html options
     * @param $options
     */
    public function setHtmlOption($options) {
        $this->_htmlOptions = array_merge_recursive($this->_htmlOptions, $options);
    }

    /**
     * @param null $htmlOptions
     * @return string
     */
    protected function _serializeHtmlOption($htmlOptions = null) {
        if (null === $htmlOptions) {
            $htmlOptions = $this->_htmlOptions;
        }

        $class = array_keys($this->_htmlClass);
        $class = (isset($htmlOptions['class']))? $htmlOptions['class'] .' ' .implode(' ', $class) : implode(' ', $class);
        $htmlOptions['class'] = $class;

        $htmlOptions['id'] = $this->_htmlId;

        return self::serializeHtmlOption($htmlOptions);
    }

    /**
     * Serialize html options to string
     *
     * @param null $htmlOptions
     * @return string
     */
    public static function serializeHtmlOption($htmlOptions = null) {
        $s = '';
        if (!empty($htmlOptions)) {
            foreach ($htmlOptions as $attr => $value) {
                $s .= ' ' .$attr .'="' .$value .'"';
            }
        }

        return $s;
    }

    /**
     * Draw html close tag difference html5 and older version
     * @return string
     */
    protected function _singleHtmlCloseTag () {
        return ($this->html5)? '>' : '/>';
    }
}