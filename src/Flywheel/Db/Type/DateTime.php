<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/15/13
 * Time: 6:07 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Db\Type;

class DateTime extends \DateTime {

    protected $_empty = false;

    public function __construct($time='now', \DateTimeZone $timezone=null) {
        if ('0000-00-00' == $time
            || '00:00:00' == $time
            || '0000-00-00 00:00:00' == $time
            || '-0001-11-30' == $time
            || '-0001-11-30 00:00:00' == $time) {
            $this->_empty = true;
        }

        /**
         * @TODO some php version run on WINDOWS OS has bug when assign $timezone null value
         * fix for stupid bug
         */
        if (null == $timezone) {
            $timezone = new \DateTimeZone(date_default_timezone_get());
        }

        parent::__construct($time, $timezone);

    }

    public function isEmpty() {
        return $this->_empty;
    }

    /**
     * @return string
     */
    public function toString() {
        if ($this->isEmpty()) {
            return '0000-00-00 00:00:00';
        }
        return $this->format('Y-m-d H:i:s');
    }

    public function __toString() {
        return $this->toString();
    }
}