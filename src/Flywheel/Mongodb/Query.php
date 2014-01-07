<?php
namespace Flywheel\Mongodb;
use Flywheel\Mongodb\MongoConnection;
class MongoQuery {

private $_connection = null;
//private $_from = null;
private $_from;
private $_sqlParts   = array(
        'select'  => '*',//array('*'),
        'from'    => array(),
       'limit'=> null,
        'where'   => array(),
       'skip'=>null,
       'condition'=>array(),
       'count'=>FALSE,
        'orderBy' => array()
    );

 public function __construct(MongoConnection $connection)
    {
        $this->_connection = $connection; 
         $this->_from= 'ds';
         echo 'KKKKK';
    } 

 public function execute(){
    
 $result = $this->_connection->executeQuery($this->_sqlParts);
 
 return $result;
 }
 public function create($data){
 $this->_connection->insert($this->_sqlParts['from'],$data);

 }

 public function select($select){ 
  $this->_sqlParts['select']=$select;
  
  return $this;
 }
 public function from($collection){ 
  $this->_sqlParts['from']=$collection;
  
  return $this;
 }  
  public function limit($limit){ 
  $this->_sqlParts['limit']=$limit;
  
  return $this;
 }  
public function condition($condition=array()){ 
  $this->_sqlParts['condition']=$condition;
  
  return $this;
 }
 public function andWhere($key,$value){ 
  $this->_sqlParts['where'][$key] =$value;
  
  return $this;
 }
  public function orwhere($key,$value){ 
  //$this->_sqlParts['where'][$key] =$value;
  
  return $this;
 }
 
 public function skip($skip){ 
  $this->_sqlParts['skip']=$skip;
  
  return $this;
 }
 public function orderBy($key,$value){ 
  if($value=='ASC')  $option=1;
  elseif ($value=='DESC')  $option=-1;
  else   throw new Exception("chi cho phep 'ASC'  hoac 'DESC' ");

  $this->_sqlParts['sort']=array($key=>$option); //=>($option=='DESC')?1:-1);
  
  return $this;
 }    


  public function count(){ 
  $this->_sqlParts['count']=TRUE;
  
  return $this;
 }
  
               

 

}