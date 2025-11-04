<?php
namespace Application\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use \Zend\Db\Sql\Expression;
use Application\Model\Utility\Utilidades;

class Empresas extends TableGateway{
	
    protected $_table = 'empresas';
    protected $_pk = 'id_emp';
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
            $object = new Empresa(0);	
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
     * CREATE VIEW v_empresas AS
     * SELECT
     * empresas.id_emp,
     * empresas.nombre AS empresasNombre,
     * empresas.cif,
     * empresas.estado,
     * sectores.id_sec,
     * sectores.nombre AS sectoresNombre,
     * usuarios.id_usu,
     * usuarios.nombre AS usuariosNombre,
     * usuarios.apellidos,
     * usuarios.telefono,
     * usuarios.email
     * FROM empresas
     * LEFT JOIN sectores ON empresas.id_sec = sectores.id_sec
     * LEFT JOIN usuarios ON empresas.id_emp = usuarios.id_emp AND usuarios.autorizado = 1
     * GROUP BY empresas.id_emp;
     */
    public function getEmpresas($where = null,$order = null,$limit = null,$offset= null){
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from('v_empresas')->columns(['*']);
        (isset($where))?($select->where($where)):(null);
        (isset($order))?($select->order($order)):(null);
        (isset($limit))?($select->limit($limit)):(null);
        (isset($offset))?($select->offset($offset)):(null);
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        return $results->toArray();
    }
    
    public function importarEmpresas(){
        $sql = new Sql($this->_adapter);
        $select1 = $sql->select();
        $select1->from('coii_js_job_companies')->columns(['*']);
        $selectString1 = $sql->getSqlStringForSqlObject($select1);
        $results1 = $this->_adapter->query($selectString1, Adapter::QUERY_MODE_EXECUTE);
        $rows1 = $results1->toArray();
        $hoy = date('d-m-Y');
        $db_usu = new Usuarios();
        foreach($rows1 as $row):
            if(!empty($row['name'])){
                $fecha = explode(' ',$row['created']);
                if(!empty($fecha[0])){
                    $fecha1 = Utilidades::giraFecha($fecha[0]);
                    if($fecha1 = '00-00-0000'){
                        $fecha1 = $hoy;
                    }
                }else{
                    $fecha1 = $hoy;
                }
                $data1 = [
                    'id_emp'        => 0,
                    'nombre'        => $row['name'],
                    'razonsocial'   => $row['name'],
                    'cif'           => null,
                    'estado'        => 1,
                    'id_sec'        => null,
                    'alta'          => $fecha1,
                    'web'           => $row['url'],
                    'direccion'     => $row['address1'],
                    'cp'            => $row['zipcode'],
                    'localidad'     => $row['city'],
                    'provincia'     => $row['county'],
                ];
                $empresa = new Empresa(0);
                $empresa->set($data1);
                $id_emp = $empresa->save();

                $autorizado = $db_usu->getByEmail($row['contactemail']);
                if((int)$autorizado->get('id')){
                    $autorizado->setAutorizado($id_emp);
                }else{
                    $data2 = [
                        'id_usu' => 0,
                        'nombre'    => $row['contactname'],
                        'apellidos' => null,
                        'colegiado' => null,
                        'telefono'  => $row['contactphone'],
                        'email'     => $row['contactemail'],
                        'nif'       => null,
                        'sexo'      => 0,
                        'nacimiento'    => null,
                        'clave'     => null,
                        'rol'       => 'us4ario',
                        'alta'      => $hoy,
                        'baja'      => null,
                        'cv'        => null,
                        'id_emp'    => $id_emp,
                        'autorizado'    => 1,
                        'sitlab'    => 1,
                        'sitcol'    => 5,
                        'titulacion'    => null,
                        'master'    => null,
                        'sincro'    => null,
                        'empleo'    => 0,
                        'experiencia'   => null,
                        'especialidad'  => null,
                        'jornada'   => null,
                        'delegacion'    => null,
                        'pago_pendiente'    => 0,
                        'observaciones' => null

                    ];
                    $autorizado->set($data2);
                    $autorizado->save();
                }

                $select2 = $sql->select();
                $select2->from('coii_js_job_jobs')->columns(['*'])->where('companyid = '.$row['id'].' AND id > 1741');
                $selectString2 = $sql->getSqlStringForSqlObject($select2);
                $results2 = $this->_adapter->query($selectString2, Adapter::QUERY_MODE_EXECUTE);
                $rows2 = $results2->toArray();
                if(count($rows2) > 0){
                    foreach($rows2 as $job):
                        $fecha = explode(' ',$job['created']);
                        if(!empty($fecha[0])){
                            $fecha2 = Utilidades::giraFecha($fecha[0]);
                        }else{
                            $fecha2 = $hoy;
                        }
                        $data3 = [
                            'id_ofe'    => 0,
                            'id_emp'    => $id_emp,
                            'titulo'    => $job['title'],
                            'descripcion'   => null,
                            'info'      => strip_tags($job['description']),
                            'plazas'    => 1,
                            'categoria' => null,
                            'experiencia'   => $job['experience'],
                            'estado'    => 1,
                            'fecha'     => $fecha2,
                            'id_usu'    => $autorizado->get('id')
                        ];
                        $oferta = new Oferta(0);
                        $oferta->set($data3);
                        $id_ofe = $oferta->save();

                        $selectString3 = "SELECT coii_js_job_jobapply.*,coii_users.email FROM coii_js_job_jobapply INNER JOIN coii_users ON coii_js_job_jobapply.uid = coii_users.id WHERE jobid = ".$job['id'];
                        $results3 = $this->_adapter->query($selectString3, Adapter::QUERY_MODE_EXECUTE);
                        $rows3 = $results3->toArray();
                        foreach($rows3 as $apply):
                            $candidato = $db_usu->getByEmail($apply['email']);
                            if((int)$candidato->get('id')){
                                $data4 = [
                                    'id_can'    => 0,
                                    'id_usu'    => $candidato->get('id'),
                                    'id_ofe'    => $id_ofe,
                                    'comentario'    => null,
                                    'fecha'     => date('d-m-Y H:i:s'),
                                    'estado'    => 1
                                ];
                                $candidatura = new Candidatura(0);
                                $candidatura->set($data4);
                                $candidatura->save();
                            }
                        endforeach;
                    endforeach;
                }
            }
        endforeach;
        die();
    }
    
