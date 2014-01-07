<?php
namespace Flywheel\Mongodb;



 //$a=new hoang();

class Connection {
	
	private $dbHost = '';
	private $dbName = '';
	private $conn;
	private $db;
	private $connected = false;
	
	public function __construct($host,$port,$name) {
    	//echo '++++'.$host.$port.$name.'<br>';
		$this->dbHost = $host;
		
		$this->conn = new \MongoClient("mongodb://".$this->dbHost);//mongodb://:@localhost:27017    
		$this->db = $this->conn->$name;

		//print_r($this->conn->hoang->find());
	//	print_r($this->firstDoc('hoang', $strListField = 'a,b,c', $cond = array(), $sort = array());
		/*
		if(!$this->conn->connected) {
			cancelConnect();
		}*/
		$this->connected = $this->conn->connected;
		if($name != '' && $this->connected) {
		//	echo '----'.$name;
			//$db = $this->conn->test;  echo '<br>'.'DB la'; print_r($db);
			//$this->dbName = $name;
			$this->db = $this->conn->$name;
			$this->dbName = $name;
		}
	}
	/*
	 public function createQuery() {

        return  new MongoQuery($this);//$this->db;//->find();//
       
    }

    public function executeQuery($param){ echo 'KKOOOO';
    	$collectionName=$param['from'];
    	$limit=$param['limit'];   
    	$cond= $param['condition'];   
    	if (isset($param['sort'])) $sort=$param['sort'];
    	else $sort=array();  //echo 'SORT'.$sort;
    	$start= $param['skip'];
    	$strListField=$param['select'];
    	$result=$this->selectDocs($collectionName, $strListField , $cond,
    						 $sort, $start, $limit) ;
    	// print_r($result);
    	 //\Flywheel\Loader::import('global.model.*');

    	// $user = new Users();
    	
    	//echo '--------------------'.$collectionName;    
    	if($param['count']==TRUE) {return count($result);}
    	else { return $result;}// new hoang($result)      ;} //

    	//$a= new {$collectionName}($result);
    	//else { return 'sss';}
    }*/
    /*
	private function cancelConnect() {
		echo "Error! Cannot connect to mongodb server!";
		__destruct();
	}
	
	public function getConnectStatus() {
		return $this->connected;
	}
	
	public function getCurrentDB() {
		return $this->dbName;
	}
	
	public function getDB() {
		return $this->db;
	}
	
	public function __destruct() {
		$this->conn->close();
		unset($this->conn);
		unset($this->db);
		unset($this);
	}*/
	/*
	public function selectDB($name) {
		if ($this->connected) {
			$this->db = $this->conn->$name;
			$this->dbName = $name;
		}
	}
	*/
	// count number of documents from the provided collection
	// return int
	/*
	public function numDocs($collectionName, $cond = array()) {
		$collection = $this->db->$collectionName;
		return $collection->count($cond);
	}
	
	// select first document of the provided collection
	// return array
	public function firstDoc($collectionName, $strListField = '*', $cond = array(), $sort = array()) {
		$collection = $this->db->$collectionName;
		
		if($strListField == '*') {
			$cursor = $collection->find($cond)->sort($sort);
		} else {
			$arrListField = explode(',', $strListField);
			for($i = 0; $i < count($arrListField); $i++) {
				$fields[trim($arrListField[$i], ' ')] = 1;
			}
			print_r($fields);
			$cursor = $collection->find($cond, $fields)->sort($sort);
		}
		
		if($cursor->hasNext()) {
			$result = $cursor->getNext();
		}
		//foreach ($cursor as $key=>$doc) {
   		 //var_dump($doc);
		//}
		
		return $result; //count($cursor);//$cursor;//$result;
	}
	
	//select last document of the provided collection
	//return array
	public function lastDoc($collectionName, $strListField = '*', $cond = array(), $sort = array()) {
		$collection = $this->db->$collectionName;
		
		if($strListField == '*') {
			$cursor = $collection->find($cond)->sort($sort);
		} else {
			$fields = array();
			$arrListField = explode(',', $strListField);
			for($i = 0; $i < count($arrListField); $i++) {
				$fields[trim($arrListField[$i], ' ')] = 1;
			}
			
			$cursor = $collection->find($cond, $fields)->sort($sort);
		}
		
		while($cursor->hasNext()) {
			$result = $cursor->getNext();
		}
		
		return $result;
	}
	*/
	//select documents with take and skip commands of the provided collection
	//return 2d array
	public function selectDocs($collectionName, $strListField = '*', $cond = array(), $sort = array(), $start = 0, $limit = -1) {
		$collection = $this->db->$collectionName; //echo $collectionName
		echo '<pre>';
		//print_r($cond);
		echo '</pre>';
		//echo $limit;
		if($strListField == '*') {
			if($limit > -1) $cursor = $collection->find($cond)->sort($sort)->skip($start)->limit($limit);
			else $cursor = $collection->find($cond)->sort($sort)->skip($start);  //echo 'OK'; echo $sort;echo $start;
		} else {
			$fields = array();
			$arrListField = explode(',', $strListField);
			for($i = 0; $i < count($arrListField); $i++) {
				$fields[trim($arrListField[$i], ' ')] = 1;
			}
			
			if($limit > -1) $cursor = $collection->find($cond, $fields)->sort($sort)->skip($start)->limit($limit);
			else $cursor = $collection->find($cond, $fields)->sort($sort)->skip($start);
		}
		
		$result = array();
		while($cursor->hasNext()) {
			$result[] = $cursor->getNext();//  print_r($cursor->getNext());
		}
		
		return $result;// print_r($result);
	}
	
	//select a docs by the provided MongoId
	//return array
	public function getDocById($collectionName, $mongoId, $strListField = '*') {
		$collection = $this->db->$collectionName;
		
		if($strListField == '*') {
			$cursor = $collection->find(array("_id" => new \MongoId($mongoId)));
		} else {
			$fields = array();
			$arrListField = explode(',', $strListField);
			for($i = 0; $i < count($arrListField); $i++) {
				$fields[trim($arrListField[$i], ' ')] = 1;
			}
			
			$cursor = $collection->find(array("_id" => new \MongoId($mongoId)), $fields);
		}
		
		if($cursor->hasNext()) {
			$result = $cursor->getNext();
			return $result;
		} else {
			return false;
		}
	}
	
	//insert new document into the provided collection
	//return MongoId
	public function insert($collectionName, $docVal = array()) {
		$collection = $this->db->$collectionName;
		$content = $docVal;
		
		$result = $collection->insert($content);
		
		if(!$result)
			return false;
		else
			return $content['_id'];
	}
	
	//remove a row by MongoId
	public function removeById($collectionName, $id) {  
	//  echo "REMOVE"; echo $id;
		$collection = $this->db->$collectionName;
		
		$result = $collection->remove(array('_id' => new \MongoId($id)));
	}
	
	public function updateById($collectionName,$id,$data) {
		$collection = $this->db->$collectionName;
		 $collection->update( array( '_id' => new \MongoId($id)),
		 					  array('$set'=>$data)  
		 					  ); 
          
          
 	}
 	/*
	public static function fetch($cursor) {
		$result = array();
		while($cursor->hasNext()) {
			$result[] = $cursor->getNext();
		}
		if(count($result) > 0) {
			return $result;
		} else {
			return false;
		}
	}

	*/
}
