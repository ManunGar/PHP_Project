<?php
namespace Backend\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Zend\Filter\StripTags;
use Zend\Json\Json;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Usuarios;
use Application\Model\Entity\Ofertas;
use Application\Model\Entity\Empresa;
use Application\Model\Entity\Empresas;
use Application\Model\Entity\Inscripciones;
use Application\Model\Utility\Exportar;
use Application\Model\Utility\Utilidades;

class EmpresasController extends AbstractActionController{
	
    protected $_usuario;
    protected $_container;
    protected $_tipo;

    public function __construct(){
        $auth = new AuthenticationService();
        $identity = $auth->getIdentity();
        if ($auth->hasIdentity()) {
            $usuario = new Usuario($identity->id_usu);
            $this->_usuario = $usuario;
            $this->_container = new Container('namespace');
        }
    }
	
    public function indexAction(){
        $this->layout()->title = 'Empresas';
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if($idm == 525){
            $msg_ok = 'La empresa ha sido borrada correctamente.';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar la empresa porque tiene otras entidades relacionadas.';
        }
        $db_empresas = new Empresas();
        $orderby = 'empresasNombre ASC';
	if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['cif'] = $filter->filter($this->request->getPost('cif'));
            $data['id_sec'] = (int)$this->request->getPost('id_sec');
            $data['estado'] = (int)$this->request->getPost('estado');
            $this->_container->empr_buscador = $data;
            $this->_container->empr_page = 0;
        }else{
            // Eliminar filtros de búsqueda
            if($idm == 114){
                if(isset($this->_container->empr_buscador)){
                    unset($this->_container->empr_buscador);
                    $this->_container->empr_page = 0;
                }
            }
        }
        // Paginación
        $page = (int)$this->params()->fromRoute('v1',-1);
    	if($page == -1){
            if(isset($this->_container->empr_page)){
                $page= $this->_container->empr_page;
            }else{
                $page = 1;
                $this->_container->empr_page = $page;
            }
    	}else{
            $this->_container->empr_page = $page;
    	}
        if($page == 0){
            $page = 1;
        }
        $offset = 50*($page - 1);
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if(isset($this->_container->empr_buscador)){
            $where = Utilidades::generaCondicion('empresas', $this->_container->empr_buscador);
            $this->_container->empr_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->empr_buscador;
        }else{
            $buscador = array('id_emp' => 0,'empresasNombre' => null,'cif' => null,'id_sec' => null,'estado' => -1);
            $where = null;
        }
        // Leer de base de datos
        $empresas = $db_empresas->getEmpresas($where,$orderby,50,$offset);
        $num = $db_empresas->num($where);
        if($num == 0){
            if(isset($this->_container->empr_buscador)){
                $msg_error = 'No hay ninguna empresa con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna empresa guardada.';
            }
        }
        // Preparar datos para la vista
    	$view = array(
            'usuario'   =>  $this->_usuario,
            'buscador'  =>  $buscador,
            'page'      =>  $page,
            'ok'        =>  $msg_ok,
            'ko'        =>  $msg_error,
            'num'       =>  $num,
            'empresas'  =>  $empresas
        );
        return new ViewModel($view);
    }
	
    public function fichaAction(){
        $this->layout()->title = 'Nueva | Empresa';
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['razonsocial'] = $filter->filter($this->request->getPost('razonsocial'));
            $data['cif'] = $filter->filter($this->request->getPost('cif'));
            if($this->_usuario->get('rol') == '4dmin'){
                $data['estado'] = (int)$this->request->getPost('estado');
            }else{
                $empresa = new Empresa($data['id_emp']);
                $data['estado'] = $empresa->get('estado');
            }

            $data['id_sec'] = (int)$this->request->getPost('id_sec');
            $data['alta'] = $filter->filter($this->request->getPost('alta'));
            $data['web'] = $filter->filter($this->request->getPost('web'));
            $data['direccion'] = $filter->filter($this->request->getPost('direccion'));
            $data['cp'] = $filter->filter($this->request->getPost('cp'));
            $data['localidad'] = $filter->filter($this->request->getPost('localidad'));
            $data['provincia'] = $filter->filter($this->request->getPost('provincia'));
            $data['email'] = $filter->filter($this->request->getPost('email'));
            $data['telefono'] = $filter->filter($this->request->getPost('telefono'));
            $empresa = new Empresa(0);
            $algun_valor_vacio = $empresa->set($data);
            if($algun_valor_vacio > 0){
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_emp'];
            }else{
                $id = $empresa->save();
                if($data['id_emp'] == 0){
                    $msg_ok = 'La empresa ha sido creada correctamente.';
                    if($this->_usuario->get('rol') != '4dmin'){
                        $this->_usuario->setIdEmp($id);
                    }
                }else{
                    $msg_ok = 'La empresa ha sido actualizada correctamente.';
                }
            }
        }else{
            $id = (int)$this->params()->fromRoute('v1',0);
            $empresa = new Empresa($id);
            $idm = (int)$this->params()->fromRoute('v5', 0);
            if($idm == 1){
                $msg_ok = 'La empresa ha sido activada.';
                $empresa->setEstado(1);
            }else if($idm == 2){
                $msg_ok = 'La empresa ha sido rechazada.';
                $empresa->setEstado(2);
            }else if($idm == 111){
                $msg_ok = 'Se ha relacionado correctamente al usuario con la empresa.';
            }else if($idm == 112){
                $msg_error = 'El NIF indicado no corresponde con ningún usuario.';
            }else if($idm == 113){
                $msg_ok = 'El usuario ya no está relacionado con la empresa.';
            }else if($idm == 114){
                $msg_error = 'No se ha podido eliminar la relación entre el usuario y la empresa.';
            }
        }

        if($this->_usuario->get('rol') != '4dmin'){
            $empresaUser = $this->_usuario->get('empresa');
            $allow = false;
            if(($empresaUser->get('id') > 0 && $this->_usuario->get('autorizado') == 1) || $this->_usuario->get('id_emp') == 0){
                $allow = true;
            }
            if($allow && $empresa->get('id') != $empresaUser->get('id')){
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empresas', 'action' => 'ficha', 'v1' => $empresaUser->get('id')));
            }else if(!$allow){
                return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
            }
        }

        if($id > 0){
            $this->layout()->title = $empresa->get('nombre').' | Empresa';            
            // Ofertas relacionadas
            $pago = (int)$this->params()->fromRoute('v2',-1);
            if($pago == -1){
                if(isset($this->_container->uins_page)){
                    $pago= $this->_container->uins_page;
                }else{
                    $pago = 0;
                    $this->_container->uins_page = $pago;
                }
            }else{
                $this->_container->uins_page = $pago;
                $tab = 'ofertas';
            }
            if($pago == 0){
                $pago = 1;
            }
            $offseto = 50*($pago - 1);
            $db_ofe = new Ofertas();
            $ofertas = $db_ofe->getOfertas('id_emp = '.$id,'id_emp DESC',50,$offseto);
            $numo = $db_ofe->num('id_emp = '.$id);
            // Inscripciones relacionadas
            $pagi = (int)$this->params()->fromRoute('v3',-1);
            if($pagi == -1){
                if(isset($this->_container->uins_page)){
                    $pagi= $this->_container->uins_page;
                }else{
                    $pagi = 0;
                    $this->_container->uins_page = $pagi;
                }
            }else{
                $this->_container->uins_page = $pagi;
                $tab = 'inscripciones';
            }
            if($pagi == 0){
                $pagi = 1;
            }
            $offseti = 50*($pagi - 1);
            $db_ins = new Inscripciones();
            $inscripciones = $db_ins->getInscripciones('id_emp = '.$id,'id_emp DESC',50,$offseti);
            $numi = $db_ins->num('id_emp = '.$id);
            // Usuarios relacionadas
            $pagu = (int)$this->params()->fromRoute('v4',-1);
            if($pagu == -1){
                if(isset($this->_container->uins_page)){
                    $pagu= $this->_container->uins_page;
                }else{
                    $pagu = 0;
                    $this->_container->uins_page = $pagu;
                }
            }else{
                $this->_container->uins_page = $pagu;
                $tab = 'usuarios';
            }
            if($pagu == 0){
                $pagu = 1;
            }
            $offsetu = 50*($pagu - 1);
            $db_usu = new Usuarios();
            $usuarios = $db_usu->get('id_emp = '.$id,'id_emp DESC',50,$offsetu);
            $numu = $db_usu->num('id_emp = '.$id);
        }else{
            $ofertas = [];
            $numo = 0;
            $pago = 0;
            $inscripciones = [];
            $numi = 0;
            $pagi = 0;
            $usuarios = [];
            $numu = 0;
            $pagu = 0;
        }
        
        $view = array(
            'usuario'       => $this->_usuario,
            'empresa'       => $empresa,
            'ok'            => $msg_ok,
            'ko'            => $msg_error,
            'ofertas'       => $ofertas,
            'numo'          => $numo,
            'pago'          => $pago,
            'inscripciones' => $inscripciones,
            'numi'          => $numi,
            'pagi'          => $pagi,
            'usuarios'      => $usuarios,
            'numu'          => $numu,
            'pagu'          => $pagu,
            'tab_empresa'   => $tab,
            'autorizado'    => $empresa->get('autorizado')
        );
        return new ViewModel($view);
    }
	
    public function borrarAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Empresa($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empresas', 'action' => 'index','v1' => (int)$this->_container->empr_page,'v2' => $ok));
    }
    
    public function xlsAction(){
        $where = Utilidades::generaCondicion('empresas', $this->_container->empr_buscador);
        $db = new Empresas();
        $objects = $db->getEmpresas($where,'empresasNombre');
        $objPHPExcel = Exportar::empresas($objects);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }
    
    public function usuariosAction(){
        if($this->request->isPost()){
            $filter = new StripTags();
            $nif = $filter->filter($this->request->getPost('nif'));
            $id_emp = (int)$this->request->getPost('id_emp');
            $db_usu = new Usuarios();
            $usuario = $db_usu->getByNif($nif);
            if((int)$usuario->get('id')){
                $usuario->setEmpresa($id_emp);
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empresas', 'action' => 'ficha','v1' => (int)$id_emp,'v2' => 0,'v3' => 0,'v4' => 0,'v5' => 111));
            }else{
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empresas', 'action' => 'ficha','v1' => (int)$id_emp,'v2' => 0,'v3' => 0,'v4' => 0,'v5' => 112));
            }
        }else{
            $id_usu = (int)$this->params()->fromRoute('v1',0);
            $id_emp = (int)$this->params()->fromRoute('v2',0);
            $usuario = new Usuario($id_usu);
            if((int)$usuario->get('id')){
                $usuario->eliminaRelacionEmpresa();
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empresas', 'action' => 'ficha','v1' => (int)$id_emp,'v2' => 0,'v3' => 0,'v4' => 0,'v5' => 113));
            }else{
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empresas', 'action' => 'ficha','v1' => (int)$id_emp,'v2' => 0,'v3' => 0,'v4' => 0,'v5' => 114));
            }
            
        }
    }

    public function xaAction(){
        $ajax = (int)$this->params()->fromRoute('v1',0);
        $answer = [];
        if($ajax == 1){
            $term = $_GET['q'];
            $db = new Empresas();
            $objects = $db->getEmpresas('empresasNombre LIKE "%'.$term.'%"','empresasNombre');
            if(count($objects)>0){
                foreach($objects as $object):
                    $answer[] = ["id"=>$object['id_emp'],"text"=>$object['empresasNombre']];
                endforeach;
            }else{
                $answer[] = ["id"=>"0","text"=>"No existen resultados."];
            }
    	}else if($ajax == 2){
            $term = (int)$_GET['q'];
            $object = new Empresa($term);
            if($object->get('id')>0){
                $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('nombre')];
            }else{
                $answer[] = ["id"=>"0","text"=>""];
            }
    	}
        return $this->getResponse()->setContent(Json::encode($answer));
    }
    
    public function importarAction(){
        $db = new Empresas();
        $db->importarEmpresas();
        die();
    }
}