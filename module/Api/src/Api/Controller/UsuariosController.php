<?php

/*
 * Servicio API para Comanies
 * */

namespace Api\Controller;

use Api\Controller\AbstractRestfulJsonController;
use Zend\View\Model\JsonModel;
use Zend\Filter\StripTags;
use Application\Model\Entity\Usuarios;
use Application\Model\Entity\Usuario;
use Application\Model\Utility\Utilidades;

class UsuariosController extends AbstractRestfulJsonController
{

    private $configApi;

    public function __construct($config_api)
    {
        $this->configApi = $config_api;
    }

    /*
     * Action usado por método GET recupera un listado sin poner el identificador en la ruta
     *
     * /api/usuarios?order=apellidos&order_type=desc&offset=0&nombre=Prueba
     * */
    public function getList(){
        if(!$this->accessApi()->authorizationHeader($this->getRequest(), $this->configApi)){
            return new JsonModel(['data' => ['code' => '403', 'msg' => 'El usuario no tiene permisos.']]);
        }

        $db_usu = new Usuarios();
        $where = "sitcol = 0" ;
        // Where params
        if($this->params()->fromQuery('nombre')){
            $where .= ' AND nombre LIKE "%' . $this->params()->fromQuery('nombre') . '%"';
        }
        if($this->params()->fromQuery('apellidos')){
            $where .= ' AND apellidos LIKE "%' . $this->params()->fromQuery('apellidos') . '%"';
        }
        if($this->params()->fromQuery('colegiado')){
            $where .= ' AND colegiado LIKE "%' . $this->params()->fromQuery('colegiado') . '%"';
        }
        if($this->params()->fromQuery('titulacion')){
            $where .= ' AND titulacion LIKE "%' . $this->params()->fromQuery('titulacion') . '%"';
        }
        if($this->params()->fromQuery('delegacion')){
            $where .= ' AND delegacion LIKE "' . $this->params()->fromQuery('delegacion') . '"';
        }

        /*
         * ['Estudiante','Trabajador por cuenta ajena','Autónomo','Empresario','Desempleado','Jubilado']
         * */
        if($this->params()->fromQuery('sitlab')){
            // $where .= ' AND sitlab = ' . (int)$this->params()->fromQuery('sitlab') . '';
        }

        // Query params
        if($this->params()->fromQuery('order')){
            $orderby = $this->params()->fromQuery('order');
        }else{
            $orderby = 'nombre';
        }
        if($this->params()->fromQuery('order_type')){
            $orderby .= ' ' . $this->params()->fromQuery('order_type');
        }else{
            $orderby .= ' ASC';
        }
        if($this->params()->fromQuery('offset')){
            $offset = (int)$this->params()->fromQuery('offset');
        }else{
            $offset = 0;
        }

        $orderby = ['apellidos','nombre'];

        $usuarios = $db_usu->get($where,$orderby,5000,$offset);
        $num_usuarios = $db_usu->num($where);

        $response['code'] = '200';
        $response['where'] = $where;
        $response['data']['total'] = $num_usuarios;
        foreach($usuarios as $usuario):
            $response['data']['usuarios'][] = $usuario->get('data');
        endforeach;

        return new JsonModel(
            $response
        );
    }

