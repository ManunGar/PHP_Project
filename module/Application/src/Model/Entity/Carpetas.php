<?php
namespace Application\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use \Zend\Db\Sql\Expression;

class Carpetas extends TableGateway{
	
    protected $_table = 'carpetas';
    protected $_pk = 'id_car';
    protected $_adapter;
	
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null){
    	if(!isset($adapter)){
            $this->_adapter = \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter();
    	}else{
            $this->_adapter = $adapter;
    	}
        return parent::__construct($this->_table, $this->_adapter, $databaseSchema,$selectResultPrototype);
    }
    
    public function get($where = null,$order = null,$limit = null,$offset= null){
        $select = $this->sql->select();
        (isset($where))?($select->where($where)):(null);
        (isset($order))?($select->order($order)):(null);
        (isset($limit))?($select->limit($limit)):(null);
        (isset($offset))?($select->offset($offset)):(null);

        $data = $this->selectWith($select);
        $rows = $data->toArray();
        $array = array();
        foreach($rows as $row):
            $object = new Carpeta(0);
            $object->set($row,1);
            $array[$row[$this->_pk]] = $object;
        endforeach;	
        return $array;
    }
	
    public function num($where = null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        (isset($where))?($select->where($where)):(null);
        $select->from($this->_table)->columns(array('num' => new Expression('COUNT(*)')));		
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->current()->num;
    }

    public function getPermisosUsuarios($where = null,$order = null,$limit = null,$offset= null,$groupby = 'id_car'){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from($this->_table)
            ->columns(array('id_car' => 'id_car','nombre' => 'nombre'))
            ->join('permisos', 'permisos.id_car = carpetas.id_car', array('id_per' => 'id_per', 'id_usu' => 'id_usu', 'permiso' => 'permiso'), 'left')
            ->group($groupby);
        (isset($where))?($select->where($where)):(null);
        
        $data = $this->selectWith($select);
        // $selectString = $sql->getSqlStringForSqlObject($select);
        // $data = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $data;
    }
}
