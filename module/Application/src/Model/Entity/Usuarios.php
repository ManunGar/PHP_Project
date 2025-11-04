<?php
namespace Application\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use \Zend\Db\Sql\Expression;

class Usuarios extends TableGateway{
	
    protected $_table = 'usuarios';
    protected $_pk = 'id_usu';
    protected $_adapter;

    private $tableGateway;
	
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
            $object = new Usuario(0);	
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
	
    public function getDescrypt($where = null,$llave){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        (isset($where))?($select->where($where)):(null);
        $select->from($this->_table)->columns(array('clave' => new Expression('AES_DECRYPT(clave, "' . $llave . '")')));		
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->current()->clave;
    }	

    public function setDescrypt($where = null,$llave, $clave){
        $sql = new Sql($this->_adapter);
        $fecha = date('Y-m-d');
        $nuevafecha = strtotime ( '+6 month' , strtotime ( $fecha ) ) ;
        $prox_fecha = date ( 'Y-m-d' , $nuevafecha );
        $update = $sql->update();
        $update->table($this->_table);
        $update->set(array('clave' => new Expression('AES_ENCRYPT("' . $clave . '", "' . $llave . '")')));
        (isset($where))?($update->where($where)):(null);
        $statement = $sql->getSqlStringForSqlObject($update);
        $results = $this->_adapter->query($statement, Adapter::QUERY_MODE_EXECUTE);
    }
    
    public function getByColegiado($colegiado){
        $usuarios = $this->get('colegiado LIKE "'.$colegiado.'"','id_usu DESC');
        if(count($usuarios) == 1){
            $usuario = current($usuarios);
        }else{
            $usuario = new Usuario();
        }
        return $usuario;
    }
    
    public function getByEmail($email){
        $usuarios = $this->get('email LIKE "'.$email.'"','id_usu DESC');
        if(count($usuarios) == 1){
            $usuario = current($usuarios);
        }else{
            $usuario = new Usuario();
        }
        return $usuario;
    }
    
    public function getByNif($nif){
        $usuarios = $this->get('nif LIKE "'.$nif.'"','id_usu DESC');
        if(count($usuarios) == 1){
            $usuario = current($usuarios);
        }else{
            $usuario = new Usuario();
        }
        return $usuario;
    }
    
    public function rellenaNumColegiado(){
        $usuarios = $this->get('colegiado IS NOT NULL','id_usu DESC');
        foreach($usuarios as $usuario):
            $num_colegiado = str_pad($usuario->get('colegiado'), 5, '0', STR_PAD_LEFT);
            $this->update(['colegiado' => $num_colegiado],'id_usu = '.$usuario->get('id'));
        endforeach;
    }
}