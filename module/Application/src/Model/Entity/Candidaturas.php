<?php
namespace Application\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use \Zend\Db\Sql\Expression;

class Candidaturas extends TableGateway{
	
    protected $_table = 'candidaturas';
    protected $_pk = 'id_can';
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
            $object = new Candidatura(0);	
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
    
    /*
     * CREATE VIEW v_candidaturas AS
     * SELECT 
     * candidaturas.id_can,
     * candidaturas.fecha AS candidaturasFecha,
     * candidaturas.estado AS candidaturasEstado,
     * ofertas.id_ofe,
     * ofertas.titulo,
     * ofertas.plazas,
     * ofertas.fecha AS ofertasFecha,
     * ofertas.estado AS ofertasEstado,
     * empresas.id_emp,
     * empresas.nombre AS empresasNombre,
     * usuarios.id_usu,
     * usuarios.nombre AS usuariosNombre,
     * usuarios.apellidos,
     * usuarios.telefono,
     * usuarios.email
     * FROM candidaturas
     * INNER JOIN ofertas ON candidaturas.id_ofe = ofertas.id_ofe
     * INNER JOIN empresas ON ofertas.id_emp = empresas.id_emp
     * INNER JOIN usuarios ON candidaturas.id_usu = usuarios.id_usu;
     */
    public function getCandidaturas($where = null,$order = null,$limit = null,$offset= null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from('v_candidaturas')->columns(['*']);
        (isset($where))?($select->where($where)):(null);
        (isset($order))?($select->order($order)):(null);
        (isset($limit))?($select->limit($limit)):(null);
        (isset($offset))?($select->offset($offset)):(null);
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->toArray();
    }
	
    public function numCandidaturas($where = null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        (isset($where))?($select->where($where)):(null);
        $select->from('v_candidaturas')->columns(array('num' => new Expression('COUNT(*)')));		
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->current()->num;
    }
}