    public function eliminarDuplicados(){
        $selectString = 'SELECT cif,count(*) as num FROM `empresas` WHERE cif is not null GROUP BY cif HAVING num > 1 ORDER BY `num` DESC';
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        $rows = $results->toArray();
        $db_usu = new Usuarios();
        $db_ins = new Inscripciones();
        $db_ofe = new Ofertas();
        echo '<pre>';
        foreach($rows as $row):
            $empresas = $this->get('cif LIKE "'.$row['cif'].'"','estado DESC');
            $id_activo = 0;
            $otros_id = [];
            var_dump($empresas);
            foreach($empresas as $empresa):
                if($empresa->get('estado') == 1){
                    $id_activo = $empresa->get('id');
                }else{
                    $otros_id[] = $empresa->get('id');
                }
            endforeach;
            
            if($id_activo > 0){
                $where = 'id_emp = '.implode(' OR id_emp = ',$otros_id);
                $data = ['id_emp' => $id_activo];
                var_dump($data);
                var_dump($where);
                $db_ins->update($data, $where);
                $db_ofe->update($data, $where);
                $data['autorizado'] = 2;
                $db_usu->update($data, $where);

                $this->delete($where);
            }
        endforeach;
    }
    
    public function getByCif($cif){
        $objects = $this->get('cif LIKE "'.$cif.'"','id_emp DESC',1);
        if((int)count($objects)){
            $object = current($objects);
            $id_emp = $object->get('id');
        }else{
            $id_emp = 0;
        }
        return $id_emp;
    }
}