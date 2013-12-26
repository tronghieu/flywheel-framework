<?php
namespace Flywheel\Mongodb;

class Criteria {
	public static $limit;
    public static $skip;
    public static $condition;
	public static $sort;
	const SORT_ASC=1;
	const SORT_DESC=-1;


		public function __construct($param) {
		
		if (isset($param['limit'])) $this->limit=$param['limit'];  else $this->limit=-1;
		if (isset($param['skip'])) $this->skip=$param['skip'];   else $this->skip=0;
		if (isset($param['condition'])) $this->condition=$param['condition'];   else $this->condition=array();
		if (isset($param['sort'])) $this->sort=$param['sort'];	 else $this->sort=array();
		
		}

}