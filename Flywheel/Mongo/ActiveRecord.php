<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Admin
 * Date: 1/6/13
 * Time: 11:08 AM
 * To change this template use File | Settings | File Templates.
 */
namespace Flywheel\Mongo;
use Flywheel\Mongo\MongoDB;
//use Mongo\Connection\Connection as Connection;
class ActiveRecord extends MongoDB
{
    private $collection;
    public $db;
    public $configData;
    //
    private $field = array(),$condition = array(),$order = array(),$limit = 20,$json = false;

    public function __construct(){
        parent::__construct();
        $this->db = MongoDB::getInstance();
    }

    public function field($field = null){
        $data = array();
        if(null != $field){
            if(is_array($field)){
                $data = $field;
            }else{
                if(strpos($field,'*')!==false){
                    $data = array();
                }else {
                    if(strpos($field,',')!==false){
                        $data = explode(',',$field);
                    }
                }
            }
        }
        $this->field = $data;
        return $this;
    }
    public function condition($condition = array()){
        //condition
        $this->condition = $condition;
        return $this;
    }
    public function order($order){
        $data = array();
        if(is_array($order)){
            $data = $order;
        }else{
            if(strpos($order,',')){
                $tmps = explode(',',$order);
            }else{
                $tmps = array();
            }
            foreach ($tmps as $tmp){
                $v_order = 'asc';
                if(strpos($tmp,'desc')!==false){
                    $v_order = 'desc';
                }else if(strpos($tmp,'asc')!==false){
                    $v_order = 'asc';
                }
                $f_order = trim(str_replace($v_order,'',$tmp));
                $data[$f_order] = $v_order;
            }
        }
        $this->order = $data;
        return $this;
    }
    public function limit($limit){
        $this->limit = $limit;
        return $this;
    }
    public function json($json){
        $this->json = $json;
        return $this;
    }
    /*
     * $field = array($f1,$f2)
     * @_addIndex
     */

    public function addIndex($fields = array()){
        $this->addIndex($this->collection ,$fields);
    }

    /*
     * $field = array($f1,$f2)
     * @_removeIndex
     */

    public function removeIndex($fields = array()){
        $this->removeIndex($this, $fields);
    }
    /*
     * $field = array($f1,$f2)
     * @findOne...
     */
    public function findOne($field, $condition = array())
    {
        if(is_string($field) === true){
            $field = explode(',',$field);
        }
        //
        $this->db->select($field);
        $data = $this->db
                    ->where($condition)
                    ->get($this->collection);
        if(!empty($data[0])){
            return $data[0];
        }
        return false;
    }

    /**
     * @param $field
     * @param $mongoIDString
     * @return bool
     */
    public function findOneByID($field, $mongoIDString)
    {

        if(is_string($field) === true){
            $field = explode(',',$field);
        }
        //
        $mongoID = new \MongoId($mongoIDString);

        $this->field($field);
        $this->condition(array("_id" => $mongoID));

        $data = $this->db
                    ->where($this->condition)
                    ->get($this->collection);
        if(!empty($data[0])){
            return $data[0];
        }
        return false;
    }

    /**
     * @param array $params
     */
    public function find()
    {
        if(!empty($this->order)){
            foreach ($this->order as $key => $value){
                $this->db->orderBy($key , $value);
            }
        }
        $data = $this->db
                ->select($this->field)
                ->where($this->condition)
                ->limit($this->limit)
                ->get($this->collection);
        return $this->json ? json_encode($data) : $data;
    }

    /**
     * @param array $document
     * @return bool
     */
    public function insert($document = array())
    {
        return $this->db->insert($this->collection, $document);
    }

    /**
     * @param string $mongoIDString
     * @param array $document
     * @return bool
     */
    public function update($mongoIDString, $document = array())
    {
        $mongoID = new \MongoId($mongoIDString);
        $this->db->where(array("_id" => $mongoID));
        return $this->db->update($this->collection, $document);
    }

    /**
     * @param string $mongoIDString
     */
    public function delete($mongoIDString)
    {
        $data['_id'] = new \MongoId($mongoIDString);
        $this->condition($data);
        return $this->db->delete($this->collection , $data);
    }
    /**
     * @param string $mongoIDString
     */
    public function deleteConditions()
    {
        return $this->db
                ->where($this->condition)
                ->delete($this->collection);
    }

    /**
     * Delete all data from a collection
     */
    public function deleteAll()
    {
        return $this->db->delete_all($this->collection);
    }

    /**
     * Count
     * @param array $field
     * @param array $condition
     * @return int
     */
    public function count()
    {
        return $this->db
                ->select($this->field)
                ->where($this->condition)
                ->count($this->collection);
    }
    /**
     * Count
     * @param array $field
     * @param array $condition
     * @return int
     */
    public function collection($collection){
        $this->collection = $collection;
        return $this;
    }

    /**
    Code sample and exceptions received:

    $mongo_url['connection'] = array('mongodb1', 'mongodb2', 'mongodb3');
    $mongo_url[‘database’] = ‘t90’;

    try {
    $mongo_conn = new Mongo('mongodb://' . join(',',
    $mongo_url['connection']), array('replicaSet' => TRUE, 'persist' =>
    'conn_x'));
    }
    catch (MongoConnectionException $e) {
    // if we can't connect through to master, at least try to use one of
    the mongos
    // for read only purposes
    if (preg_match('/couldn\'t determine master/', $e->getMessage())) {
    $slave_available = FALSE;
    foreach ($mongo_url['connection'] as $single_url) {
    try {
    $mongo_conn = new Mongo('mongodb://' . $single_url,
    array('persist' => 'conn_x'));
    $slave_available = TRUE;
    break;
    }
    catch (MongoConnectionException $f) { }
    }
    }
    }

    if (!empty($mongo_conn)) {
    $mongo = $mongo_conn->selectDB($mongo_url['database']);
    } else {
    return new NoMongoCollection();
    }

    if (!isset($mongo_slaves)) {
    $mongo_slaves = array();
    // this is the point in the code that the exceptions occur on
    $mongo_replset_data = $mongo_conn->admin->command(array('isMaster'
    => TRUE));

    ...
     */
}
