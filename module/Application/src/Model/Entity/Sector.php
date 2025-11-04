<?php
namespace Application\Model\Entity;
use Application\Model\Utility\Utilidades;

class Sector{
    
    protected $_id;
    protected $_data;
    protected $_pk = 'id_sec';

    public function __construct($cod = 0){
        $this->_id = (int)$cod;
        $this->_data = array();
        if($this->_id > 0){
            $db_table = new Sectores();
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
                /*if(isset($row['fecha'])){
                    $row['fecha'] = Utilidades::giraFecha($row['fecha']);
                }*/
            case 2:
                $this->_id = (int)$row[$this->_pk];
                if(empty($row['nombre'])){
                    $this->_data['nombre'] = null;
                }else{
                    $this->_data['nombre'] = $row['nombre'];
                }
                break;
        }
        return $algun_valor_vacio;
    }
	
    public function save(){
        $db_table = new Sectores();
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
        $db_emp = new Empresas();
        $num = $db_emp->num('id_sec = '.$this->_id);
        if($num == 0){
            $db_table = new Sectores();
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
        }else{
            if(!empty($this->_data[$elemento])){
                return html_entity_decode(htmlspecialchars(stripslashes($this->_data[$elemento])),ENT_NOQUOTES);
            }else{
                return null;
            }
        }
    }
        
    private function dates(){
        /*if(isset($this->_data['fecha'])){
            $this->_data['fecha'] = Utilidades::giraFecha($this->_data['fecha']);
        }*/
    }
	
}