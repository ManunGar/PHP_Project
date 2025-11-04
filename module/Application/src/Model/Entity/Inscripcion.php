<?php
namespace Application\Model\Entity;
use Application\Model\Utility\Utilidades;

class Inscripcion{
    
    protected $_id;
    protected $_data;
    protected $_pk = 'id_ins';
    CONST FILE_DIRECTORY_JUSTIFICANTE = './public/files/justificantes/';

    public function __construct($cod = 0){
        $this->_id = (int)$cod;
        $this->_data = array();
        if($this->_id > 0){
            $db_table = new Inscripciones();
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
                if(isset($row['fecha'])){
                    $row['fecha'] = Utilidades::giraFecha($row['fecha']);
                }
            case 2:
                $this->_id = (int)$row[$this->_pk];
                if(empty($row['id_usu'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['id_usu'] = (int)$row['id_usu'];
                }
                if(empty($row['id_cur'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['id_cur'] = (int)$row['id_cur'];
                }
                $this->_data['beca'] = (int)$row['beca'];
                if(empty($row['importe'])){
                    $this->_data['importe'] = null;
                }else{
                    $this->_data['importe'] = str_replace(',','.',$row['importe']);
                }
                if(empty($row['beca_importe'])){
                    $this->_data['beca_importe'] = null;
                }else{
                    $this->_data['beca_importe'] = str_replace(',','.',$row['beca_importe']);
                }
                if(empty($row['observaciones'])){
                    $this->_data['observaciones'] = null;
                }else{
                    $this->_data['observaciones'] = $row['observaciones'];
                }
                if(empty($row['id_emp'])){
                    $this->_data['id_emp'] = null;
                }else{
                    $this->_data['id_emp'] = (int)$row['id_emp'];
                }
                $this->_data['estado'] = (int)$row['estado'];
                $this->_data['pago'] = (int)$row['pago'];
                if(empty($row['fecha'])){
                    $this->_data['fecha'] = date('d-m-Y H:i:s');
                }else{
                    $this->_data['fecha'] = $row['fecha'];
                }

                if(empty($row['nombre'])){
                    $this->_data['nombre'] = null;
                }else{
                    $this->_data['nombre'] = $row['nombre'];
                }

                if(empty($row['cif'])){
                    $this->_data['cif'] = null;
                }else{
                    $this->_data['cif'] = $row['cif'];
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

                if(empty($row['nombre_empresa'])){
                    $this->_data['nombre_empresa'] = null;
                }else{
                    $this->_data['nombre_empresa'] = $row['nombre_empresa'];
                }

                if(empty($row['cif_empresa'])){
                    $this->_data['cif_empresa'] = null;
                }else{
                    $this->_data['cif_empresa'] = $row['cif_empresa'];
                }

                if(empty($row['cp_empresa'])){
                    $this->_data['cp_empresa'] = null;
                }else{
                    $this->_data['cp_empresa'] = $row['cp_empresa'];
                }

                if(empty($row['direccion_empresa'])){
                    $this->_data['direccion_empresa'] = null;
                }else{
                    $this->_data['direccion_empresa'] = $row['direccion_empresa'];
                }

                if(empty($row['localidad_empresa'])){
                    $this->_data['localidad_empresa'] = null;
                }else{
                    $this->_data['localidad_empresa'] = $row['localidad_empresa'];
                }

                if(empty($row['provincia_empresa'])){
                    $this->_data['provincia_empresa'] = null;
                }else{
                    $this->_data['provincia_empresa'] = $row['provincia_empresa'];
                }

                if(empty($row['justificante_pago'])){
                    $this->_data['justificante_pago'] = null;
                }else{
                    $this->_data['justificante_pago'] = $row['justificante_pago'];
                }

                break;
        }
        return $algun_valor_vacio;
    }
	
    public function save(){
        $db_table = new Inscripciones();
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
	
    public function remove($sincomprobaciones = 0){
        $db_ins = new Inscritos();
        $db_par = new Participantes();
        if($sincomprobaciones){
            $num1 = 0;
            $num2 = 0;
        }else{
            $num1 = $db_ins->num('id_ins = '.$this->_id);
            $num2 = $db_par->num('id_ins = '.$this->_id);
        }
        if($num1 + $num2 == 0){
            if($sincomprobaciones){
                $db_ins->delete('id_ins = '.$this->_id);
                $db_par->delete('id_ins = '.$this->_id);
            }
            $db_table = new Inscripciones();
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
        }else if($elemento == 'curso'){
            return new Curso($this->_data['id_cur']);
        }else if($elemento == 'creador'){
            return new Usuario($this->_data['id_usu']);
        }else if($elemento == 'empresa'){
            return new Empresa($this->_data['id_emp']);
        }else if($elemento == 'total'){
            $total = 0;
            if(isset($this->_data['importe']) && isset($this->_data['importe'])){
                $total = $this->_data['importe'] - $this->_data['beca_importe'];
            }
            return $total;
        }else{
            if(!empty($this->_data[$elemento])){
                return html_entity_decode(htmlspecialchars(stripslashes($this->_data[$elemento])),ENT_NOQUOTES);
            }else{
                return null;
            }
        }
    }
        
    private function dates(){
        if(isset($this->_data['fecha'])){
            $this->_data['fecha'] = Utilidades::giraFecha($this->_data['fecha']);
        }
    }
    
    public function setEstado($estado,$forma_pago = 0){
        $this->_data['estado'] = $estado;
        if((int)$forma_pago){
            $this->_data['pago'] = $forma_pago;
        }
        $this->save();
    }

    public function revisaImporte(){

        $db_insc = new Inscripciones();

        $curso = $this->get('curso');
        $total_importe  = 0;
        if($curso->get('tipo') == 2) {
            $participantes = $db_insc->getParticipantes('id_ins = ' . $this->_id);
            foreach($participantes as $participante):
                $total_importe += $participante['importe'];
            endforeach;
        }else{
            $inscritos = $db_insc->getInscritos('id_ins = ' . $this->_id);
            foreach($inscritos as $inscrito):
                $total_importe += $inscrito['importe'];
            endforeach;
        }

        $db_inscripciones = new Inscripciones();
        $data_update_inscripcion = [];
        $data_update_inscripcion['importe'] = $total_importe;
        $db_inscripciones->update($data_update_inscripcion, 'id_ins = ' . $this->_id);

    }

    public function setJustificante($fichero){
        $data = [];
        $data['justificante_pago'] = $fichero;

        $db_usuario = new Inscripciones();
        $db_usuario->update($data, 'id_ins = ' . $this->_id);
    }

    public function removeJustificante(){
        @unlink(self::FILE_DIRECTORY_JUSTIFICANTE . $this->_data['justificante_pago']);

        $data = [];
        $data['justificante_pago'] = null;

        $db_usuario = new Inscripciones();
        $db_usuario->update($data, 'id_ins = ' . $this->_id);
    }

    public function setAttribute($index, $value){
        $this->_data[$index] = $value;
    }
}
