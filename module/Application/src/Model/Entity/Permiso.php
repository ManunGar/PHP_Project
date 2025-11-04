<?php
namespace Application\Model\Entity;
use Application\Model\Utility\Utilidades;

class Permiso{
    
    protected $_id;
    protected $_data;
    protected $_pk = 'id_per';

    public function __construct($cod = 0){
        $this->_id = (int)$cod;
        $this->_data = array();
        if($this->_id > 0){
            $db_table = new Permisos();
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
            case 2:
                $this->_id = (int)$row[$this->_pk];
                $this->_data['id_car'] = $row['id_car'];
                $this->_data['id_usu'] = $row['id_usu'];
                $this->_data['permiso'] = $row['permiso'];

                break;
        }
        return $algun_valor_vacio;
    }
	
    public function save(){
        $db_table = new Permisos();
        if($this->_id == 0){
            $db_table->insert($this->_data);
            $this->_id = $db_table->getLastInsertValue();
        }else{
            $db_table->update($this->_data,$this->_pk.' = '.$this->_id);
        }
        return $this->_id;
    }
	
    public function remove(){
        $db_table = new Permisos();
        $db_table->delete($this->_pk.' = '.$this->_id);
        $dev = 525;

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
}
