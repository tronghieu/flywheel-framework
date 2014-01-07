<?php
namespace Flywheel\Mongodb;
use Flywheel\Object;




class Embedded extends Object{
    protected static $_embedded_name;
    //protected static $mongoId;
    protected  static $isNew;

    public  $_data=array();
    /*
    public static function read(){
        return  MongoManager::getConnection()->createQuery()->from(static::getTableName());;  //'Doc';
    }
    */
      public function __construct($data = null) { 
       // $this->setTableDefinition();
        //$this->_initDataValue();
        //$this->init();
         $this->isNew = 1;//'true';

       //  echo 'ds'. $this->isNew;
        if (!empty($data)) {  
            $this->hydrate($data);
       }
        /*
        $this->setNew($isNew);
        if (!static::$_init) {
            $this->validationRules();
            static::$_init = true;*/
        
    }
    public function hydrate($data) {
        //echo 'hydrate';  
        echo '<pre>';   
        //print_r($data) ;
        echo '</pre>';

        //print_r($data['_id']->{'$id'});

        foreach ($data as $p=>$value) {  
            if (isset($this->_schema[$p])) {   
               // $this->_modifiedCols[$p] = true;
                $this->_data[$p] = $value;  
                 $this->$p= $value;
                 //$this->fixData($value, static::$_schema[$p]);
            } else { 
                $this->$p = $value;
            }
        }
       // $this->mongoId=$data['_id']->{'$id'};
        $this->isNew=0;// 'false';


    }
    public function IsNew(){
       //  print_r($this->$isNew);
         return $this->isNew;
    }  
   


}

