<?php
namespace Backend\Controller;

use Application\Model\Entity\Participantes;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Zend\Filter\StripTags;
use Zend\Json\Json;
use Application\Model\Entity\Curso;
use Application\Model\Entity\Empresa;
use Application\Model\Entity\Sectores;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Usuarios;
use Application\Model\Entity\Menor;
use Application\Model\Entity\Menores;
use Application\Model\Entity\Participante;
use Application\Model\Entity\Inscripcion;
use Application\Model\Entity\Inscripciones;
use Application\Model\Entity\Inscrito;
use Application\Model\Utility\Utilidades;
use Application\Model\Utility\Notificaciones;
use Application\Service\SendMail;

class InscripcionesController extends AbstractActionController{
	
    protected $_usuario;
    protected $_container;
    protected $_tipo;
    protected $sendMail;

    public function __construct(SendMail $send_mail){
        $auth = new AuthenticationService();
        $identity = $auth->getIdentity();
        if ($auth->hasIdentity()) {
            $usuario = new Usuario($identity->id_usu);
            $this->_usuario = $usuario;
            $this->_container = new Container('namespace');
        }
        $this->sendMail = $send_mail;
    }
	
    public function indexAction(){
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertas','v1' => 1));
    }
	

    public function inscripcionAction(){
        $this->layout()->title = 'Nueva | Inscripción';

        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            $id = (int)$this->request->getPost('id_cur');

            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['razonsocial'] = $filter->filter($this->request->getPost('razonsocial'));
            $data['cif'] = str_replace(['-',' '],'',mb_strtoupper($filter->filter($this->request->getPost('cif'))));
            $data['telefono'] = $filter->filter($this->request->getPost('telefono'));
            $data['email'] = $filter->filter($this->request->getPost('email'));
            $data['estado'] = 0;
            $data['id_sec'] = (int)$this->request->getPost('id_sec');
            $data['alta'] = date('d-m-Y');
            
            $db_emp = new \Application\Model\Entity\Empresas();
            $id_emp = (int)$db_emp->getByCif($data['cif']);
            
            if($id_emp){
                $algun_valor_vacio = 0;
                $autorizado = 2;
            }else{
                $empresa = new Empresa(0);
                $algun_valor_vacio = $empresa->set($data);
                if($algun_valor_vacio > 0){
                    $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                }else{
                    $id_emp = $empresa->save();
                    $autorizado = 1;
                }
            }
            if($algun_valor_vacio == 0){
                $db_usuarios = new Usuarios();
                $data_usu = [];
                $data_usu['id_emp'] = $id_emp;
                $data_usu['autorizado'] = $autorizado;
                $db_usuarios->update($data_usu, 'id_usu = ' . $this->_usuario->get('id'));
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'empresa', 'v1' => $id));
            }
        }else{
            $id = (int)$this->params()->fromRoute('v1',0);
        }

        $curso = new Curso($id);
        $empresa = new Empresa(0);
        $db_sectores = new Sectores();
        $sectores = $db_sectores->get(null, 'nombre asc');

        $msg_error = null;

        if($id > 0 && (int)$curso->get('estado') == 1){
            if($curso->get('tipo') == 2){
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'eventoinfantil', 'v1' => $id));
            }
        }else{
            $msg_error = 'El curso no tiene inscripciones abiertas.';
        }

        $view = [
            'usuario'       =>  $this->_usuario,
            'ko'            =>  $msg_error,
            'curso'         =>  $curso,
            'empresa'       =>  $empresa,
            'sectores'      =>  $sectores,
            'usuario'       =>  $this->_usuario
        ];
        $this->layout()->setTemplate('layout/app');
        return new ViewModel($view);
    }

    public function eventoinfantilAction(){
        $this->layout()->title = 'Inscripción | Evento infantil';

        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;

        if($this->request->isPost()){
            $filter = new StripTags();
            $id = (int)$this->request->getPost('id_cur');
            $curso = new Curso($id);

            $id_men = $this->request->getPost('id_men');

            $menores = [];
            if(is_array($id_men)) {
                foreach ($id_men as $index => $value):
                    $menores[] = $index;
                endforeach;
            }

            $nombres = $this->request->getPost('nombre');
            $apellidos = $this->request->getPost('apellidos');
            $nacimiento = $this->request->getPost('nacimiento');
            $observaciones = $this->request->getPost('observaciones');
            if(is_array($nombres)){
                foreach($nombres as $index => $value):
                    $data_menor = [];
                    $data_menor['id_men'] = 0;
                    $data_menor['nombre'] = $filter->filter($value);
                    $data_menor['apellidos'] = $filter->filter($apellidos[$index]);
                    $data_menor['nacimiento'] = $filter->filter($nacimiento[$index]);
                    $data_menor['observaciones'] = $filter->filter($observaciones[$index]);
                    $data_menor['id_usu'] = $this->_usuario->get('id');

                    $menor = new Menor(0);
                    $algun_valor_vacio = $menor->set($data_menor, 2);
                    if($algun_valor_vacio == 0){
                        $menores[] = $menor->save();
                    }
                endforeach;
            }

            if(count($menores) == 0){
                $msg_error = 'Debe rellenar los datos de al menos un menor.';
            }

            if(!isset($msg_error)){
                $id_ins = $curso->generaInscricionEventoInfantil($this->_usuario, $menores);
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'resumeninscripcion', 'v1' => $id_ins));
            }
        }else{
            $id = (int)$this->params()->fromRoute('v1',0);
            $curso = new Curso($id);
        }

        /*
         * Quitamos los participantes en el curso para no crear otra inscipción con los mismos menores y el mismo curso
         * */
        $db_inscripciones = new Inscripciones();
        $participantes = $db_inscripciones->getParticipantes('id_cur = ' . $curso->get('id') . ' and id_usu = ' . $this->_usuario->get('id'));

        $where = 'id_usu = ' . $this->_usuario->get('id');
        foreach($participantes as $participante):
            $where .= ' AND id_men != ' . $participante['id_men'];
        endforeach;

        $db_menores = new Menores();
        $menores = $db_menores->get($where, ['nombre asc', 'apellidos asc']);

        if($curso->get('id') > 0 && $curso->get('estado') == 1 && $curso->get('tipo') == 2){
            $this->layout()->title = $curso->get('nombre').' | Inscripción';
        }else{
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        $view = array(
            'usuario'       =>  $this->_usuario,
            'curso'         =>  $curso,
            'menores'       =>  $menores,
            'ok'            =>  $msg_ok,
            'ko'            =>  $msg_error,
        );
        $this->layout()->setTemplate('layout/app');
        return new ViewModel($view);
    }


    public function individualAction(){
        $this->layout()->title = 'Inscripción | Individual';

        $id = (int)$this->params()->fromRoute('v1',0);
        $curso = new Curso($id);

        if($curso->get('id') == 0 || $curso->get('estado') != 1 || $curso->get('tipo') == 2){
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }else{
            $db_inscripciones = new Inscripciones();
            $inscripciones = $db_inscripciones->get('id_cur = ' . $curso->get('id') . ' and id_usu = ' . $this->_usuario->get('id'));
            if(count($inscripciones) > 0){
                $id_ins = current($inscripciones)->get('id');
                $x = 125;
            }else{
                $id_ins = $curso->generaInscripcionCursoEvento($this->_usuario, [0 => $this->_usuario->get('id')]);
                $x = 0;
            }
            return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'resumeninscripcion', 'v1' => $id_ins,'v2' => $x));
        }

    }

    public function empresaAction(){
        $this->layout()->title = 'Inscripción | Empresa';

        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;

        if($this->request->isPost()){
            $filter = new StripTags();
            $id = (int)$this->request->getPost('id_cur');
            $curso = new Curso($id);

            $id_usu = $this->request->getPost('id_usu');
            $trabajadores = [];
            if(is_array($id_usu)) {
                foreach ($id_usu as $index => $value):
                    $trabajadores[] = $index;
                endforeach;
            }

            $id_usu_new = $this->request->getPost('id_usu_new');
            $nombres = $this->request->getPost('nombre');
            $apellidos = $this->request->getPost('apellidos');
            $colegiado = $this->request->getPost('colegiado');
            $telefono = $this->request->getPost('telefono');
            $email = $this->request->getPost('email');
            $nif = $this->request->getPost('nif');
            $sexo = $this->request->getPost('sexo');
            $nacimiento = $this->request->getPost('nacimiento');
            $sitlab = $this->request->getPost('sitlab');
            $titulacion = $this->request->getPost('titulacion');
            $master = $this->request->getPost('master');

            if(is_array($nombres)){
                foreach($nombres as $index => $value):
                    $data_usuario = [];
                    $data_usuario['id_usu'] = 0;

                    $data_usuario['rol'] = 'us4ario';
                    $data_usuario['alta'] = date('d-m-Y');
                    $data_usuario['baja'] = null;
                    $data_usuario['cv'] = null;
                    $data_usuario['id_emp'] = $this->_usuario->get('id_emp');
                    $data_usuario['autorizado'] = 0;
                    $data_usuario['sincro'] = date('d-m-Y H:i:s');
                    $data_usuario['empleo'] = 0;
                    $data_usuario['experiencia'] = 0;
                    $data_usuario['especialidad'] = null;
                    $data_usuario['jornada'] = 0;
                    $data_usuario['nombre'] = $filter->filter($nombres[$index]);
                    $data_usuario['apellidos'] = $filter->filter($apellidos[$index]);
                    $data_usuario['colegiado'] = $filter->filter($colegiado[$index]);
                    $data_usuario['telefono'] = $filter->filter($telefono[$index]);
                    $data_usuario['email'] = trim($filter->filter($email[$index]));
                    $data_usuario['nif'] = $filter->filter($nif[$index]);
                    $data_usuario['sexo'] = (int)$sexo[$index];
                    $data_usuario['nacimiento'] = $filter->filter($nacimiento[$index]);
                    $data_usuario['sitlab'] = 1;
                    $data_usuario['sitcol'] = 5;
                    $data_usuario['titulacion'] = null;
                    $data_usuario['master'] = null;
                    $data_usuario['clave'] = Utilidades::generaPass();
                    $data_usuario['pago_pendiente'] = 0;
                    $data_usuario['observaciones'] = null;
                    $data_usuario['delegacion'] = null;

                    if(!empty($data_usuario['email'])){
                        $db_usu = new Usuarios();
                        $usuarioExistente = $db_usu->getByEmail($data_usuario['email']);
                        $data_usuario['id_usu'] = $usuarioExistente->get('id');
                    }
                    
                    $usuario = new Usuario(0);
                    $algun_valor_vacio = $usuario->set($data_usuario, 2);

                    if($algun_valor_vacio == 0){
                        $id_tra = $usuario->save();
                        if(isset($id_usu_new[$index])){
                            $trabajadores[] = $id_tra;
                        }
                    }
                endforeach;
            }

            if(count($trabajadores) == 0){
                $msg_error = 'Debe seleccionar al menos un trabajador.';
            }else{
                $id_ins = $curso->generaInscripcionCursoEvento($this->_usuario, $trabajadores);
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'resumeninscripcion', 'v1' => $id_ins));
            }
        }else{
            $id = (int)$this->params()->fromRoute('v1',0);
            $curso = new Curso($id);
        }

        /*
         * Quitamos los inscritos en el curso para no crear otra inscipción con los mismos usuarios y el mismo curso
         * */
        $db_inscripciones = new Inscripciones();
        $inscritos = $db_inscripciones->getInscritos('id_cur = ' . $curso->get('id') . ' and (id_emp = ' . $this->_usuario->get('id_emp') . ' or id_usu = ' . $this->_usuario->get('id') . ')');

        $where = 'id_emp = ' . $this->_usuario->get('id_emp');
        foreach($inscritos as $inscrito):
            $where .= ' AND id_usu != ' . $inscrito['id_usu'];
        endforeach;

        $db_trabajadores = new Usuarios();
        $trabajadores = $db_trabajadores->get($where, ['nombre asc', 'apellidos asc']);

        if($curso->get('id') > 0 && $curso->get('estado') == 1 && $this->_usuario->get('id_emp') > 0 && (int)$this->_usuario->get('autorizado')){
            $this->layout()->title = $curso->get('nombre').' | Inscripción';
        }else{
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        $view = [
            'usuario'       =>  $this->_usuario,
            'curso'         =>  $curso,
            'trabajadores'  =>  $trabajadores,
            'ok'            =>  $msg_ok,
            'ko'            =>  $msg_error,
        ];
        $this->layout()->setTemplate('layout/app');
        return new ViewModel($view);
    }

    public function resumeninscripcionAction(){
        $this->layout()->title = 'Inscripción | Resumen inscripción';
        $msg_ok = null;
        $msg_error = null;
        $existe = 0;
        if($this->request->isPost()){
            $filter = new StripTags();
            $id_ins = (int)$this->request->getPost('id_ins');
            $data = [];
            $data['beca'] =(int)$this->request->getPost('beca');
            $existe = (int)$this->request->getPost('existe');
            $data['estado'] = 1;

            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['cif'] = $filter->filter($this->request->getPost('cif'));
            $data['direccion'] = $filter->filter($this->request->getPost('direccion'));
            $data['cp'] = $filter->filter($this->request->getPost('cp'));
            $data['localidad'] = $filter->filter($this->request->getPost('localidad'));
            $data['provincia'] = $filter->filter($this->request->getPost('provincia'));
            $data['nombre_empresa'] = $filter->filter($this->request->getPost('nombre_empresa'));
            $data['cif_empresa'] = $filter->filter($this->request->getPost('cif_empresa'));
            $data['cp_empresa'] = $filter->filter($this->request->getPost('cp_empresa'));
            $data['direccion_empresa'] = $filter->filter($this->request->getPost('direccion_empresa'));
            $data['localidad_empresa'] = $filter->filter($this->request->getPost('localidad_empresa'));
            $data['provincia_empresa'] = $filter->filter($this->request->getPost('provincia_empresa'));

            if($existe == 0){
                $db_inscripciones = new Inscripciones();
                $db_inscripciones->update($data, 'id_ins = ' . $id_ins);
            }
            
            return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'confirmacion', 'v1' => $id_ins));
        }else{
            $id_ins = (int)$this->params()->fromRoute('v1',0);
            $existe = (int)$this->params()->fromRoute('v2',0);
            $inscripcion = new Inscripcion($id_ins);
            if($existe == 125){
                if($inscripcion->get('estado') > 0){
                    $msg_ok = 'Ya se ha inscrito a esta actividad anteriormente.';
                }else{
                    $msg_error = 'Debe confirmar la inscripción para que tenga validez.';
                }
                
            }
            
        }
        $curso = $inscripcion->get('curso');
        if(($inscripcion->get('id') > 0 || $curso->get('id') > 0) && $this->_usuario->get('id')  == $inscripcion->get('id_usu')){
            $this->layout()->title = $curso->get('nombre').' | Resumen Inscripción';
        }else{
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        if($curso->get('tipo') != 2 && $inscripcion->get('id') > 0){
            $db_inscriciones = new Inscripciones();
            $inscritos = $db_inscriciones->getInscritos('id_ins = ' . $inscripcion->get('id'));
        }else{
            $db_inscriciones = new Inscripciones();
            $inscritos = $db_inscriciones->getParticipantes('id_ins = ' . $inscripcion->get('id'));
        }

        $view = [
            'usuario'       => $this->_usuario,
            'curso'         => $curso,
            'inscripcion'   => $inscripcion,
            'inscritos'     => $inscritos,
            'ok'            => $msg_ok,
            'ko'            => $msg_error,
            'existe'        => $existe,
            'empresa'       => $inscripcion->get('empresa')
        ];
        $this->layout()->setTemplate('layout/app');
        return new ViewModel($view);
    }

    public function confirmacionAction(){
        $id_ins = (int)$this->params()->fromRoute('v1',0);
        if($id_ins){
            $inscripcion = new Inscripcion($id_ins); 
            Notificaciones::enviarAvisoNuevaInscripcion($inscripcion, $this->sendMail);
        }
        header('Location:'.Utilidades::systemOptions('cursos', 'web').'/inscripcion-realizada');
        die();
    }
    
    public function cancelarAction(){
        $id_ins = (int)$this->params()->fromRoute('v1',0);
        $inscripcion = new Inscripcion($id_ins);
        $curso = $inscripcion->get('curso');
        if($this->_usuario->get('id') == $inscripcion->get('id_usu')){
            $inscripcion->setEstado(5);
        }
        $view = [
            'usuario'       => $this->_usuario,
            'curso'         => $curso,
            'inscripcion'   => $inscripcion
        ];
        $this->layout()->setTemplate('layout/app');
        return new ViewModel($view);
    }
    
    public function xaAction(){

        if(!$this->getRequest()->isXmlHttpRequest()){
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        $ajax = (int)$this->params()->fromRoute('v1',0);
        $answer = [];
        if($ajax == 1){
            $email = $_GET['email'];
            $nif = $_GET['nif'];
            $db = new Usuarios();
            $answer['status'] = 0;
            $coincide_email = (int)$db->num('email LIKE "' . $email . '"');
            if($coincide_email > 0){
                $answer['status'] += 1;
            }
            $coincide_nif = (int)$db->num('nif LIKE "' . $nif . '"');
            if($coincide_nif > 0){
                $answer['status'] += 2;
            }
        }

        return $this->getResponse()->setContent(Json::encode($answer));
    }
}