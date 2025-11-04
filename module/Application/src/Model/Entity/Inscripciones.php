<?php
namespace Application\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use \Zend\Db\Sql\Expression;

class Inscripciones extends TableGateway{
	
    protected $_table = 'inscripciones';
    protected $_pk = 'id_ins';
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
            $object = new Inscripcion(0);	
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
     * CREATE VIEW v_inscripciones AS
     * SELECT
     * inscripciones.id_ins,
     * inscripciones.fecha,
     * inscripciones.importe,
     * inscripciones.estado,
     * inscripciones.pago,
     * inscripciones.id_emp,
     * cursos.id_cur,
     * cursos.nombre AS cursosNombre,
     * cursos.tipo,
     * usuarios.id_usu,
     * usuarios.nombre AS usuariosNombre,
     * usuarios.apellidos AS usuariosApellidos,
     * usuarios.telefono,
     * usuarios.email,
     * COUNT(id_ui) AS inscritos
     * FROM inscripciones
     * INNER JOIN cursos ON inscripciones.id_cur = cursos.id_cur
     * INNER JOIN usuarios ON inscripciones.id_usu = usuarios.id_usu
     * LEFT JOIN inscritos ON inscripciones.id_ins = inscritos.id_ins
     * GROUP BY inscripciones.id_ins;
     */
    public function getInscripciones($where = null,$order = null,$limit = null,$offset= null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from('v_inscripciones')->columns(['*']);
        (isset($where))?($select->where($where)):(null);
        (isset($order))?($select->order($order)):(null);
        (isset($limit))?($select->limit($limit)):(null);
        (isset($offset))?($select->offset($offset)):(null);

        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->toArray();
    }

    public function numInscripciones($where = null,$order = null,$limit = null,$offset= null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        (isset($where))?($select->where($where)):(null);
        $select->from('v_inscripciones')->columns(array('num' => new Expression('COUNT(*)')));
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->current()->num;
    }
    
    /*
     * CREATE VIEW v_inscritos AS
     * SELECT
     * inscripciones.id_ins,
     * inscripciones.fecha,
     * inscripciones.estado,
     * inscripciones.pago,
     * inscripciones.id_emp,
     * inscripciones.id_usu as id_cre,
     * cursos.id_cur,
     * cursos.nombre AS cursosNombre,
     * cursos.tipo,
     * cursos.estado as cursoEstado,
     * inscritos.id_ui,
     * inscritos.importe,
     * inscritos.diploma,
     * inscritos.asistencia,
     * inscritos.envio_diploma,
     * usuarios.id_usu,
     * usuarios.nombre AS usuariosNombre,
     * usuarios.apellidos AS usuariosApellidos,
     * usuarios.telefono,
     * usuarios.email
     * FROM inscripciones
     * INNER JOIN cursos ON inscripciones.id_cur = cursos.id_cur
     * INNER JOIN inscritos ON inscripciones.id_ins = inscritos.id_ins
     * INNER JOIN usuarios ON inscritos.id_usu = usuarios.id_usu
     * GROUP BY inscritos.id_ui;
     */
    public function getInscritos($where = null,$order = null,$limit = null,$offset= null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from('v_inscritos')->columns(['*']);
        (isset($where))?($select->where($where)):(null);
        (isset($order))?($select->order($order)):(null);
        (isset($limit))?($select->limit($limit)):(null);
        (isset($offset))?($select->offset($offset)):(null);
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->toArray();
    }
	
    public function numInscritos($where = null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        (isset($where))?($select->where($where)):(null);
        $select->from('v_inscritos')->columns(array('num' => new Expression('COUNT(*)')));		
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->current()->num;
    }
    
    /*
     * CREATE VIEW v_participantes AS
     * SELECT
     * inscripciones.id_ins,
     * inscripciones.fecha,
     * inscripciones.estado,
     * inscripciones.pago,
     * inscripciones.id_emp,
     * inscripciones.id_usu AS id_cre,
     * cursos.id_cur,
     * cursos.nombre AS cursosNombre,
     * cursos.tipo,
     * usuarios.id_usu,
     * usuarios.nombre AS usuariosNombre,
     * usuarios.apellidos AS usuariosApellidos,
     * usuarios.telefono,
     * usuarios.email,
     * participantes.id_par,
     * participantes.importe,
     * menores.id_men,
     * menores.nombre AS menoresNombre,
     * menores.apellidos AS menoresApellidos,
     * menores.nacimiento,
     * menores.observaciones
     * FROM inscripciones
     * INNER JOIN cursos ON inscripciones.id_cur = cursos.id_cur
     * INNER JOIN usuarios ON inscripciones.id_usu = usuarios.id_usu
     * INNER JOIN participantes ON inscripciones.id_ins = participantes.id_ins
     * INNER JOIN menores ON participantes.id_men = menores.id_men
     * GROUP BY participantes.id_par;
     */
    public function getParticipantes($where = null,$order = null,$limit = null,$offset= null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from('v_participantes')->columns(['*']);
        (isset($where))?($select->where($where)):(null);
        (isset($order))?($select->order($order)):(null);
        (isset($limit))?($select->limit($limit)):(null);
        (isset($offset))?($select->offset($offset)):(null);
        
        $selectString = $sql->getSqlStringForSqlObject($select);        
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->toArray();
    }
	
    public function numParticipantes($where = null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        (isset($where))?($select->where($where)):(null);
        $select->from('v_participantes')->columns(array('num' => new Expression('COUNT(*)')));		
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->current()->num;
    }
    
    public function borraInscripcionesIncompletasCursosTerminados(){
        $selectString = 'SELECT inscripciones.* FROM inscripciones INNER JOIN cursos ON inscripciones.id_cur = cursos.id_cur WHERE (inscripciones.estado = 0 OR inscripciones.estado = 5) AND cursos.estado = 3';
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        $rows = $results->toArray();
        foreach($rows as $row):
            $object = new Inscripcion(0);	
            $object->set($row,1);
            $object->remove(1);
        endforeach;
    }
}