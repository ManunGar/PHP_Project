<?php
namespace Backend\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Zend\Filter\StripTags;
use Zend\Json\Json;
use Application\Model\Entity\Candidaturas;
use Application\Model\Entity\Inscripciones;
use Application\Model\Entity\Menor;
use Application\Model\Entity\Menores;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Usuarios;
use Application\Model\Entity\Carpeta;
use Application\Model\Entity\Carpetas;
use Application\Model\Entity\Permiso;
use Application\Model\Entity\Permisos;
use Application\Model\Utility\Exportar;
use Application\Model\Utility\Utilidades;

class UsuariosController extends AbstractActionController{
	
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
        $this->layout()->title = 'Usuarios';
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if($idm == 125){
            $msg_ok = 'El usuario ha sido borrado correctamente.';
        }else if($idm == 136){
            $msg_error = 'No se ha podido borrar el usuario porque tiene otras entidades relacionadas.';
        }
        
        $db_usuarios = new Usuarios();
        $orderby = 'nombre ASC';
	if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_usu'] = (int)$this->request->getPost('id_usu');
            $data['nif'] = $filter->filter($this->request->getPost('nif'));
            $data['colegiado'] = $filter->filter($this->request->getPost('colegiado'));
            $data['telefono'] = $filter->filter($this->request->getPost('telefono'));
            $data['email'] = $filter->filter($this->request->getPost('email'));
            $data['sitcol'] = (int)$this->request->getPost('sitcol');
            $data['rol'] = $filter->filter($this->request->getPost('rol'));
            $data['autorizado'] = (int)$this->request->getPost('autorizado');
            
