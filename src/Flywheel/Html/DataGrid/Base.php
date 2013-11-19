<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nobita
 * Date: 4/29/13
 * Time: 4:30 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Flywheel\Html\DataGrid;

use Flywheel\Object;

abstract class Base extends Object {
    public $data;
    public $columns;

    public function __construct($data, $columns = array()) {
        $this->data;
        $this->columns = $columns;

        $this->_init();
    }

    protected function _init() {
    }
}