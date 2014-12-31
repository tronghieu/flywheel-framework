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
     * @return $this
     */
    public function addClass($class) {
        $this->_htmlClass[$class] = true;
        return $this;
    }

    /**
     * Remove a html class
     * @param $class
     * @return $this
     */
    public function removeClass($class) {
        unset($this->_htmlClass[$class]);
        return $this;
    }

    /**
     * set html id
     * @param $id
     * @return $this
     */
    public function setId($id) {
        $this->_htmlId = $id;
        return $this;
    }

    /**
     * Set custom html options
     * @param $options
     * @return $this
     */
    public function setHtmlOption($options) {
        $this->_htmlOptions = array_merge_recursive($this->_htmlOptions, $options);
        return $this;
    }

    /**
     * @param null $htmlOptions
     * @return string
     */
    protected function _serializeHtmlOption($htmlOptions = null) {
        if (null === $htmlOptions) {
            $htmlOptions = $this->_htmlOptions;
        }

        $properties_options = [
            'id' => $this->_htmlId,
        ];

        $htmlOptions += $properties_options;

        $class = array_keys($this->_htmlClass);
        $class = (isset($htmlOptions['class']))? $htmlOptions['class'] .' ' .implode(' ', $class) : implode(' ', $class);
        $htmlOptions['class'] = $class;

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