            $this->_container->usua_buscador = $data;
            $this->_container->usua_page = 0;
        }else{
            // Eliminar filtros de búsqueda
            if($idm == 114){
                if(isset($this->_container->usua_buscador)){
                    unset($this->_container->usua_buscador);
                    $this->_container->usua_page = 0;
                }
            }
        }
        // Paginación
        $page = (int)$this->params()->fromRoute('v1',-1);
    	if($page == -1){
            if(isset($this->_container->usua_page)){
                $page= $this->_container->usua_page;
            }else{
                $page = 1;
                $this->_container->usua_page = $page;
            }
    	}else{
            $this->_container->usua_page = $page;
    	}
        if($page == 0){
            $page = 1;
        }
        $offset = 50*($page - 1);
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if(isset($this->_container->usua_buscador)){
            $where = Utilidades::generaCondicion('usuarios', $this->_container->usua_buscador);
            $this->_container->usua_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->usua_buscador;
        }else{
            $buscador = array('id_usu' => 0,'nif' => null,'colegiado' => null,'telefono' => null,'email' => null,'sitcol' => -1,'rol' => -1,'autorizado' => -1);
            $where = null;
        }
        // Leer de base de datos
        $usuarios = $db_usuarios->get($where,$orderby,50,$offset);
        $num = $db_usuarios->num($where);
        if($num == 0){
            if(isset($this->_container->usua_buscador)){
                $msg_error = 'No hay ning&uacute;n usuario con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ning&uacute;n usuario guardado.';
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
            'usuarios'  =>  $usuarios
        );
        return new ViewModel($view);
    }
	
    public function fichaAction(){
        $this->layout()->title = 'Nuevo | Usuario';
        
        $msg_ok = null;
        $msg_error = null;
        $array_errores = [];
        $clave = null;
        $tab = 'default';
    	$set = 1;
        $id_mens_ins= (int)$this->params()->fromRoute('v6', 0);
        if($id_mens_ins == 525){
            $msg_ok = 'La inscripción ha sido borrado correctamente.';
            $tab = 'default';
        }else if($id_mens_ins == 536){
            $msg_error = 'No se ha podido borrar la inscripción porque tiene algún registro relacionado.';
            $tab = 'default';
        }else if($id_mens_ins == 547){
            $msg_ok = 'La inscripción ha sido actualizado correctamente.';
            $tab = 'default';
        }else if($id_mens_ins == 548){
            $msg_ok = 'El CV ha sido subido correctamente.';
            $tab = 'default';
        }else if($id_mens_ins == 549){
            $msg_error = 'El CV no tiene une extensión válida.';
            $tab = 'default';
        }else if($id_mens_ins == 550){
            $msg_error = 'Ha ocurrido un error al subir el CV.';
            $tab = 'default';
        }else if($id_mens_ins == 551){
            $msg_ok = 'El CV ha sido borrado correctamente.';
            $tab = 'default';
        }else if($id_mens_ins == 552){
            $msg_ok = 'Los accesos a los directorios han sido actualizados correctamente.';
            $tab = 'gestor-documental';
        }


        $idm = (int)$this->params()->fromRoute('v5', 0);
        if($idm == 525){
            $msg_ok = 'El menor ha sido borrado correctamente.';
            $tab = 'menores';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar el menor porque está inscrito en algún evento.';
            $tab = 'menores';
        }else if($idm == 547){
            $msg_ok = 'Los menores han sido actualizados correctamente.';
            $tab = 'menores';
        }

        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            $set = (int)$this->request->getPost('set');
            if($this->_usuario->get('rol') == '4dmin'){
                $data['id_usu'] = (int)$this->request->getPost('id_usu');
            }else{
                $data['id_usu'] = (int)$this->_usuario->get('id');
            }
            if($set == 1){
                if($this->_usuario->get('rol') == '4dmin'){
                    $case = 2;
                    $usuario = new Usuario(0);
                    $data['observaciones'] = $filter->filter($this->request->getPost('observaciones'));
                    $data['pago_pendiente'] = (int)$this->request->getPost('pago_pendiente');
                    $data['sincro'] = $filter->filter($this->request->getPost('sincro'));
                    $data['id_emp'] = (int)$this->request->getPost('id_emp');
                    $data['autorizado'] = (int)$this->request->getPost('autorizado');
                    $data['rol'] = $filter->filter($this->request->getPost('rol'));
                    $data['colegiado'] = $filter->filter($this->request->getPost('colegiado'));
                    $data['nif'] = $filter->filter($this->request->getPost('nif'));
                    $data['alta'] = $filter->filter($this->request->getPost('alta'));
                    $data['sitcol'] = (int)$this->request->getPost('sitcol');
                    $data['delegacion'] = $filter->filter($this->request->getPost('delegacion'));
                    $data['baja'] = $filter->filter($this->request->getPost('baja'));
                    $data['acceso_gestor_documental'] = (int)$this->request->getPost('acceso_gestor_documental');
                }else{
                    $case = 4;
                    $usuario = new Usuario($data['id_usu']);
                }
                $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
                $data['apellidos'] = $filter->filter($this->request->getPost('apellidos'));
                $data['telefono'] = $filter->filter($this->request->getPost('telefono'));
                $data['email'] = $filter->filter($this->request->getPost('email'));
                $data['sexo'] = (int)$this->request->getPost('sexo');
                $data['nacimiento'] = $filter->filter($this->request->getPost('nacimiento'));
                $data['clave'] = $filter->filter($this->request->getPost('clave'));
                $data['cv'] = $filter->filter($this->request->getPost('cv'));
                $data['sitlab'] = (int)$this->request->getPost('sitlab');
                $data['titulacion'] = $filter->filter($this->request->getPost('titulacion'));
                $data['master'] = $filter->filter($this->request->getPost('master'));
                $data['empleo'] = (int)$this->request->getPost('empleo');
                $data['experiencia'] = (int)$this->request->getPost('experiencia');
                $data['especialidad'] = $filter->filter($this->request->getPost('especialidad'));
                $data['jornada'] = (int)$this->request->getPost('jornada');
                $data['profesional_direccion'] = $filter->filter($this->request->getPost('profesional_direccion'));
                $data['profesional_cp'] = $filter->filter($this->request->getPost('profesional_cp'));
                $data['profesional_poblacion'] = $filter->filter($this->request->getPost('profesional_poblacion'));
                $data['profesional_provincia'] = $filter->filter($this->request->getPost('profesional_provincia'));
                
                
                $algun_valor_vacio = $usuario->set($data,$case);
                $ko = $usuario->revisaEmailYNif();
                if($algun_valor_vacio > 0){
                    $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                    $id = $data['id_usu'];
                }else if($ko == 1){
                    $msg_error = 'No se ha guardado el usuario porque el e-mail introducido ya pertenece a otro cliente/usuario.';
                    $id = $data['id_usu'];
                }else if($ko == 2){
                    $msg_error = 'No se ha guardado el usuario porque el NIF introducido ya pertenece a otro cliente/usuario.';
                    $id = $data['id_usu'];
                }else if($ko == 3){
                    $msg_error = 'No se ha guardado el usuario porque el e-mail y el NIF introducidos ya pertenece a otro cliente/usuario.';
                    $id = $data['id_usu'];
                }else{
                    $id = $usuario->save();
                    if($data['id_usu'] == 0){
                        $msg_ok = 'El usuario ha sido creado correctamente.';
                    }else{
                        $msg_ok = 'El usuario ha sido actualizado correctamente.';
                    }
                }
            }else{
                $data['clave'] = $this->request->getPost('clave');
                $clave = $data['clave'];
                $usuario = new Usuario($data['id_usu']);
                $array_errores = $usuario->validaClave($data['clave']);
                $entra = true;
                for($i = 0; $i < count($array_errores); $i++){
                    if($array_errores[$i] == 0){
                        $entra = false;
                    } 
                }
                if($entra){
                    $usuario->setClave($data['clave']);
                    $set = 1;
                    $msg_ok = 'La clave ha sido actualizada correctamente';	
                }
                $id = $data['id_usu'];
            }
        }else{
            if($this->_usuario->get('rol') == '4dmin'){
                $id = (int)$this->params()->fromRoute('v1',0);
            }else{
                $id = (int)$this->_usuario->get('id');
            }
            $usuario = new Usuario($id);
        }
        
        if($id > 0){
            $this->layout()->title = $usuario->get('nombre-completo').' | Usuario';            
            // Inscripciones relacionadas
            $pagi = (int)$this->params()->fromRoute('v2',-1);
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
            $inscripciones = $db_ins->getInscripciones('id_usu = '.$id,'id_usu DESC',50,$offseti);
            $numi = $db_ins->numInscripciones('id_usu = '.$id);

            // Inscripciones inscritas
            $pagins = (int)$this->params()->fromRoute('v5',-1);
            if($pagins == -1){
                if(isset($this->_container->uinsc_page)){
                    $pagins= $this->_container->uinsc_page;
                }else{
                    $pagins = 0;
                    $this->_container->uinsc_page = $pagins;
                }
            }else{
                $this->_container->uinsc_page = $pagins;
                $tab = 'inscritos';
            }
            if($pagins == 0){
                $pagins = 1;
            }
            $offsetin = 50*($pagins - 1);
            $db_insc = new Inscripciones();
            $inscritos= $db_insc->getInscritos('id_usu = '.$id,'id_usu DESC',50,$offseti);
            $numins = $db_insc->numInscritos('id_usu = '.$id);
            
            // Candidaturas relacionadas
            $pagc = (int)$this->params()->fromRoute('v3',-1);
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
            $candidaturas = $db_can->getCandidaturas('id_usu = '.$id,'id_usu DESC',50,$offsetc);
            $numc = $db_can->num('id_usu = '.$id);
            
            // Menores relacionados
            $pagm = (int)$this->params()->fromRoute('v4',-1);
            if($pagm == -1){
                if(isset($this->_container->uins_page)){
                    $pagm= $this->_container->uins_page;
                }else{
                    $pagm = 0;
                    $this->_container->uins_page = $pagc;
                }
            }else{
                $this->_container->uins_page = $pagc;
                $tab = 'menores';
            }
            if($pagm == 0){
                $pagm = 1;
            }
            $offsetm = 50*($pagm - 1);
            $db_men = new Menores();
            $menores = $db_men->get('id_usu = '.$id,'id_usu DESC',50,$offsetm);
            $numm = $db_men->num('id_usu = '.$id);

            $db_carpetas = new Carpetas();
            $carpetas = $db_carpetas->get(null, 'nombre ASC');
            $db_permisos = new Permisos();
            $permisos = [];
            foreach($carpetas as $carpeta):
                $permisos_usuario = $db_permisos->get('id_usu = ' . $id . ' and id_car = ' . $carpeta->get('id'));
                if(count($permisos_usuario) > 0){
                    $permisos_usuario = current($permisos_usuario);
                    $id_per = $permisos_usuario->get('id');
                    $permiso = $permisos_usuario->get('permiso');
                }else{
                    $id_per = 0;
                    $permiso = 0;
                }
                $permisos[] = [
                    'id_car'    => $carpeta->get('id'),
                    'nombre'    => $carpeta->get('nombre'),
                    'id_per'    => $id_per,
                    'permiso'   => $permiso
                ];

            endforeach;

        }else{
            $inscripciones = [];
            $numi = 0;
            $pagi = 0;
            $inscritos = [];
            $numins = 0;
            $pagins = 0;            
            $candidaturas = [];
            $permisos = [];
            $numc = 0;
            $pagc = 0;
            $menores = [];
            $numm = 0;
            $pagm = 0;
        }
        
        $view = array(
            'usuario'       =>  $this->_usuario,
            'cliente'       =>  $usuario,
            'ok'            =>  $msg_ok,
            'ko'            =>  $msg_error,
            'inscripciones' =>  $inscripciones,
            'numi'          =>  $numi,
            'pagi'          =>  $pagi,
            'inscritos'     =>  $inscritos,
            'numins'        =>  $numins,
            'pagins'        =>  $pagins,            
            'candidaturas'  =>  $candidaturas,
            'numc'          =>  $numc,
            'pagc'          =>  $pagc,
            'menores'       =>  $menores,
            'numm'          =>  $numm,
            'pagm'          =>  $pagm,
            'menores'       =>  $menores,
            'tab_usuario'   =>  $tab,
            'clave'         =>	$clave,
            'array_errores' =>	$array_errores,
            'set'           => 	$set,
            'permisos'      =>  $permisos,
        );
        return new ViewModel($view);
    }
	
    public function borrarAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Usuario($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'index','v1' => (int)$this->_container->usua_page,'v2' => $ok));
    }

    public function cvAction(){
        $msg = 550;
        if($this->request->isPost()) {
            $id_usu = (int)$this->request->getPost('id_usu');
            $usuario = new Usuario($id_usu);
            $nombreDirectorio = $usuario::FILE_DIRECTORY_CV;

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
                $httpadapter->addFilter('filerename', ['target' => $nombreDirectorio . $fichero]);
                $httpadapter->setDestination($nombreDirectorio);

                if ($httpadapter->receive($files['cv']['name'])) {
                    $usuario->setCv($fichero);
                    $msg = 548;
                }
            }else{
                $msg = 549;
            }
        }else{
            $id_usu = (int)$this->params()->fromRoute('v1',0);
            $usuario = new Usuario($id_usu);
            $usuario->removeCv();
            $msg = 551;

        }
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => $id_usu,'v2' => -1,'v3' => -1,'v4' => -1,'v5' => -1,'v6' => $msg));
    }
    
    public function xlsAction(){
        $where = Utilidades::generaCondicion('usuarios', $this->_container->usua_buscador);
        $db = new Usuarios();
        $objects = $db->get($where,['apellidos','nombre']);
        $objPHPExcel = Exportar::usuarios($objects);
        
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }

    public function importarcolegiadosAction(){
        $file_import = './data/import/ListadoGenerado_Definitivo.xlsx';
        $inputFileType = \PHPExcel_IOFactory::identify($file_import);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($file_import);

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $i = 0;
        for ($row = 2; $row <= $highestRow; $row++){

            $data = [];

            $data['id_usu'] = 0;
            $data['nombre'] = $sheet->getCell("C".$row)->getValue();
            $data['apellidos'] = $sheet->getCell("B".$row)->getValue();
            $data['colegiado'] = $sheet->getCell("A".$row)->getValue();
            $data['telefono'] = $sheet->getCell("H".$row)->getValue();
            $data['email'] = $sheet->getCell("I".$row)->getValue();
            $data['nif'] = $sheet->getCell("D".$row)->getValue();
            if($sheet->getCell("E".$row)->getValue() == 'H'){
                $data['sexo'] = 2;
            }else if($sheet->getCell("E".$row)->getValue() == 'M'){
                $data['sexo'] = 1;
            }else{
                $data['sexo'] = 0;
            }
            $nacimiento = explode(' ', $sheet->getCell("G".$row)->getValue());
            if($nacimiento[0] != ''){
                $data['nacimiento'] = date('d-m-Y', strtotime(Utilidades::giraFecha($nacimiento[0])));
            }else{
                $data['nacimiento'] = null;
            }

            $data['clave'] = $sheet->getCell("J".$row)->getValue();

            $data['rol'] = 'c4legiado';
            $data['cv'] = null;
            $data['id_emp'] = null;
            $data['autorizado'] = 0;
            $data['sitlab'] = 0;


            $sitcol = 0;
            //Precolegiado sitcol == true 1
            if($sheet->getCell("M".$row)->getValue() == 'True'){
                $sitcol = 1;
            }
            //TituladoAdherido sitcol == true 2
            if($sheet->getCell("N".$row)->getValue() == 'True'){
                $sitcol = 2;
            }
            //EstudianteAdherido sitcol == true 3
            if($sheet->getCell("O".$row)->getValue() == 'True'){
                $sitcol = 3;
            }
            //ProfesionalAdherido sitcol == true 4

            if($sheet->getCell("P".$row)->getValue() == 'True'){
                $sitcol = 4;
            }

            $data['sitcol'] = $sitcol;
            $data['titulacion'] = null;
            $data['master'] = null;
            $data['empleo'] = (int)$row['empleo'];
            $data['experiencia'] = (int)$row['experiencia'];
            $data['especialidad'] = null;
            $data['jornada'] = (int)$row['jornada'];
            $data['alta'] = date('d-m-Y');
            $data['baja'] = null;
            $data['sincro'] = date('d-m-Y');

            $usuario = new Usuario(0);
            $usuario->set($data, 2);
            $id_usu = $usuario->save();
            if($id_usu > 0){
                $i++;
            }
        }

        echo 'Se han importado ' . $i . ' colegiados.';

        die();
    }
    
    public function actualizacolAction(){
        $precol = (int)$this->params()->fromRoute('v1',0);
        if((int)$precol){
            $file_import = './data/import/ListadoPrecol.xlsx';
        }else{
            $file_import = './data/import/ListadoCol.xlsx';
        }
        $inputFileType = \PHPExcel_IOFactory::identify($file_import);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($file_import);

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $db_usu = new Usuarios();
        $i = 0;
        for ($row = 2; $row <= $highestRow; $row++){
            $data = [];
            $colegiado = $sheet->getCell("A".$row)->getValue();
            $usuario = $db_usu->getByColegiado($colegiado);
            
            if($sheet->getCell("E".$row)->getValue() == 'H'){
                $data['sexo'] = 2;
            }else if($sheet->getCell("E".$row)->getValue() == 'M'){
                $data['sexo'] = 1;
            }else{
                $data['sexo'] = 0;
            }
            $data['delegacion'] = $sheet->getCell("I".$row)->getValue();
            
            if((int)$precol){
                $data['observaciones'] = $sheet->getCell("L".$row)->getValue();
                $data['sitcol'] = 1;
                $pp = $sheet->getCell("K".$row)->getValue();
            }else{
                $data['observaciones'] = $sheet->getCell("N".$row)->getValue();
                $data['sitcol'] = 0;
                $pp = $sheet->getCell("M".$row)->getValue();
            }
            if($pp == 'True'){
                $data['pago_pendiente'] = 1;
            }else{
                $data['pago_pendiente'] = 0;
            }
            
            if((int)$usuario->get('id')){                
                $usuario->set($data,3);
                $usuario->save();
            }else{
                $data['colegiado'] = $colegiado;
                $data['apellidos'] = $sheet->getCell("B".$row)->getValue();
                $data['nombre'] = $sheet->getCell("C".$row)->getValue();
                $data['nif'] = $sheet->getCell("D".$row)->getValue();
                $data['telefono'] = $sheet->getCell("G".$row)->getValue();
                $data['email'] = $sheet->getCell("H".$row)->getValue();
                $nacimiento = explode(' ', $sheet->getCell("F".$row)->getValue());
                if($nacimiento[0] != ''){
                    $data['nacimiento'] = date('d-m-Y', strtotime(Utilidades::giraFecha($nacimiento[0])));
                }else{
                    $data['nacimiento'] = null;
                }
                $data['id_usu'] = 0;
                $data['empleo'] = 0;
                $data['experiencia'] = 0;
                $data['jornada'] = 0;
                $data['clave'] = $sheet->getCell("J".$row)->getValue();
                $data['rol'] = 'c4legiado';
                $data['cv'] = null;
                $data['id_emp'] = null;
                $data['autorizado'] = 0;
                $data['sitlab'] = 0;
                $data['alta'] = date('d-m-Y');
                $usuario->set($data,2);
                $usuario->save();
                echo 'Creado ('.$row.'): '.$data['apellidos'].'<br/>';
            }
        }
        echo $i;
        die();
    }
    
    public function fotoAction(){
        
    }
    
    public function menoresAction(){
        if($this->request->isPost()){
            $data = [];
            $boton = $this->request->getPost('boton');
            $id_usu = $this->request->getPost('id_usu');
            $id_mens = $this->request->getPost('id_men');
            $nombres = $this->request->getPost('nombre');
            $apellidos = $this->request->getPost('apellidos');
            $observaciones = $this->request->getPost('observaciones');
            $nacimiento = $this->request->getPost('nacimiento');
            if($boton == 'guardar-todos'){
                foreach($id_mens as $i => $id_men):
                    if(!empty($nombres[$i])){
                        $data = [
                            'id_men'    => $id_men,
                            'nombre'    => $nombres[$i],
                            'apellidos'    => $apellidos[$i],
                            'observaciones'    => $observaciones[$i],
                            'nacimiento'    => $nacimiento[$i],
                            'id_usu'    => $id_usu
                        ];
                        $object = new Menor(0);
                        $object->set($data);
                        $object->save();
                    }
                endforeach;
            }else{
                $i = $boton;
                if(!empty($nombres[$i])){
                    $data = [
                        'id_men'    => $id_mens[$i],
                        'nombre'    => $nombres[$i],
                        'apellidos'    => $apellidos[$i],
                        'observaciones'    => $observaciones[$i],
                        'nacimiento'    => $nacimiento[$i],
                        'id_usu'    => $id_usu
                    ];
                    $object = new Menor(0);
                    $object->set($data);
                    $object->save();
                }
            }
            return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => (int)$id_usu,'v2' => 0,'v3' => 0,'v4' => 0,'v5' => 547));
        }else{
            return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'index','v1' => (int)$this->_container->usua_page));
        }
    }
    
    public function borrarmenorAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Menor($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => (int)$object->get('id_usu'),'v2' => 0,'v3' => 0,'v4' => 0,'v5' => $ok));
    }

    public function carpetasAction(){
        $this->layout()->title = 'Carpetas';
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if($idm == 525){
            $msg_ok = 'La carpeta ha sido borrada correctamente.';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar la carpeta porque tiene usuarios relacionados.';
        }
        $db = new Carpetas();
        $orderby = 'nombre ASC';
        if($this->request->isPost()){
            $data = [];
            $boton = $this->request->getPost('boton');
            if($boton == 'buscar'){
                $data['id_car'] = (int)$this->request->getPost('id_car');
                $this->_container->car_buscador = $data;
            }else if($boton == 'guardar-todos'){
                $id_cars =  $this->request->getPost('id_car');
                $nombres =  $this->request->getPost('nombre');
                foreach($id_cars as $i => $id_car):
                    if(!empty($nombres[$i])){
                        $data = [
                            'id_car'    => $id_car,
                            'nombre'    => $nombres[$i]
                        ];
                        $object = new Carpeta(0);
                        $object->set($data);
                        $object->save();
                    }
                endforeach;
                $msg_ok = 'Las carpetas han sido actualizadas correctamente.';
            }else{
                $i = $boton;
                $id_cars =  $this->request->getPost('id_car');
                $nombres =  $this->request->getPost('nombre');
                if(!empty($nombres[$i])){
                    $data = [
                        'id_car'    => $id_cars[$i],
                        'nombre'    => $nombres[$i]
                    ];
                    $object = new Carpeta(0);
                    $object->set($data);
                    $object->save();
                }
                $msg_ok = 'La carpeta ha sido actualizada correctamente.';
            }
        }else{
            // Eliminar filtros de búsqueda
            if($idm == 114){
                if(isset($this->_container->car_buscador)){
                    unset($this->_container->car_buscador);
                    $this->_container->car_page = 0;
                }
            }
        }

        // Paginación
        $page = (int)$this->params()->fromRoute('v1',-1);
        if($page == -1){
            if(isset($this->_container->car_page)){
                $page= $this->_container->car_page;
            }else{
                $page = 1;
                $this->_container->car_page = $page;
            }
        }else{
            $this->_container->car_page = $page;
        }
        if($page == 0){
            $page = 1;
        }
        $offset = 50*($page - 1);

        // Construcción de la condición ($where) según los parámetros de búsqueda
        if(isset($this->_container->car_buscador)){
            $where = Utilidades::generaCondicion('carpetas', $this->_container->car_buscador);
            $this->_container->car_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->car_buscador;
        }else{
            $buscador = array('id_car' => 0,'nombre' => null);
            $where = null;
        }

        // Leer de base de datos
        $objects = $db->get($where,$orderby,50,$offset);
        $num = $db->num($where);

        if($num == 0){
            if(isset($this->_container->car_buscador)){
                $msg_error = 'No hay ninguna carpeta con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna carpeta guardada.';
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
            'carpetas'  =>  $objects
        );
        return new ViewModel($view);
    }

    public function borrarcarpetaAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Carpeta($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'carpetas','v1' => (int)$this->_container->car_page,'v2' => $ok));
    }


    public function permisosAction(){
        if($this->request->isPost()){
            $data = [];
            $boton = $this->request->getPost('boton');
            if($boton == 'guardar-todos'){
                $id_pers =  $this->request->getPost('id_per');
                $id_cars =  $this->request->getPost('id_car');
                $permisos =  $this->request->getPost('permiso');
                $id_usu =  $this->request->getPost('id_usu');

                foreach($id_cars as $i => $id_car):
                    $data = [
                        'id_per'    => $id_pers[$i],
                        'id_car'    => $id_car,
                        'id_usu'    => $id_usu,
                        'permiso'    => $permisos[$i]
                    ];
                    $object = new Permiso(0);
                    $object->set($data);
                    $object->save();
                endforeach;
            }else{
                $i = $boton;
                $id_pers =  $this->request->getPost('id_per');
                $id_cars =  $this->request->getPost('id_car');
                $permisos =  $this->request->getPost('permiso');
                $id_usu =  $this->request->getPost('id_usu');

                $data = [
                    'id_per'    => $id_pers[$i],
                    'id_car'    => $id_cars[$i],
                    'id_usu'    => $id_usu,
                    'permiso'    => $permisos[$i]
                ];
                $object = new Permiso(0);
                $object->set($data);
                $object->save();
            }
            $ok = 552;
        }

        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => $id_usu, 'v2' => -1, 'v3' => -1, 'v4' => -1, 'v5' => -1,'v6' => $ok));
    }

    public function xaAction(){
        $ajax = (int)$this->params()->fromRoute('v1',0);
        $answer = [];
        if($ajax == 1){
            $term = $_GET['q'];
            $db = new Usuarios();
            $objects = $db->get('nombre LIKE "%'.$term.'%" OR apellidos LIKE "%'.$term.'%" OR CONCAT(nombre," ",apellidos) LIKE "%'.$term.'%"', ['nombre','apellidos']);
            if(count($objects)>0){
                foreach($objects as $object):
                    $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('nombre-completo')];
                endforeach;
            }else{
                $answer[] = ["id"=>"0","text"=>"No existen resultados."];
            }
    	}else if($ajax == 2){
            $term = (int)$_GET['q'];
            $object = new Usuario($term);
            if($object->get('id')>0){
                $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('nombre-completo')];
            }else{
                $answer[] = ["id"=>"0","text"=>""];
            }
    	}
        return $this->getResponse()->setContent(Json::encode($answer));
    }
}
