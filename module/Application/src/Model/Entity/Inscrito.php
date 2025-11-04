<?php
namespace Application\Model\Entity;
use Application\Model\Utility\Utilidades;

class Inscrito{
    
    protected $_id;
    protected $_data;
    protected $_pk = 'id_ui';
    CONST FILE_DIRECTORY_DIPLOMA = './public/files/diplomas/';

    public function __construct($cod = 0){
        $this->_id = (int)$cod;
        $this->_data = array();
        if($this->_id > 0){
            $db_table = new Inscritos();
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
                if(isset($row['envio_diploma'])){
                    $row['envio_diploma'] = Utilidades::giraFecha($row['envio_diploma']);
                }
            case 2:
                $this->_id = (int)$row[$this->_pk];
                
                $this->_data['id_ins'] = (int)$row['id_ins'];
                
                $this->_data['id_usu'] = (int)$row['id_usu'];
                
                $this->_data['sitcol'] = (int)$row['sitcol'];
                
                $this->_data['sitlab'] = (int)$row['sitlab'];
                
                if(empty($row['importe'])){
                    $this->_data['importe'] = null;
                }else{
                    $this->_data['importe'] = str_replace(',','.',$row['importe']);
                }
                
                if(empty($row['diploma'])){
                    $this->_data['diploma'] = null;
                }else{
                    $this->_data['diploma'] = $row['diploma'];
                }

                if(isset($row['envio_diploma'])){
                    $this->_data['envio_diploma'] = $row['envio_diploma'];
                }
                
                $this->_data['asistencia'] = (int)$row['asistencia'];
                break;
        }
        return $algun_valor_vacio;
    }
	
    public function save(){
        $db_table = new Inscritos();
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
        $num = 0;
        if($num == 0){
            $db_table = new Inscritos();
            $db_table->delete($this->_pk.' = '.$this->_id);
            $dev = 525;
            $inscripcion = $this->get('inscripcion');
            $inscripcion->revisaImporte();
        }else{
            $dev = 536;
        }
        return $dev;
    }

    public function get($elemento){
        if($elemento == 'id'){
            return $this->_id;
        } else if($elemento == 'inscripcion'){
            return new Inscripcion($this->_data['id_ins']);
        } else if($elemento == 'usuario'){
            return new Usuario($this->_data['id_usu']);
        } else {
            if(!empty($this->_data[$elemento])){
                return html_entity_decode(htmlspecialchars(stripslashes($this->_data[$elemento])),ENT_NOQUOTES);
            }else{
                return null;
            }
        }
    }
        
    private function dates(){
        if(isset($this->_data['envio_diploma'])){
            $this->_data['envio_diploma'] = Utilidades::giraFecha($this->_data['envio_diploma']);
        }
    }

    public function setAsistencia($asistencia){
        $this->_data['asistencia'] = $asistencia;
        $this->save();
    }

    public function setDiploma($fichero){
        $this->_data['diploma'] = $fichero;
        $this->save();
    }

    public function removeDiploma(){
        @unlink(self::FILE_DIRECTORY_DIPLOMA . $this->_data['cv']);
        $this->_data['diploma'] = null;
        $this->save();
    }

    public function setAttribute($index, $value){
        $this->_data[$index] = $value;
    }

}