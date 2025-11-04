<?php
namespace Application\Model\Entity;
use Application\Model\Utility\Utilidades;

class Usuario{

    protected $_id;
    protected $_data;
    protected $_pk = 'id_usu';
    CONST FILE_DIRECTORY_CV = './public/files/candidaturas/';

    public function __construct($cod = 0){
        $this->_id = (int)$cod;
        $this->_data = array();
        if($this->_id > 0){
            $db_table = new Usuarios();
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
                if(isset($row['nacimiento'])){
                    $row['nacimiento'] = Utilidades::giraFecha($row['nacimiento']);
                }
                if(isset($row['alta'])){
                    $row['alta'] = Utilidades::giraFecha($row['alta']);
                }
                if(isset($row['baja'])){
                    $row['baja'] = Utilidades::giraFecha($row['baja']);
                }
                if(isset($row['sincro'])){
                    $row['sincro'] = Utilidades::giraFecha($row['sincro']);
                }
            case 2:
                $this->_id = (int)$row['id_usu'];
                if(empty($row['nombre'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['nombre'] = $row['nombre'];
                }
                if(empty($row['apellidos'])){
                    $this->_data['apellidos'] = null;
                }else{
                    $this->_data['apellidos'] = $row['apellidos'];
                }
                if(empty($row['colegiado'])){
                    $this->_data['colegiado'] = null;
                }else{
                    $this->_data['colegiado'] = $row['colegiado'];
                }
                if(empty($row['telefono'])){
                    $this->_data['telefono'] = null;
                }else{
                    $this->_data['telefono'] = $row['telefono'];
                }
                if(empty($row['email'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['email'] = $row['email'];
                }
                if(empty($row['nif'])){
                    $this->_data['nif'] = null;
                }else{
                    $this->_data['nif'] = mb_strtoupper(str_replace([' ','-','=','.',','],'',$row['nif']),'utf-8');
                }
                $this->_data['sexo'] = (int)$row['sexo'];
                if(empty($row['nacimiento'])){
                    $this->_data['nacimiento'] = null;
                }else{
                    $this->_data['nacimiento'] = $row['nacimiento'];
                }
                if((int)$this->_id == 0){
                    if(empty($row['clave'])){
                        $this->_data['clave'] = Utilidades::generaPass();
                    }else{
                        $this->_data['clave'] = $row['clave'];
                    }
                }
                $this->_data['rol'] = $row['rol'];
                if(empty($row['cv'])){
                    $this->_data['cv'] = null;
                }else{
                    $this->_data['cv'] = $row['cv'];
                }
                if(empty($row['id_emp'])){
                    $this->_data['id_emp'] = null;
                }else{
                    $this->_data['id_emp'] = (int)$row['id_emp'];
                }
                $this->_data['autorizado'] = (int)$row['autorizado'];
                $this->_data['sitlab'] = (int)$row['sitlab'];
                $this->_data['sitcol'] = (int)$row['sitcol'];
                if(empty($row['titulacion'])){
                    $this->_data['titulacion'] = null;
                }else{
                    $this->_data['titulacion'] = $row['titulacion'];
                }
                if(empty($row['master'])){
                    $this->_data['master'] = null;
                }else{
                    $this->_data['master'] = $row['master'];
                }
                $this->_data['empleo'] = (int)$row['empleo'];
                $this->_data['experiencia'] = (int)$row['experiencia'];
                if(empty($row['especialidad'])){
                    $this->_data['especialidad'] = null;
                }else{
                    $this->_data['especialidad'] = $row['especialidad'];
                }
                $this->_data['jornada'] = (int)$row['jornada'];
                if(empty($row['alta'])){
                    $this->_data['alta'] = date('d-m-Y');
                }else{
                    $this->_data['alta'] = $row['alta'];
                }
                if(empty($row['baja'])){
                    $this->_data['baja'] = null;
                }else{
                    $this->_data['baja'] = $row['baja'];
                }
                if(empty($row['baja'])){
                    $this->_data['baja'] = null;
                }else{
                    $this->_data['baja'] = $row['baja'];
                }
                if(empty($row['sincro'])){
                    $this->_data['sincro'] = null;
                }else{
                    $this->_data['sincro'] = $row['sincro'];
                }
                if(empty($row['pago_pendiente'])){
                    $this->_data['pago_pendiente'] = null;
                }else{
                    $this->_data['pago_pendiente'] = (int)$row['pago_pendiente'];
                }
                if(empty($row['delegacion'])){
                    $this->_data['delegacion'] = null;
                }else{
                    $this->_data['delegacion'] = $row['delegacion'];
                }
                if(empty($row['observaciones'])){
                    $this->_data['observaciones'] = null;
                }else{
                    $this->_data['observaciones'] = $row['observaciones'];
                }
                if(empty($row['acceso_gestor_documental'])){
                    $this->_data['acceso_gestor_documental'] = null;
                }else{
                    $this->_data['acceso_gestor_documental'] = (int)$row['acceso_gestor_documental'];
                }
                if(empty($row['profesional_direccion'])){
                    $this->_data['profesional_direccion'] = null;
                }else{
                    $this->_data['profesional_direccion'] = $row['profesional_direccion'];
                }
                if(empty($row['profesional_cp'])){
                    $this->_data['profesional_cp'] = null;
                }else{
                    $this->_data['profesional_cp'] = $row['profesional_cp'];
                }
                if(empty($row['profesional_poblacion'])){
                    $this->_data['profesional_poblacion'] = null;
                }else{
                    $this->_data['profesional_poblacion'] = $row['profesional_poblacion'];
                }
                if(empty($row['profesional_provincia'])){
                    $this->_data['profesional_provincia'] = null;
                }else{
                    $this->_data['profesional_provincia'] = (int)$row['profesional_provincia'];
                }

                break;
            case 3:
                if(empty($row['pago_pendiente'])){
                    $this->_data['pago_pendiente'] = null;
                }else{
                    $this->_data['pago_pendiente'] = (int)$row['pago_pendiente'];
                }
                if(empty($row['delegacion'])){
                    $this->_data['delegacion'] = null;
                }else{
                    $this->_data['delegacion'] = $row['delegacion'];
                }
                if(empty($row['observaciones'])){
                    $this->_data['observaciones'] = null;
                }else{
                    $this->_data['observaciones'] = $row['observaciones'];
                }
                $this->_data['sitcol'] = (int)$row['sitcol'];
                $this->_data['sexo'] = (int)$row['sexo'];
                break;
            case 4:
                if(empty($row['nombre'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['nombre'] = $row['nombre'];
                }
                if(empty($row['apellidos'])){
                    $this->_data['apellidos'] = null;
                }else{
                    $this->_data['apellidos'] = $row['apellidos'];
                }
                if(empty($row['telefono'])){
                    $this->_data['telefono'] = null;
                }else{
                    $this->_data['telefono'] = $row['telefono'];
                }
                if(empty($row['email'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['email'] = $row['email'];
                }
                $this->_data['sexo'] = (int)$row['sexo'];
                if(empty($row['nacimiento'])){
                    $this->_data['nacimiento'] = null;
                }else{
                    $this->_data['nacimiento'] = $row['nacimiento'];
                }
                if((int)$this->_id == 0){
                    if(empty($row['clave'])){
                        $this->_data['clave'] = Utilidades::generaPass();
                    }else{
                        $this->_data['clave'] = $row['clave'];
                    }
                }
                if(empty($row['cv'])){
                    $this->_data['cv'] = null;
                }else{
                    $this->_data['cv'] = $row['cv'];
                }
                $this->_data['sitlab'] = (int)$row['sitlab'];
                if(empty($row['titulacion'])){
                    $this->_data['titulacion'] = null;
                }else{
                    $this->_data['titulacion'] = $row['titulacion'];
                }
                if(empty($row['master'])){
                    $this->_data['master'] = null;
                }else{
                    $this->_data['master'] = $row['master'];
                }
                $this->_data['empleo'] = (int)$row['empleo'];
                $this->_data['experiencia'] = (int)$row['experiencia'];
                if(empty($row['especialidad'])){
                    $this->_data['especialidad'] = null;
                }else{
                    $this->_data['especialidad'] = $row['especialidad'];
                }
                $this->_data['jornada'] = (int)$row['jornada'];
                break;
        }
        return $algun_valor_vacio;
    }

    public function save(){
        $db_table = new Usuarios();
        $this->dates();
        if($this->_id == 0){
            $clave=$this->_data['clave'];
            unset($this->_data['clave']);
            $db_table->insert($this->_data);
            $this->_id = $db_table->getLastInsertValue();
            $this->setClave($clave);
        }else{
            $db_table->update($this->_data,$this->_pk.' = '.$this->_id);
        }
        $this->dates();
        return $this->_id;
    }

    public function setClave($clave){
        $db_table = new Usuarios();
        $test = $db_table->setDescrypt($this->_pk.' = ' . $this->_id, $this->getLlave(), $clave);
    }

    public function remove(){
        $db_men = new Menores();
        $db_ofe = new Ofertas();
        $db_can = new Candidaturas();
        $db_ins = new Inscripciones();
        $db_insc = new Inscritos();
        $num = $db_men->num('id_usu = '.$this->_id);
        $num += $db_ofe->num('id_usu = '.$this->_id);
        $num += $db_can->num('id_usu = '.$this->_id);
        $num += $db_ins->num('id_usu = '.$this->_id);
        $num += $db_insc->num('id_usu = '.$this->_id);
        if($num == 0){
            $this->removeCv();
            $db_table = new Usuarios();
            $db_table->delete($this->_pk.' = '.$this->_id);
            $dev = 125;
        }else{
            $dev = 136;
        }
        return $dev;
    }

    public function get($elemento){
        if($elemento == 'id'){
            return $this->_id;
        }else if($elemento == 'nombre-completo'){
            return $this->_data['nombre'].' '.$this->_data['apellidos'];
        }else if($elemento == 'data'){
            return $this->_data;
        }else if($elemento == 'desencripta_clave'){
            $db_table = new Usuarios();
            $clave = null;
            if((int)$this->_id > 0){
                $clave = $db_table->getDescrypt($this->_pk.' = ' . $this->_id, $this->getLlave());
            }
            return $clave;
        }else if($elemento == 'empresa'){
            return new Empresa($this->_data['id_emp']);
        }else if($elemento == 'estimado-nombre'){
            if($this->_data['sexo'] == 1){
                $msg = 'Estimada ';
            }else{
                $msg = 'Estimado ';
            }
            $msg .= $this->_data['nombre'];
            return $msg;
        }else{
            if(!empty($this->_data[$elemento])){
                return html_entity_decode(htmlspecialchars(stripslashes($this->_data[$elemento])),ENT_NOQUOTES);
            }else{
                return null;
            }
        }
    }

    private function dates(){
        if(isset($this->_data['nacimiento'])){
            $this->_data['nacimiento'] = Utilidades::giraFecha($this->_data['nacimiento']);
        }
        if(isset($this->_data['alta'])){
            $this->_data['alta'] = Utilidades::giraFecha($this->_data['alta']);
        }
        if(isset($this->_data['baja'])){
            $this->_data['baja'] = Utilidades::giraFecha($this->_data['baja']);
        }
        if(isset($this->_data['sincro'])){
            $this->_data['sincro'] = Utilidades::giraFecha($this->_data['sincro']);
        }
    }

    public function revisaEmailYNif(){
        $ko = 0;
        $db = new Usuarios();
        
        $coincide_email = (int)$db->num('email LIKE "'.$this->_data['email'].'"');
        if(($coincide_email > 0 && $this->_id == 0) || $coincide_email > 1){
            $ko += 1;
        }
        $coincide_nif = (int)$db->num('nif LIKE "'.$this->_data['nif'].'"');
        if(($coincide_nif > 0 && $this->_id == 0) || $coincide_nif > 1){
            $ko += 2;
        }
        return $ko;
    }

    public function asignarCodigoContable($codigo,$guardar = 0){
        $this->_data['cuenta_contable'] = $codigo;
        if((int)$guardar){
            $this->save();
        }
    }
    
    public function setIdEmp($id_emp){
        $this->_data['id_emp'] = $id_emp;
        $this->_data['autorizado'] = 1;
        $this->save();
    }

    public function validaClave($pass){
        $error = null;
        $array_errores = array();
        $array_errores[0] = 1;
        $array_errores[1] = 1;
        $array_errores[2] = 1;
        $array_errores[3] = 1;
        $array_errores[4] = 1;
        $array_errores[5] = 1;
        $array_errores[6] = 1;

        if($pass == ''){
            $error = 'Debe rellenar la contrase&ntilde;a';
            $array_errores[0] = 0;
        }
        if(strlen($pass) < 4){
            $error = 'La contrase&ntilde;a debe contener al menos 4 car&aacute;cteres.';
            $array_errores[1] = 0;

        }
        if(strpos($pass, $this->_data['nif']) !== false){
                $error = 'La contrase&ntilde;a no debe contener el dni.';
                $array_errores[2] = 0;

        }
        if (!preg_match('`[a-z]`',$pass)){
                $error = "La clave debe tener al menos una letra min&uacute;scula";
                $array_errores[3] = 0;

        }
        if (!preg_match('`[A-Z]`',$pass)){
                $error = "La clave debe tener al menos una letra may&uacute;scula";
                $array_errores[4] = 0;
        }
        if (!preg_match('`[0-9]`',$pass)){
                $error = "La clave debe tener al menos un caracter num&eacute;rico";
                $array_errores[5] = 0;
        }
        $regex = '[[:punct:]]';

        if (!preg_match('/'.$regex.'/', $pass)) {
            $error = "La clave debe tener al menos un s&iacute;mbolo '&, =, ?, ! ...'";
                $array_errores[6] = 0;
        }

        return $array_errores;
    }

    public function getLlave(){
        return 'c0i1a0c523';
    }

    public function registro($acceso){
        $acciones = ['entrada','salida'];
        $fecha = date("d-m-Y H:i:s");
        $data = [
            'id_log'    => 0,
            'accion'    => $acciones[(int)$acceso],
            'fecha'     => $fecha,
            'id_usu'    => $this->_id,
            'comentario'    => null
        ];
        $log = new Log();
        $log->set($data);
        $log->save();
        return true;
    }

    public function setCv($fichero){
        $data = [];
        $data['cv'] = $fichero;

        $db_usuario = new Usuarios();
        $db_usuario->update($data, 'id_usu = ' . $this->_id);
    }

    public function removeCv(){
        @unlink(self::FILE_DIRECTORY_CV . $this->_data['cv']);

        $data = [];
        $data['cv'] = null;

        $db_usuario = new Usuarios();
        $db_usuario->update($data, 'id_usu = ' . $this->_id);
    }

    public function setAutorizado($id_emp){
        $data = [
            'id_emp'    => $id_emp,
            'autorizado'    => 1
        ];
        $db_usuario = new Usuarios();
        $db_usuario->update($data, 'id_usu = ' . $this->_id);
    }
    
    public function eliminaRelacionEmpresa(){
        $data = [
            'id_emp'        => null,
            'autorizado'    => 0
        ];
        $db_usuario = new Usuarios();
        $db_usuario->update($data, 'id_usu = ' . $this->_id);
    }

    public function setEmpresa($id_emp){
        $data = [
            'id_emp'    => $id_emp,
            'autorizado'    => 0
        ];
        $db_usuario = new Usuarios();
        $db_usuario->update($data, 'id_usu = ' . $this->_id);
    }
}