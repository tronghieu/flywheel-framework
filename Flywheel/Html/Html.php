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
    protected $_htmlOptions = array();

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

        return self::serializeHtmlOption($htmlOptions);
    }

    public static function serializeHtmlOption($htmlOptions = null) {
        $s = '';
        if (!empty($htmlOptions)) {
            foreach ($htmlOptions as $attr => $value) {
                $s .= ' ' .$attr .'="' .$value .'"';
            }
        }

        return $s;
    }
}