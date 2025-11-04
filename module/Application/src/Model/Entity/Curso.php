<?php
namespace Application\Model\Entity;
use Application\Model\Utility\Utilidades;

class Curso{
    
    protected $_id;
    protected $_data;
    protected $_pk = 'id_cur';

    public function __construct($cod = 0){
        $this->_id = (int)$cod;
        $this->_data = array();
        if($this->_id > 0){
            $db_table = new Cursos();
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
                if(isset($row['publicacion'])){
                    $row['publicacion'] = Utilidades::giraFecha($row['publicacion']);
                }
                if(isset($row['comienzo'])){
                    $row['comienzo'] = Utilidades::giraFecha($row['comienzo']);
                }
                if(isset($row['fin'])){
                    $row['fin'] = Utilidades::giraFecha($row['fin']);
                }
                if(isset($row['sincro'])){
                    $row['sincro'] = Utilidades::giraFecha($row['sincro']);
                }
                $row = $this->decodeHtml($row);
            case 2:
                $this->_id = (int)$row[$this->_pk];
                if(empty($row['nombre'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['nombre'] = $row['nombre'];
                }
                if(empty($row['horario'])){
                    $this->_data['horario'] = null;
                }else{
                    $this->_data['horario'] = $row['horario'];
                }
                if(empty($row['ubicacion'])){
                    $this->_data['ubicacion'] = null;
                }else{
                    $this->_data['ubicacion'] = $row['ubicacion'];
                }
                if(empty($row['enlubi'])){
                    $this->_data['enlubi'] = null;
                }else{
                    $this->_data['enlubi'] = $row['enlubi'];
                }
                if(empty($row['id_cat'])){
                    $algun_valor_vacio++;
                }else{
                    $this->_data['id_cat'] = (int)$row['id_cat'];
                }
                $this->_data['estado'] = (int)$row['estado'];
                if(empty($row['descripcion'])){
                    $this->_data['descripcion'] = null;
                }else{
                    $this->_data['descripcion'] = $row['descripcion'];
                }
                if(empty($row['objetivos'])){
                    $this->_data['objetivos'] = null;
                }else{
                    $this->_data['objetivos'] = $row['objetivos'];
                }
                if(empty($row['dirigido'])){
                    $this->_data['dirigido'] = null;
                }else{
                    $this->_data['dirigido'] = $row['dirigido'];
                }
                if(empty($row['dinamica'])){
                    $this->_data['dinamica'] = null;
                }else{
                    $this->_data['dinamica'] = $row['dinamica'];
                }
                if(empty($row['metodologia'])){
                    $this->_data['metodologia'] = null;
                }else{
                    $this->_data['metodologia'] = $row['metodologia'];
                }
                if(empty($row['programa'])){
                    $this->_data['programa'] = null;
                }else{
                    $this->_data['programa'] = $row['programa'];
                }
                if(empty($row['profesorado'])){
                    $this->_data['profesorado'] = null;
                }else{
                    $this->_data['profesorado'] = $row['profesorado'];
                }
                if(empty($row['precios'])){
                    $this->_data['precios'] = null;
                }else{
                    $this->_data['precios'] = $row['precios'];
                }
                if(empty($row['informacion'])){
                    $this->_data['informacion'] = null;
                }else{
                    $this->_data['informacion'] = $row['informacion'];
                }
                if(empty($row['precio_col'])){
                    $this->_data['precio_col'] = null;
                }else{
                    $this->_data['precio_col'] = str_replace(',','.',$row['precio_col']);
                }
                if(empty($row['precio_otr'])){
                    $this->_data['precio_otr'] = null;
                }else{
                    $this->_data['precio_otr'] = str_replace(',','.',$row['precio_otr']);
                }
                $this->_data['tipo'] = (int)$row['tipo'];
                $this->_data['colegiados'] = (int)$row['colegiados'];
                if(empty($row['publicacion'])){
                    $this->_data['publicacion'] = null;
                }else{
                    $this->_data['publicacion'] = $row['publicacion'];
                }
                if(empty($row['comienzo'])){
                    $this->_data['comienzo'] = null;
                }else{
                    $this->_data['comienzo'] = $row['comienzo'];
                }
                if(empty($row['fin'])){
                    $this->_data['fin'] = null;
                }else{
                    $this->_data['fin'] = $row['fin'];
                }
                if(empty($row['sincro'])){
                    $this->_data['sincro'] = null;
                }else{
                    $this->_data['sincro'] = $row['sincro'];
                }
                $this->_data['post_id'] = (int)$row['post_id'];
                $this->_data['beca'] = (int)$row['beca'];
                if(empty($row['informacion_certificados'])){
                    $this->_data['informacion_certificados'] = null;
                }else{
                    $this->_data['informacion_certificados'] = $row['informacion_certificados'];
                }
                if(empty($row['resumen'])){
                    $this->_data['resumen'] = null;
                }else{
                    $this->_data['resumen'] = $row['resumen'];
                }
                if(empty($row['no_sincronizar_web']) || $this->_data['estado'] != 3){
                    $this->_data['no_sincronizar_web'] = 0;
                }else{
                    $this->_data['no_sincronizar_web'] = (int)$row['no_sincronizar_web'];
                }
                break;
        }
        return $algun_valor_vacio;
    }
	
    public function save(){
        $db_table = new Cursos();
        $this->dates();
        $row = $this->_data;
        $row = $this->encodeHtml($row);
        if($this->_id == 0){
            $db_table->insert($row);
            $this->_id = $db_table->getLastInsertValue();
        }else{
            $db_table->update($row,$this->_pk.' = '.$this->_id);
        }
        $this->dates();
        return $this->_id;
    }
	
    public function remove(){
        $db_ins = new Inscripciones();
        $num = $db_ins->numParticipantes('id_cur = '.$this->_id);
        $num += $db_ins->numInscripciones('id_cur = '.$this->_id);
        if($num == 0){
            $db_table = new Cursos();
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
        }else if($elemento == 'categoria'){
            return new Categoria((int)$this->_data['id_cat']);
        }else if($elemento == 'nombre-sin-tildes'){
            return Utilidades::cleanString($this->_data['nombre'],1);
        }else{
            if(!empty($this->_data[$elemento])){
                return html_entity_decode(htmlspecialchars(stripslashes($this->_data[$elemento])),ENT_NOQUOTES);
            }else{
                return null;
            }
        }
    }
        
    private function dates(){
        if(isset($this->_data['publicacion'])){
            $this->_data['publicacion'] = Utilidades::giraFecha($this->_data['publicacion']);
        }
        
        if(isset($this->_data['comienzo'])){
            $this->_data['comienzo'] = Utilidades::giraFecha($this->_data['comienzo']);
        }
        
        if(isset($this->_data['fin'])){
            $this->_data['fin'] = Utilidades::giraFecha($this->_data['fin']);
        }
        
        if(isset($this->_data['sincro'])){
            $this->_data['sincro'] = Utilidades::giraFecha($this->_data['sincro']);
        }
    }

    /*
     * Genera las distintas inscripciones sobre un curso
     * @param:
     *      $usuario (object Usuario): Usuario creador
     *      $usuarios (array): Usuarios a registrarse en un curso
     *
     * @return:
     *      $id_ins (int): Identificador de la inscripcion
     * */
    public function generaInscripcionCursoEvento($usuario, $usuarios){
        $data_inscripcion = [];
        $data_inscripcion['id_ins'] = 0;
        $data_inscripcion['fecha'] = date('d-m-Y H:i:s');
        $data_inscripcion['id_usu'] = $usuario->get('id');
        if($usuario->get('id_emp') > 0 && (int)$usuario->get('autorizado')){
            $data_inscripcion['id_emp'] = $usuario->get('id_emp');
        }else{
            $data_inscripcion['id_emp'] = null;
        }
        $data_inscripcion['id_cur'] = $this->_id;
        $data_inscripcion['beca'] = 0;
        $data_inscripcion['importe'] = null;
        $data_inscripcion['observaciones'] = null;
        $data_inscripcion['estado'] = 0;
        $data_inscripcion['pago'] = 0;

        $db_inscripciones = new Inscripciones();
        $inscripciones = $db_inscripciones->get('id_usu = ' . $usuario->get('id'), 'id_ins DESC', 1, 0);
        if(count($inscripciones) > 0){
            $inscripcion_ref = reset($inscripciones);
            $data_inscripcion['nombre'] = $inscripcion_ref->get('nombre')?$inscripcion_ref->get('nombre'):$usuario->get('nombre-completo');
            $data_inscripcion['cif'] = $inscripcion_ref->get('cif')?$inscripcion_ref->get('cif'):$usuario->get('nif');
            $data_inscripcion['direccion'] = $inscripcion_ref->get('direccion');
            $data_inscripcion['cp'] = $inscripcion_ref->get('cp');
            $data_inscripcion['localidad'] = $inscripcion_ref->get('localidad');
            $data_inscripcion['provincia'] = $inscripcion_ref->get('provincia');
            $data_inscripcion['nombre_empresa'] = $inscripcion_ref->get('nombre_empresa');
            $data_inscripcion['cif_empresa'] = $inscripcion_ref->get('cif_empresa');
            $data_inscripcion['cp_empresa'] = $inscripcion_ref->get('cp_empresa');
            $data_inscripcion['direccion_empresa'] = $inscripcion_ref->get('direccion_empresa');
            $data_inscripcion['localidad_empresa'] = $inscripcion_ref->get('localidad_empresa');
            $data_inscripcion['provincia_empresa'] = $inscripcion_ref->get('provincia_empresa');
        }else{
            $data_inscripcion['nombre'] = $usuario->get('nombre');
            $data_inscripcion['cif'] = $usuario->get('nif');
        }

        $inscripcion = new Inscripcion(0);
        $algun_valor_vacio = $inscripcion->set($data_inscripcion);
        $id_ins = $inscripcion->save();

        $total_importe = 0;
        foreach($usuarios as $id_tra):
            $usuario = new Usuario($id_tra);
            $importe = 0;
            if($usuario->get('rol') == 'c4legiado'){
                $importe = $this->_data['precio_col'];
            }else{
                $importe = $this->_data['precio_otr'];
            }
            $total_importe += $importe;
            $data_inscrito = [];
            $data_inscrito['id_ui'] = 0;
            $data_inscrito['id_ins'] = $inscripcion->get('id');
            $data_inscrito['id_usu'] = $id_tra;
            $data_inscrito['sitcol'] = $usuario->get('sitcol');
            $data_inscrito['sitlab'] = $usuario->get('sitlab');
            $data_inscrito['importe'] = $importe;
            $data_inscrito['diploma'] = null;
            $data_inscrito['asistencia'] = 0;

            $data_inscrito['fecha'] = date('d-m-Y');
            $inscrito = new Inscrito(0);
            $algun_valor_vacio = $inscrito->set($data_inscrito);
            if($algun_valor_vacio == 0){
                $id_ui = $inscrito->save();
            }
        endforeach;

        $data_update_inscripcion = [];
        $data_update_inscripcion['importe'] = $total_importe;
        $db_inscripciones->update($data_update_inscripcion, 'id_ins = ' . $inscripcion->get('id'));

        return $id_ins;
    }

    /*
     * Genera las distintas inscripciones sobre un curso
     * @param:
     *      $usuario (object Usuario): Usuario creador
     *      $usuarios (array): Usuarios a registrarse en un curso
     *
     * @return:
     *      $id_ins (int): Identificador de la inscripcion
     * */
    public function generaInscricionEventoInfantil($usuario, $menores){
        $data_inscripcion = [];
        $data_inscripcion['id_ins'] = 0;
        $data_inscripcion['fecha'] = date('d-m-Y H:i:s');
        $data_inscripcion['id_usu'] = $usuario->get('id');
        $data_inscripcion['id_emp'] = null;
        $data_inscripcion['id_cur'] = $this->_id;
        $data_inscripcion['beca'] = 0;
        $data_inscripcion['importe'] = null;
        $data_inscripcion['observaciones'] = null;
        $data_inscripcion['estado'] = 0;
        $data_inscripcion['pago'] = 0;

        $inscripcion = new Inscripcion(0);
        $algun_valor_vacio = $inscripcion->set($data_inscripcion);
        $id_ins = $inscripcion->save();

        $total_importe = 0;
        foreach($menores as $id_men):
            $menor = new Menor($id_men);

            $importe = $this->_data['precio_otr'];
            $total_importe += $importe;

            $data_participante = [];
            $data_participante['id_par'] = 0;
            $data_participante['id_ins'] = $inscripcion->get('id');
            $data_participante['id_men'] = $id_men;
            $data_participante['fecha'] = date('d-m-Y');
            $data_participante['importe'] = $importe;

            $participante = new Participante(0);
            $algun_valor_vacio = $participante->set($data_participante);
            if($algun_valor_vacio == 0){
                $id_par = $participante->save();
            }
        endforeach;

        $db_inscripciones = new Inscripciones();
        $data_update_inscripcion = [];
        $data_update_inscripcion['importe'] = $total_importe;
        $db_inscripciones->update($data_update_inscripcion, 'id_ins = ' . $inscripcion->get('id'));

        return $id_ins;
    }

    public function getMappingWP($crud = 'create'){
        if($crud == 'create'){
            $categoria = $this->get('categoria');
            if($this->_data['estado'] == 0 || $this->_data['estado'] == 3){
                $status = 'draft';
            }else{
                $status = 'publish';
            }
            $data = [
                'title' => $this->_data['nombre'],
                'status' => $status,
                'content' => '',
                'cursos-categorias'  => $categoria->get('taxonomy_id'),
                'fields' => $this->getFieldsWp(),
            ];
        }else if($crud == 'remove'){
            $data = [
                'status' => 'draft',
            ];
        }
        return $data;
    }

    public function getFieldsWP($categoria = false){
        $fields = [
            'courses_comienzo' => (isset($this->_data['comienzo']) && $this->_data['comienzo'] != '')?(date('Ymd', strtotime($this->_data['comienzo']))):(''),
            'courses_fin' => (isset($this->_data['fin']) && $this->_data['fin'] != '')?(date('Ymd', strtotime($this->_data['fin']))):(''),
            'courses_horario' => $this->_data['horario'],
            'courses_ubicacion' => $this->_data['ubicacion'],
            'courses_enlubi' => $this->_data['enlubi'],
            'courses_estado' => ($this->_data['estado'] - 1),
            'courses_descripcion' =>  $this->_data['descripcion'],
            'courses_objetivos' => $this->_data['objetivos'],
            'courses_dirigido' =>  $this->_data['dirigido'],
            'courses_dinamica' =>  $this->_data['dinamica'],
            'courses_metodologia' =>  $this->_data['metodologia'],
            'courses_programa' =>  $this->_data['programa'],
            'courses_profesorado' =>  $this->_data['profesorado'],
            'courses_precios' =>  $this->_data['precios'],
            'courses_informacion' =>  $this->_data['informacion'],
            'courses_precio_col' =>  $this->_data['precio_col'],
            'courses_precio_otr' =>  $this->_data['precio_otr'],
            'courses_tipo' =>  $this->_data['tipo'],
            'courses_colegiados' =>  $this->_data['colegiados'],
            'courses_id_cur' =>  $this->_id,
        ];

        if($categoria){
            $categoria = $this->get('categoria');
            $fields['title'] = $this->_data['nombre'];
            $fields['courses_categoria'] = $categoria->getData();
        }

        return $fields;
    }
    
    private function decodeHtml($row){
        $campos = ['descripcion','objetivos','dirigido','dinamica','programa','profesorado','precios','informacion','enlubi'];
        foreach($campos as $key):
            $row[$key] = html_entity_decode($row[$key],ENT_QUOTES | ENT_HTML5,'UTF-8');
        endforeach;
        return $row;
    }
    
    private function encodeHtml($row){
        $campos = ['descripcion','objetivos','dirigido','dinamica','programa','profesorado','precios','informacion','enlubi'];
        foreach($campos as $key):
            $row[$key] = htmlentities($row[$key],ENT_QUOTES | ENT_HTML5,'UTF-8');
        endforeach;
        return $row;
    }

}