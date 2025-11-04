<?php
namespace Backend\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Zend\Filter\StripTags;
use Zend\Json\Json;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Oferta;
use Application\Model\Entity\Ofertas;
use Application\Model\Entity\Candidatura;
use Application\Model\Entity\Candidaturas;
use Application\Model\Entity\Sector;
use Application\Model\Entity\Sectores;
use Application\Model\Utility\Exportar;
use Application\Model\Utility\Utilidades;

class EmpleoController extends AbstractActionController{
	
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
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertas','v1' => 1));
    }
	
    public function ofertasAction(){
        $this->layout()->title = 'Ofertas';
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if($idm == 525){
            $msg_ok = 'La oferta ha sido borrada correctamente.';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar la oferta porque tiene otras entidades relacionadas.';
        }
        $db_ofertas = new Ofertas();
        $orderby = 'id_ofe DESC';
	if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            $data['titulo'] = $filter->filter($this->request->getPost('titulo'));
            $data['estado'] = (int)$this->request->getPost('estado');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['id_sec'] = (int)$this->request->getPost('id_sec');
            
            $this->_container->ofer_buscador = $data;
            $this->_container->ofer_page = 0;
        }else{
            // Eliminar filtros de búsqueda
            if($idm == 114){
                if(isset($this->_container->ofer_buscador)){
                    unset($this->_container->ofer_buscador);
                    $this->_container->ofer_page = 0;
                }
            }
        }
        // Paginación
        $page = (int)$this->params()->fromRoute('v1',-1);
    	if($page == -1){
            if(isset($this->_container->ofer_page)){
                $page= $this->_container->ofer_page;
            }else{
                $page = 1;
                $this->_container->ofer_page = $page;
            }
    	}else{
            $this->_container->ofer_page = $page;
    	}
        if($page == 0){
            $page = 1;
        }
        $offset = 50*($page - 1);
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if(isset($this->_container->ofer_buscador)){
            $where = Utilidades::generaCondicion('ofertas', $this->_container->ofer_buscador);
            $this->_container->ofer_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->ofer_buscador;
        }else{
            $buscador = array('id_ofe' => 0,'titulo' => null,'estado' => -1,'fechaDesde' => null,'fechaHasta' => null,'id_emp' => null,'id_sec' => null);
            $where = null;
        }
        // Leer de base de datos
        $ofertas = $db_ofertas->getOfertas($where,$orderby,50,$offset);
        $num = $db_ofertas->numOfertas($where);
        if($num == 0){
            if(isset($this->_container->ofer_buscador)){
                $msg_error = 'No hay ninguna oferta con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna oferta guardada.';
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
            'ofertas'  =>  $ofertas
        );
        return new ViewModel($view);
    }
	
    public function ofertaAction(){
        $this->layout()->title = 'Nueva | Oferta';
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';
    	$set = 1;
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            if($this->_usuario->get('rol') == '4dmin'){
                $data['id_emp'] =(int)$this->request->getPost('id_emp');
            }else{
                $data['id_emp'] =(int)$this->_usuario->get('id_emp');
            }

            $data['titulo'] = $filter->filter($this->request->getPost('titulo'));
            $data['descripcion'] = $filter->filter($this->request->getPost('descripcion'));
            $data['info'] = $filter->filter($this->request->getPost('info'));
            $data['plazas'] = (int)$this->request->getPost('plazas');
            $data['categoria'] = $filter->filter($this->request->getPost('categoria'));
            $data['experiencia'] = (int)$this->request->getPost('experiencia');
            $data['estado'] = (int)$this->request->getPost('estado');
            $data['fecha'] = $filter->filter($this->request->getPost('fecha'));
            $data['id_usu'] = (int)$this->request->getPost('id_usu');
            $oferta = new Oferta(0);
            $algun_valor_vacio = $oferta->set($data);
            if($algun_valor_vacio > 0){
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_ofe'];
            }else{
                $id = $oferta->save();
                if($data['id_ofe'] == 0){
                    $msg_ok = 'La oferta ha sido creada correctamente.';
                }else{
                    $msg_ok = 'La oferta ha sido actualizada correctamente.';
                }
            }
        }else{
            $id = (int)$this->params()->fromRoute('v1',0);
            $oferta = new Oferta($id);
            $idm = (int)$this->params()->fromRoute('v3', 0);
            if($idm == 1){
                $msg_ok = 'La oferta ha sido publicada.';
                $oferta->setEstado(1);
            }else if($idm == 3){
                $msg_ok = 'La oferta ha sido rechazada.';
                $oferta->setEstado(3);
            }
        }

        if($this->_usuario->get('rol') != '4dmin' && $oferta->get('id') > 0 &&
            ($oferta->get('id_emp') != $this->_usuario->get('id_emp') || $this->_usuario->get('autorizado') != 1)){
            return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertasempleo', 'v1' => $oferta->get('id') ));
        }

        if($this->_usuario->get('rol') != '4dmin'){
            $empresa = $this->_usuario->get('empresa');
        }else{
            $empresa = null;
        }

        if($id > 0){
            $this->layout()->title = $oferta->get('titulo').' | Oferta';
            // Candidaturas relacionadas
            $pagc = (int)$this->params()->fromRoute('v2',-1);
            if($pagc == -1){
                if(isset($this->_container->uins_page)){
                    $pagc= $this->_container->uins_page;
                }else{
                    $pagc = 0;
                    $this->_container->uins_page = $pagc;
                }
            }else{
                $this->_container->uins_page = $pagc;
                $tab = 'candidaturas';
            }
            if($pagc == 0){
                $pagc = 1;
            }
            $offsetc = 50*($pagc - 1);
            $db_can = new Candidaturas();
            $candidaturas = $db_can->getCandidaturas('id_ofe = '.$id,'id_ofe DESC',50,$offsetc);
            $numc = $db_can->num('id_ofe = '.$id);
        }else{
            $candidaturas= [];
            $numc = 0;
            $pagc = 0;
        }
        $view = array(
            'usuario'       =>  $this->_usuario,
            'oferta'        =>  $oferta,
            'ok'            =>  $msg_ok,
            'ko'            =>  $msg_error,
            'candidaturas'  =>  $candidaturas,
            'empresa'       =>  $empresa,
            'numc'          =>  $numc,
            'pagc'          =>  $pagc,
            'tab_oferta'    =>  $tab,
        );
        return new ViewModel($view);
        
    }

    public function ofertasempleoAction(){
        $this->layout()->title = 'Ofertas de empleo';
        $oferta_seleccionada = (int)$this->params()->fromRoute('v1', 0);
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if($idm == 100){
            $msg_error = 'No se ha podido presentar la candidatura. Inténtelo más tarde.';
        }else if($idm == 101){
            $msg_error = 'Debe de rellenar todos los datos del formulario para presentar la candidatura.';
        }else if($idm == 200){
            $msg_ok = 'La candidatura se ha presentado correctamente.';
        }

        $db_ofertas = new Ofertas();
        $orderby = 'fecha DESC';
        $where = 'estado = 1';
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['categoria'] = (int)$this->request->getPost('categoria');

            $this->_container->ofer_empleo_buscador = $data;
        }else{
            // Eliminar filtros de búsqueda
            if($idm == 114){
                if(isset($this->_container->ofer_empleo_buscador)){
                    unset($this->_container->ofer_empleo_buscador);
                }
            }
        }

        // Construcción de la condición ($where) según los parámetros de búsqueda
        if(isset($this->_container->ofer_empleo_buscador)){
            if($this->_container->ofer_empleo_buscador['categoria'] > 0){
                (isset($where))?($where .= ' AND categoria = ' . $this->_container->ofer_empleo_buscador['categoria']):($where = 'categoria = ' . $this->_container->ofer_empleo_buscador['categoria']);
            }
            $this->_container->ofer_empleo_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->ofer_empleo_buscador;
        }else{
            $buscador = array('categoria' => 0);
        }
        // Leer de base de datos
        $ofertas = $db_ofertas->getOfertas($where,$orderby);
        $num = $db_ofertas->numOfertas($where);

        $db_candidaturas = new Candidaturas();
        $candidaturas = $db_candidaturas->getCandidaturas('id_usu = ' . $this->_usuario->get('id'));

        $candidaturasCursosUsuario = [];
        foreach($candidaturas as $candidatura):
            $candidaturasCursosUsuario[] = $candidatura['id_ofe'];
        endforeach;

        if($num == 0){
            if(isset($this->_container->ofer_empleo_buscador)){
                $msg_error = 'No hay ninguna oferta con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna oferta de emplo guardada.';
            }
        }

        // Preparar datos para la vista
        $view = [
            'usuario'               => $this->_usuario,
            'buscador'              => $buscador,
            'ok'                    => $msg_ok,
            'ko'                    => $msg_error,
            'num'                   => $num,
            'candidaturas_usuario'  => $candidaturasCursosUsuario,
            'ofertas'               => $ofertas,
            'oferta_seleccionada'   => $oferta_seleccionada
        ];
        return new ViewModel($view);
    }

    public function presentarcandidaturaAction(){
        $ok = 100;
        if($this->request->isPost()) {
            $candidatura = new Candidatura(0);
            $nombreDirectorio = $candidatura::FILE_DIRECTORY_CV;

            $data = [];
            $filter = new StripTags();

            $httpadapter = new \Zend\File\Transfer\Adapter\Http();
            $filesize = new \Zend\Validator\File\Size(array('min' => '0kB', 'max' => '10MB')); //1KB
            $extension = new \Zend\Validator\File\Extension(array('extension' => array('pdf', 'doc', 'docx')));
            $httpadapter->setValidators(array($filesize, $extension));

            $files = $httpadapter->getFileInfo('cv');
            if ($httpadapter->isValid()) {
                $fichero = $files['cv']['name'];
                $ext = pathinfo($fichero, PATHINFO_EXTENSION);
                $fichero = time() . "." . $ext;
                $httpadapter->addFilter('Rename', $fichero);
                $httpadapter->setDestination($nombreDirectorio);

                if ($httpadapter->receive($files['cv']['name'])) {
                    $data['id_can'] = 0;
                    $data['id_usu'] = $this->_usuario->get('id');
                    $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
                    $data['comentario'] = $filter->filter($this->request->getPost('comentario'));
                    $data['fecha'] = date('d-m-Y H:i:s');
                    $data['cv'] = $fichero;
                    $data['estado'] = 1;

                    $algunValorVacio = $candidatura->set($data, 2);
                    if($algunValorVacio == 0){
                        $id_cand = $candidatura->save();
                        $ok = 200;
                    }
                }
            }else{
                $ok = 101;
            }
        }
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertasempleo','v1' => 0,'v2' => $ok));
    }
	
    public function borrarofertaAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Oferta($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertas','v1' => (int)$this->_container->ofer_page,'v2' => $ok));
    }
    
    public function xlsofertasAction(){
        $where = Utilidades::generaCondicion('ofertas', $this->_container->ofer_buscador);
        $db = new Ofertas();
        $objects = $db->getOfertas($where,'titulo');
        $objPHPExcel = Exportar::ofertas($objects);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }
	
    public function candidaturasAction(){
        $this->layout()->title = 'Candidaturas';
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if($idm == 525){
            $msg_ok = 'La candidatura ha sido borrada correctamente.';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar la candidatura porque tiene otras entidades relacionadas.';
        }
        $db_candidaturas = new Candidaturas();
        $orderby = 'usuariosNombre ASC';
	if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_can'] = (int)$this->request->getPost('id_can');
            $data['id_usu'] =(int)$this->request->getPost('id_usu');
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['candidaturasEstado'] = (int)$this->request->getPost('candidaturasEstado');
            $this->_container->cand_buscador = $data;
            $this->_container->cand_page = 0;
        }else{
            // Eliminar filtros de búsqueda
            if($idm == 114){
                if(isset($this->_container->cand_buscador)){
                    unset($this->_container->cand_buscador);
                    $this->_container->cand_page = 0;
                }
            }
        }
        // Paginación
        $page = (int)$this->params()->fromRoute('v1',-1);
    	if($page == -1){
            if(isset($this->_container->cand_page)){
                $page= $this->_container->cand_page;
            }else{
                $page = 1;
                $this->_container->cand_page = $page;
            }
    	}else{
            $this->_container->cand_page = $page;
    	}
        if($page == 0){
            $page = 1;
        }
        $offset = 50*($page - 1);
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if(isset($this->_container->cand_buscador)){
            $where = Utilidades::generaCondicion('candidaturas', $this->_container->cand_buscador);
            $this->_container->cand_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->cand_buscador;
        }else{
            $buscador = array('id_can' => 0,'id_usu' => null,'id_ofe' => null,'id_emp' => null,'fechaDesde' => null,'fechaHasta' => null,'candidaturasEstado' => -1);
            $where = null;
        }

        if($this->_usuario->get('rol') == 'c4legiado'){
            (isset($where))?($where .= ' AND id_usu = ' . $this->_usuario->get('id')):($where = 'id_usu = ' . $this->_usuario->get('id'));
        }

        // Leer de base de datos
        $candidaturas = $db_candidaturas->getCandidaturas($where,$orderby,50,$offset);
        $num = $db_candidaturas->numCandidaturas($where);
        if($num == 0){
            if(isset($this->_container->cand_buscador)){
                $msg_error = 'No hay ninguna candidatura con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna candidatura guardada.';
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
            'candidaturas'  =>  $candidaturas
        );
        return new ViewModel($view);
    }
	
    public function candidaturaAction(){
        $this->layout()->title = 'Nueva | Candidatura';
        
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            $data['id_can'] = (int)$this->request->getPost('id_can');
            $data['id_usu'] =(int)$this->request->getPost('id_usu');
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            $data['comentario'] = $filter->filter($this->request->getPost('comentario'));
            $data['fecha'] = $filter->filter($this->request->getPost('fecha'));
            $data['estado'] = (int)$this->request->getPost('estado');
            $candidatura = new Candidatura(0);
            $algun_valor_vacio = $candidatura->set($data);
            if($algun_valor_vacio > 0){
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_can'];
            }else{
                $id = $candidatura->save();
                if($data['id_can'] == 0){
                    $msg_ok = 'La candidatura ha sido creada correctamente.';
                }else{
                    $msg_ok = 'La candidatura ha sido actualizada correctamente.';
                }
            }
        }else{
            $id = (int)$this->params()->fromRoute('v1',0);
            $candidatura = new Candidatura($id);
            $idm = (int)$this->params()->fromRoute('v2', 0);
            if($idm == 2){
                $msg_ok = 'La candidatura ha sido preseleccionada.';
                $candidatura->setEstado(2);
            }else if($idm == 3){
                $msg_ok = 'La candidatura ha sido seleccionada.';
                $candidatura->setEstado(3);
            }else if($idm == 4){
                $msg_ok = 'La candidatura ha sido descartada.';
                $candidatura->setEstado(4);
            }
        }
        $oferta = $candidatura->get('oferta');
        if($this->_usuario->get('rol') != '4dmin'){
            $allow = false;
            if($candidatura->get('id') > 0 && (($oferta->get('id_emp') == $this->_usuario->get('id_emp')
                && $this->_usuario->get('autorizado') == 1) || ($this->_usuario->get('rol') == 'c4legiado'
                        && $this->_usuario->get('id') == $candidatura->get('id_usu')))){
                $allow = true;
            }
            if(!$allow){
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'candidaturas'));
            }
        }
        if($id > 0){
            $this->layout()->title = 'Candidatura '.str_pad($candidatura->get('id'),5,'0',STR_PAD_LEFT);            
        }
        $view = array(
            'usuario'       =>  $this->_usuario,
            'candidatura'   =>  $candidatura,
            'ok'            =>  $msg_ok,
            'ko'            =>  $msg_error,
            'tab_candidatura'   =>  $tab,
            'cliente'       => $candidatura->get('candidato'),
            'oferta'        => $oferta
        );
        return new ViewModel($view);
    }
	
    public function borrarcandidaturaAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Candidatura($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'candidaturas','v1' => (int)$this->_container->cand_page,'v2' => $ok));
    }
    
    public function xlscandidaturasAction(){
        $where = Utilidades::generaCondicion('candidaturas', $this->_container->cand_buscador);
        $db = new Candidaturas();
        $objects = $db->getCandidaturas($where,['usuariosNombre','apellidos']);
        $objPHPExcel = Exportar::candidaturas($objects);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }
    
    public function sectoresAction(){
        $this->layout()->title = 'Sectores';
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if($idm == 525){
            $msg_ok = 'El sector ha sido borrado correctamente.';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar el sector porque tiene registros relacionados.';
        }
        $db = new Sectores();
        $orderby = 'nombre ASC';
        if($this->request->isPost()){
            $data = [];
            $boton = $this->request->getPost('boton');
            if($boton == 'buscar'){
                $data['id_sec'] = (int)$this->request->getPost('id_sec');
                $this->_container->sec_buscador = $data;
            }else if($boton == 'guardar-todos'){
                $id_secs =  $this->request->getPost('id_sec');
                $nombres =  $this->request->getPost('nombre');
                foreach($id_secs as $i => $id_sec):
                    if(!empty($nombres[$i])){
                        $data = [
                            'id_sec'    => $id_sec,
                            'nombre'    => $nombres[$i]
                        ];
                        $object = new Sector(0);
                        $object->set($data);
                        $object->save();
                    }
                endforeach;
                $msg_ok = 'Los sectores han sido actualizados correctamente.';
            }else{
                $i = $boton;
                $id_secs =  $this->request->getPost('id_sec');
                $nombres =  $this->request->getPost('nombre');
                if(!empty($nombres[$i])){
                    $data = [
                        'id_sec'    => $id_secs[$i],
                        'nombre'    => $nombres[$i]
                    ];
                    $object = new Sector(0);
                    $object->set($data);
                    $object->save();
                }
                $msg_ok = 'El sector ha sido actualizado correctamente.';
            }
        }else{
            // Eliminar filtros de búsqueda
            if($idm == 114){
                if(isset($this->_container->sec_buscador)){
                    unset($this->_container->sec_buscador);
                    $this->_container->sec_page = 0;
                }
            }
        }
        // Paginación
        $page = (int)$this->params()->fromRoute('v1',-1);
    	if($page == -1){
            if(isset($this->_container->sec_page)){
                $page= $this->_container->sec_page;
            }else{
                $page = 1;
                $this->_container->sec_page = $page;
            }
    	}else{
            $this->_container->sec_page = $page;
    	}
        if($page == 0){
            $page = 1;
        }
        $offset = 50*($page - 1);
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if(isset($this->_container->sec_buscador)){
            $where = Utilidades::generaCondicion('sectores', $this->_container->sec_buscador);
            $this->_container->sec_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->sec_buscador;
        }else{
            $buscador = array('id_sec' => 0,'nombre' => null);
            $where = null;
        }
        // Leer de base de datos
        $objects = $db->get($where,$orderby,50,$offset);
        $num = $db->num($where);
        if($num == 0){
            if(isset($this->_container->sec_buscador)){
                $msg_error = 'No hay ningún sector con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ningún sector guardado.';
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
            'sectores'  =>  $objects
        );
        return new ViewModel($view);
    }
    
    public function borrarsectorAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Sector($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'sectores','v1' => (int)$this->_container->sec_page,'v2' => $ok));
    }

    public function xaAction(){
        $ajax = (int)$this->params()->fromRoute('v1',0);
        $answer = [];
        if($ajax == 1){
            $term = $_GET['q'];
            $db = new Ofertas();
            $where = 'titulo LIKE "%'.$term.'%"';
            if($this->_usuario->get('rol') != '4dmin'){
                $where .= ' AND id_emp = ' . $this->_usuario->get('id_emp');
            }
            $objects = $db->get($where,'titulo');
            if(count($objects)>0){
                foreach($objects as $object):
                    $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('titulo')];
                endforeach;
            }else{
                $answer[] = ["id"=>"0","text"=>"No existen resultados."];
            }
    	}else if($ajax == 2){
            $term = (int)$_GET['q'];
            $object = new Oferta($term);
            if($object->get('id')>0){
                $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('titulo')];
            }else{
                $answer[] = ["id"=>"0","text"=>""];
            }
        }else if($ajax == 3 && $this->_usuario->get('rol') == '4dmin'){
            $term = $_GET['q'];
            $db = new Candidaturas();
            $objects = $db->getCandidaturas('nombre LIKE "%'.$term.'%" OR apellidos LIKE "%'.$term.'%"', ['nombre','apellidos']);
            if(count($objects)>0){
                foreach($objects as $object):
                    $answer[] = ["id"=>$object['id_can'],"text"=>$object['nombre'].' '.$object['nombre']];
                endforeach;
            }else{
                $answer[] = ["id"=>"0","text"=>"No existen resultados."];
            }
        }else if($ajax == 4 && $this->_usuario->get('rol') == '4dmin'){
            $term = (int)$_GET['q'];
            $db = new Candidaturas();
            $objects = $db->getCandidaturas('id_can = '.$term);
            if(count($objects)>0){
                $object = current($objects);
                $answer[] = ["id"=>$object['id_can'],"text"=>$object['nombre'].' '.$object['nombre']];
            }else{
                $answer[] = ["id"=>"0","text"=>"No existen resultados."];
            }
    	}else if($ajax == 5){
            $term = $_GET['q'];
            $db = new Sectores();
            $objects = $db->get('nombre LIKE "%'.$term.'%"','nombre');
            if(count($objects)>0){
                foreach($objects as $object):
                    $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('nombre')];
                endforeach;
            }else{
                $answer[] = ["id"=>"0","text"=>"No existen resultados."];
            }
    	}else if($ajax == 6){
            $term = (int)$_GET['q'];
            $object = new Sector($term);
            if($object->get('id')>0){
                $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('nombre')];
            }else{
                $answer[] = ["id"=>"0","text"=>""];
            }
    	}
        return $this->getResponse()->setContent(Json::encode($answer));
    }
}