    /*
     * Action usado por método POST para crear entidades
     * */
    public function create($data){
        if(!$this->accessApi()->authorizationHeader($this->getRequest(), $this->configApi)){
            return new JsonModel(['data' => ['code' => '403', 'msg' => 'El usuario no tiene permisos.']]);
        }

        $filter = new StripTags();
        foreach($data as &$filter_data):
            $filter_data = $filter->filter($filter_data);
        endforeach;

        $info = json_encode($data);
        $id = 0;

        if(isset($data['nif'])){
            $db_usuarios = new Usuarios();
            $users = $db_usuarios->get('nif LIKE "' . $data['nif'] . '" OR colegiado LIKE "' . $data['colegiado'] . '" OR email LIKE "' . $data['email'] . '"','id_usu DESC');
            if(count($users) > 0){
                $user = reset($users);

                $data['id_usu'] = (int)$user->get('id');
                $data['rol'] = 'c4legiado';
                $data['alta'] = $user->get('alta');
                $baja = $user->get('baja');
                $data['cv'] = $user->get('cv');
                $data['id_emp'] = $user->get('id_emp');
                $data['autorizado'] = $user->get('autorizado');
                $data['sitlab'] = $user->get('sitlab');
                $data['sincro'] = date('d-m-Y H:i:s');
                $data['empleo'] = $user->get('empleo');
                $data['experiencia'] = $user->get('experiencia');
                $data['especialidad'] = $user->get('especialidad');
                $data['jornada'] = $user->get('jornada');
                $data['acceso_gestor_documental'] = (int)$user->get('acceso_gestor_documental');
            }else{
                $user = new Usuario(0);

                $data['id_usu'] = 0;
                $data['rol'] = 'c4legiado';
                $data['alta'] = date('d-m-Y');
                $baja = null;
                $data['cv'] = null;
                $data['id_emp'] = null;
                $data['autorizado'] = 0;
                $data['sitlab'] = 0;
                $data['sincro'] = date('d-m-Y H:i:s');
                $data['empleo'] = 0;
                $data['experiencia'] = 0;
                $data['especialidad'] = null;
                $data['jornada'] = 0;
                $data['acceso_gestor_documental'] = 0;
            }
            if(strtoupper($data['sexo']) == 'M' || $data['sexo'] == 0){
                $data['sexo'] = 0;
            }else{
                $data['sexo'] = 1;
            }
            if(strtoupper($data['baja']) == 'N' || $data['baja'] == 0){
                $data['baja'] = null;
            }else{
                /*if($baja == null){
                    $data['baja'] = date('Y-m-d');
                }else{
                    $data['baja'] = $baja;
                }*/
                // No lo damos de baja, lo pasamos a usuario registrado
                $data['baja'] = null;
                $data['rol'] = 'us4ario';
            }

            if(isset($data['nacimiento'])){
                $data['nacimiento'] = date('d-m-Y', strtotime($data['nacimiento']));
                if($data['nacimiento'] == '01-01-1970'){
                    $data['nacimiento'] = null;
                }
            }
            switch (strtoupper($data['tipo'])) {
                case 'C':
                    $data['sitcol'] = 0;
                    break;
                case 'P':
                    $data['sitcol'] = 1;
                    break;
                case 'TA':
                    $data['sitcol'] = 2;
                    break;
                case 'EA':
                    $data['sitcol'] = 3;
                    break;
                case 'PA':
                    $data['sitcol'] = 4;
                    break;
                case 'AH':
                    $data['sitcol'] = 6;
                    break;
                case 'V':
                    $data['sitcol'] = 7;
                    break;
                case 'SA':
                    $data['sitcol'] = 8;
                    break;
                default:
                    $data['sitcol'] = 5;
                    break;
            }
            switch (strtoupper($data['situacionLaboral'])) {
                case 'EMPLEADO':
                    $data['sitlab'] = 1;
                    break;
                case 'DESEMPLEADO':
                    $data['sitlab'] = 4;
                    break;
            }
            $data['profesional_direccion'] = trim($data['direccionProfesional']);
            unset($data['direccionProfesional']);
            $data['profesional_cp'] = trim($data['cpProfesional']);
            unset($data['cpProfesional']);
            $data['profesional_poblacion'] = trim($data['poblacionProfesional']);
            unset($data['poblacionProfesional']);
            if(strlen($data['profesional_cp']) == 5){
                $data['profesional_provincia'] = substr($data['profesional_cp'],0,2);
            }

            $usuario = new Usuario(0);
            $algun_valor_vacio = $usuario->set($data);

            if($algun_valor_vacio > 0){
                $msg = 'No se debe dejar ningún valor obligatorio sin rellenar.';
                $code = '400';
            }else{
                $id = $usuario->save();
                if($data['id_usu'] > 0){
                    if(isset($data['clave']) && $data['clave'] != ''){
                        $usuario->setClave($data['clave']);
                    }
                    $msg = 'El usuario ha sido actualizado correctamente.';
                }else{
                    $msg = 'El usuario ha sido creado correctamente.';
                }
                $code = '200';
                $db_usu = new Usuarios();
                $db_usu->update(['info' => $info],'id_usu = '.$id);
            }

        }else{
            $msg = 'El nif es obligatorio.';
            $code = '400';
        }

        return new JsonModel(['data' => ['code' => $code, 'msg' => $msg]]);
    }

}