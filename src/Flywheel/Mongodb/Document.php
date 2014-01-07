<?php
namespace Flywheel\Mongodb;

use Flywheel\Object;

class Document extends Object{
    protected static $_tableName;
    public  $mongoId;
    protected  static $isNew;

    public  $_data=array();
    /*
    public static function read(){
        return  MongoManager::getConnection()->createQuery()->from(static::getTableName());;  //'Doc';
    }
    */
      public function __construct($data = null) { 
         $list_embedded=$this->embeddedDocuments();
        foreach ($this->_schema  as $p => $q) {
                if (isset($list_embedded[$p]))  { 
                    $a=$list_embedded[$p];  
                 //  $contruct_data=$data[$p];
                    $this->_data[$p]= new $a() ;  
                    $this->$p= new $a() ; 
                }
                else {  //echo $data[$p];
                    $this->$p= $data[$p]; 
                     $this->_data[$p] = $data[$p]; 
                }
          }      
       // $this->setTableDefinition();
        //$this->_initDataValue();
        //$this->init();
       // print_r($this->embeddedDocuments());
         //static::_schema 
            # code...
        
         $this->isNew = 1;//'true';

         //echo 'ds'. $this->isNew;
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
       
       // print_r($data['_id']->{'$id'});
        $list_embedded=$this->embeddedDocuments();
            foreach ($this->_schema  as $p => $q) {
                if (isset($list_embedded[$p]))  {
                    $a=$list_embedded[$p];  echo $a;
                    $contruct_data=$data[$p];
                    $this->_data[$p]= $contruct_data; // new $a($contruct_data) ;  
                    $this->$p= new $a($contruct_data) ; 
                }
                else {  //echo $data[$p];
                    $this->$p= $data[$p]; 
                     $this->_data[$p] = $data[$p]; 
                }
                 # code...
                
             }    
                /*
        foreach ($data as $p=>$value) {  
            if (isset($this->_schema[$p])) {   
               // $this->_modifiedCols[$p] = true;
                
                 $this->$p= $value;
                 //$this->fixData($value, static::$_schema[$p]);
            } else { 
                $this->$p = $value;
            }*/
        
        $this->mongoId=$data['_id']->{'$id'};
        $this->isNew=0;// 'false';


    }
    public function IsNew(){
       //  print_r($this->$isNew);
         return $this->isNew;
    }  
    public static function find($criteria){
        $collectionName=static::getTableName();
        $limit=$criteria->limit;//echo $limit;//$param['limit'];   
        $cond=$criteria->condition;// $param['condition'];   
        if (isset($criteria->sort)) $sort=$criteria->sort;
        else $sort=array();  
        $start= $criteria->skip;
        $strListField='*';
        $data =Manager::getConnection()->selectDocs($collectionName, $strListField , $cond,
                             $sort, $start, $limit) ;  
     //    echo '<pre>' ;print($data);echo '</pre>' ;
          $result= array();
        foreach ($data as $key => $value) {
             $obj=new static($value); // print_r($value); echo '<pre>' ;print_r($obj);echo '</pre>' ;
              $result[]=$obj;
        }
        return $result;// $data;

    }
/*
    public static function read(){ //echo 'CALSS'. get_class($this);// $a = new hoang();  print_r($a);
        

        $data =MongoManager::getConnection()
                ->createQuery()
                ->from(static::getTableName()); 
       
        /* foreach ($data as $key => $value) {
              $obj=new static($value);   
              $result[]=$obj;
                    # code...
                }       
        print_r($result);
                //->{static::getTableName()};
                //->find();
                
             //  ->where($condition)
               // ->limit($limit);  //'Doc';
        return $data;
    }
    public static function write($data){ //echo 'CALSS'. get_class($this);// $a = new hoang();  print_r($a);
        

         MongoManager::getConnection()
                ->createQuery()
                ->from(static::getTableName())
                ->create($data)
                ; 
        
                //->{static::getTableName()};
                //->find();
                
             //  ->where($condition)
               // ->limit($limit);  //'Doc';
        return $data;
    }*/
    public  function remove(){ echo 'XXX';
         Manager::getConnection()->removeById(static::getTableName(),$this->mongoId);
    }
    public static function getTableName() {
        return static::$_tableName;
    }
    public  function save(){
       // echo '<br>'."save".'<br>';
        $data=array();
         $list_embedded=$this->embeddedDocuments();
        
        foreach ($this->_schema as $p => $q) {
          
            if (isset($list_embedded[$p]))  {// echo $p;
                
                    foreach ($this->$p->_schema as $key => $value) {
                      $data[$p][$key]= $this->$p->$key;
                     }
            }else{ 
                $data[$p] =$this->$p;
            }
        
        }
       
            if ($this->IsNew()) { 
               
                Manager::getConnection()->insert(static::getTableName(),$data);
            }else{  
                 
                $data_diffirent=array();
                foreach ($this->_schema as $p => $q) {
                    if (isset($list_embedded[$p]))  {
                            foreach ($this->$p->_schema as $key => $value) {
                                if($this->$p->$key==$this->_data[$p][$key]) $data_diffirent[$p][$key]=$this->_data[$p][$key];
                                else  $data_diffirent[$p][$key]= $this->$p->$key;
                             
                             }  
                             
                    }else{   
                            if($this->$p==$this->_data[$p])  $data_diffirent[$p]=$this->_data[$p]; //echo 'nothing';
                            else  $data_diffirent[$p] =$this->$p; 
                    }
                }
                echo '<pre>';
             //   print_r( $data_diffirent);
                 echo '</pre>';
       // static::write($data);
         Manager::getConnection()->updateById(static::getTableName(),$this->mongoId,$data_diffirent);

            }
    }

    public  function update(){
       // echo '<br>'."save".'<br>';
        $data=array();

        foreach (static::$_schema as $p => $value) {
           
           if ($this->$p==$this->_data[$p])  ;// echo 'nothing';
           else  $data[$p] =$this->$p; 
           }
            //echo '<pre>';   
            //print_r($data) ;
            //echo '</pre>';
           // static::write($data);
        Manager::getConnection()->updateById(static::getTableName(),$this->mongoId,$data);
    }



     public static function retrieveById($mongoid){
       //  echo 'br'.'get_class'.get_class($this)  ;
     $data=  Manager::getConnection()->getDocById(static::getTableName(),$mongoid)  ;
     $obj=new static($data);    
        echo '<pre>';   
        //print_r( $data) ;
        echo '</pre>';
    return $obj;    
     }
     /*
     public static function remove(){
         MongoManager::getConnection()->removeById(static::getTableName(),$this->mongoId);
     }*/


}

