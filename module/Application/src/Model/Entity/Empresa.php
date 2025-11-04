<?php
namespace Application\Model\Entity;
use Application\Model\Utility\Utilidades;

class Empresa{
    
    protected $_id;
    protected $_data;
    protected $_pk = 'id_emp';

    public function __construct($cod = 0){
        $this->_id = (int)$cod;
        $this->_data = array();
        if($this->_id > 0){
            $db_table = new Empresas();
            $results = $db_table->select($this->_pk.' = '.$this->_id);
            $row = $results->current();
            if($row != null){
                $this->set($row,1);
            }else{
                $this->_id = 0;
            }
        }
        return $this;
    }
	
    public function set($row,$case = 2){
        $algun_valor_vacio = 0;
        switch($case){
            case 1:
                if(isset($row['alta'])){
                    $row['alta'] = Utilidades::giraFecha($row['alta']);
                }
            case 2:
                $this->_id = (int)$row[$this->_pk];
                if(empty($row['nombre'])){
                    $this->_data['nombre'] = null;
                }else{
                    $this->_data['nombre'] = $row['nombre'];
                }
                if(empty($row['razonsocial'])){
                    $this->_data['razonsocial'] = null;
                }else{
                    $this->_data['razonsocial'] = $row['razonsocial'];
                }
                if(empty($row['cif'])){
                    $this->_data['cif'] = null;
                }else{
                    $this->_data['cif'] = $row['cif'];
                }
                $this->_data['estado'] = (int)$row['estado'];
                if(empty($row['id_sec'])){
                    $this->_data['id_sec'] = null;
                }else{
                    $this->_data['id_sec'] = (int)$row['id_sec'];
                }
                if(empty($row['alta'])){
                    $this->_data['alta'] = date('d-m-Y');
                }else{
                    $this->_data['alta'] = $row['alta'];
                }
                if(empty($row['web'])){
                    $this->_data['web'] = null;
                }else{
                    $this->_data['web'] = $row['web'];
                }
                if(empty($row['direccion'])){
                    $this->_data['direccion'] = null;
                }else{
                    $this->_data['direccion'] = $row['direccion'];
                }
                if(empty($row['cp'])){
                    $this->_data['cp'] = null;
                }else{
                    $this->_data['cp'] = $row['cp'];
                }
                if(empty($row['localidad'])){
                    $this->_data['localidad'] = null;
                }else{
                    $this->_data['localidad'] = $row['localidad'];
                }
                if(empty($row['provincia'])){
                    $this->_data['provincia'] = null;
                }else{
                    $this->_data['provincia'] = $row['provincia'];
                }
                if(empty($row['email'])){
                    $this->_data['email'] = null;
                }else{
                    $this->_data['email'] = $row['email'];
                }
                if(empty($row['telefono'])){
                    $this->_data['telefono'] = null;
                }else{
                    $this->_data['telefono'] = $row['telefono'];
                }
                break;
        }
        return $algun_valor_vacio;
    }
	
    public function save(){
        $db_table = new Empresas();
        $this->dates();
        if($this->_id == 0){
            $db_table->insert($this->_data);
            $this->_id = $db_table->getLastInsertValue();
        }else{
            $db_table->update($this->_data,$this->_pk.' = '.$this->_id);
        }
        $this->dates();
        return $this->_id;
    }
	
    public function remove(){
        $db_ofe = new Ofertas();
        $db_ins = new Inscripciones();
        $db_usu = new Usuarios();
        $num = $db_ofe->num('id_emp = '.$this->_id);
        $num += $db_ins->num('id_emp = '.$this->_id);
        $num += $db_usu->num('id_emp = '.$this->_id);
        if($num == 0){
            $db_table = new Empresas();
            $db_table->delete($this->_pk.' = '.$this->_id);
            $dev = 525;
        }else{
            $dev = 536;
        }
        return $dev;
    }

    public function get($elemento){
        if($elemento == 'id'){
            return $this->_id;
        }else if($elemento == 'autorizado'){
            $db = new Usuarios();
            $rows = $db->get('id_emp = '.$this->_id.' AND autorizado = 1','id_usu DESC', 1);
            if((int)count($rows)){
                $autorizado = current($rows);
            }else{
                $autorizado = new Usuario();
            }
            return $autorizado;
        }else{
            if(!empty($this->_data[$elemento])){
                return html_entity_decode(htmlspecialchars(stripslashes($this->_data[$elemento])),ENT_NOQUOTES);
            }else{
                return null;
            }
        }
    }
        
    private function dates(){
        if(isset($this->_data['alta'])){
            $this->_data['alta'] = Utilidades::giraFecha($this->_data['alta']);
        }
    }
    
    public function setEstado($estado){
        $this->_data['estado'] = $estado;
        $this->save();
    }
	
}