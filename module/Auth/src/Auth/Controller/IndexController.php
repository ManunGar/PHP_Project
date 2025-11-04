<?php
namespace Auth\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as AuthAdapter;
use Zend\Session\Container;
use Zend\Filter\StripTags;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Usuarios;
use Application\Model\Utility\Utilidades;
use Application\Model\Utility\Notificaciones;
use Application\Service\SendMail;

class IndexController extends AbstractActionController{

    protected $storage;
    protected $authservice;
    protected $container;


    public function __construct(SendMail $send_mail){
        $this->container = new Container('namespace');
        $this->sendMail = $send_mail;
    }
    
    public function indexAction(){
        $this->layout()->title = 'Intranet';
        $msg_ok = null;
        $msg_ko = null;
        $msg_info = null;
        $usuario = new Usuario(0);

    	if($this->request->isPost()){
            $filter = new StripTags();
    	    $login = true;

    	    /*
    	     * Viene por el registro de usuario
    	     * */
    	    $id_usu = $this->request->getPost('id_usu');
    	    if(isset($id_usu)){
    	        $data = [];
                $data['id_usu'] = 0;

                $data['rol'] = 'us4ario';
                $data['alta'] = date('d-m-Y');
                $data['baja'] = null;
                $data['cv'] = null;
                $data['id_emp'] = null;
                $data['autorizado'] = 0;
                $data['sincro'] = date('d-m-Y H:i:s');
                $data['empleo'] = 0;
                $data['experiencia'] = 0;
                $data['especialidad'] = null;
                $data['jornada'] = 0;
                $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
                $data['apellidos'] = $filter->filter($this->request->getPost('apellidos'));
                $data['colegiado'] = $filter->filter($this->request->getPost('colegiado'));
                $data['telefono'] = $filter->filter($this->request->getPost('telefono'));
                $data['email'] = $filter->filter($this->request->getPost('email'));
                $data['nif'] = $filter->filter($this->request->getPost('nif'));
                $data['sexo'] = (int)$this->request->getPost('sexo');
                $data['nacimiento'] = $filter->filter($this->request->getPost('nacimiento'));
                $data['sitlab'] = (int)$this->request->getPost('sitlab');
                $data['sitcol'] = (int)$this->request->getPost('sitcol');
                $data['titulacion'] = $filter->filter($this->request->getPost('titulacion'));
                $data['master'] = $filter->filter($this->request->getPost('master'));
                $data['clave'] = $filter->filter($this->request->getPost('pwd'));

                $algun_valor_vacio = $usuario->set($data, 2);

                $ko = $usuario->revisaEmailYNif();

                if(!(int)$this->request->getPost('politica_privacidad')){
                    $msg_ko = 'Debes aceptar la política de privacidad.';
                    $login = false;
                }else if($algun_valor_vacio > 0){
                    $msg_ko = 'Debes rellenar los campos del formulario.';
                    $login = false;
                }else if($ko == 1){
                    $msg_ko = 'No se ha guardado el usuario porque el e-mail introducido ya pertenece a otro usuario.';
                    $login = false;
                }else if($ko == 2){
                    $msg_ko = 'No se ha guardado el usuario porque el NIF introducido ya pertenece a otro usuario.';
                    $login = false;
                }else{
                    $id = $usuario->save();
                    if($id == 0){
                        $msg_info = 'Ha ocurrido un error en la inscripción.';
                        $login = false;
                    }
                }
            }

            if($login) {
                $data = [];
                $email = $filter->filter($this->request->getPost('email'));
                $pwd = $filter->filter($this->request->getPost('pwd'));
                $recordarme = $this->request->getPost('recordarme');

                if ($email != '' and $pwd != '') {
                    $dbAdapter = \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter();
                    if(strpos($email,'@') === false){
                        $identity_column = 'colegiado';
                        $email = str_pad($email, 5, '0', STR_PAD_LEFT);
                    }else{
                        $identity_column = 'email';
                    }
                    $authAdapter = new AuthAdapter($dbAdapter,
                        'usuarios', 
                        $identity_column, 
                        'clave', 
                        "AES_ENCRYPT(?,'" . $usuario->getLlave() . "')"
                    );
                    $authAdapter->setIdentity($email)->setCredential($pwd);
                    $es_usuario = false;
                    $this->auth = $this->getAuth();
                    $result = $this->auth->authenticate($authAdapter);

                    switch ($result->getCode()) {
                        case Result::FAILURE_IDENTITY_NOT_FOUND:
                            $msg_ko = 'El usuario no está registrado en el sistema.';
                            break;
                        case Result::FAILURE_CREDENTIAL_INVALID:
                            // do stuff for invalid credential
                            $msg_ko = 'El usuario o la contrase&ntilde;a no son correctos.';
                            break;
                        case Result::SUCCESS:
                            if ($this->auth->hasIdentity()) {
                                $storage = $this->auth->getStorage();
                                $storage->write($authAdapter->getResultRowObject(
                                    null,
                                    'usr_password'
                                ));
                                $time = 1209600; // 14 days 1209600/3600 = 336 hours => 336/24 = 14 days
                                if ($recordarme) {
                                    $sessionManager = new \Zend\Session\SessionManager();
                                    $sessionManager->rememberMe($time);
                                }
                                $identity = $this->auth->getIdentity();
                                $usuario = new Usuario($identity->id_usu);

                                $baja = $usuario->get('baja');
                                if(isset($baja)){
                                    $this->auth->clearIdentity();
                                    $msg_ko = 'Su ficha está inactiva. Le rogamos que se ponga en contacto con nosotros para volver a activarla. Muchas gracias.';
                                }else{
                                    $usuario->registro(0);

                                    if($this->container->id_cur_inscripcion > 0){
                                        $id_ins = $this->container->id_cur_inscripcion;
                                        $this->container->id_cur_inscripcion = 0;
                                        return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'inscripcion', 'v1' => $id_ins));
                                    }else if($this->container->redirect_section > 0){
                                        $id = $this->container->redirect_section;
                                        $this->container->redirect_section = 0;
                                        return $this->redirect()->toRoute('application/default', array('controller' => 'index', 'action' => 'redirect', 'v1' => $id));
                                    }else{
                                        return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
                                    }
                                }

                                break;
                            }
                        default:
                            $msg_ko = 'El usuario o la contrase&ntilde;a no son correctos.';
                            break;
                    }
                } else {
                    $msg_ko = 'Debes rellenar los campos del formulario.';
                }
            }
    	}else{
            $auth = new AuthenticationService();
            if ($auth->hasIdentity()){
                return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'logout'));
            }
            if(isset($this->container->id_cur_inscripcion)){
                $msg_info = 'Para comenzar la inscripción, primero debe identificarse. Si usted es colegiado, puede acceder mediante su número de colegiado (si no recuerda la contraseña, puede recuperarla).<br/><br/>Si no es colegiado y no ha accedido anteriormente a nuestro sistema, puede registrarse como nuevo usuario.';
            }
            $id_msg = $this->params()->fromRoute('v1', 0);

            if($id_msg == 1){
                $msg_ok = 'Se ha enviado un email para restablecer la contraseña.';
            }else if($id_msg == 2){
                $msg_ko = 'No hemos podido enviar el email, por favor, inténtelo mas tarde.';
            }else if($id_msg == 3){
                $msg_ok = 'La contraseña ha sido restablecida correctamente.';
            }else if($id_msg == 4){
                $msg_ko = 'El email no se encuentra registrado en el sistema.';
            }
    	}
        $view = array(
            'title'   => 'Identificación de usuario',
            'ok'      => $msg_ok,
            'ko'      => $msg_ko,
            'info'    => $msg_info,
            'usuario' => $usuario,
        );
        return new ViewModel($view);
    }
	
    public function getAuth(){
        $this->auth = new AuthenticationService();
        return $this->auth;
    }
    
    public function logoutAction(){
        $auth = new AuthenticationService();
        if ($auth->hasIdentity()) {
            $identity = $auth->getIdentity();
            $usuario = new Usuario($identity->id_usu);
            $usuario->registro(1);
        }
        $auth->clearIdentity();
        $sessionManager = new \Zend\Session\SessionManager();
        $sessionManager->forgetMe();
        return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'index'));		
    }
    
    public function errorAction(){
        return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'error'));
    }

    public function passAction(){
        $id_msg = 0;

        if($this->request->isPost()) {
            $filter = new StripTags();
            $email = $filter->filter($this->request->getPost('email'));
            $db_users = new Usuarios();
            $usuarios = $db_users->get('email LIKE "' . $email . '"');
            if(count($usuarios) > 0){
                $usuario = current($usuarios);
                $dev_mail = Notificaciones::recuperarClave($usuario,$this->sendMail);
                if($dev_mail['status']){
                    $id_msg = 1;
                }else{
                    $id_msg = 2;
                }
            }else{
                $id_msg = 4;
            }
        }
        return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'index', 'v1' => $id_msg));
    }

    public function requestpassAction(){
        $this->layout()->title = 'Recuperar clave';
        $msg_error = null;
        if($this->request->isPost()){
            $id_ms = 0;
            $filter = new StripTags();
            $hash = $filter->filter($this->request->getPost('hash'));

            $pass = $filter->filter($this->request->getPost('pwd'));
            $passrepeat = $filter->filter($this->request->getPost('pwdrepeat'));

            if($pass != $passrepeat){
                $msg_error = 'Las contraseñas no coinciden.';
            }else if(!isset($hash[1]) || $hash[1] == ''){
                return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'index'));
            }else{
                $hash = explode('h/h', base64_decode($hash));
                $id_usu = hexdec($hash[0]) / 7 / 11;

                $email = $hash[1];

                $db_users = new Usuarios();
                $usuarios = $db_users->get('email like "' . $email . '"');
                if(count($usuarios) > 0){
                    $id_ms = 3;
                    $usuario = array_values($usuarios)[0];
                    $usuario->setClave($pass);
                }
                return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'index', 'v1' => $id_ms));
            }

        }else{
            $auth = new AuthenticationService();
            if ($auth->hasIdentity()){
                return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'logout'));
            }

            $hash = $this->params()->fromRoute('v1', 0);
        }

        $view = [
            'title'     => 	'Recuperar contraseña',
            'hash'      =>      $hash,
            'ko'            =>  $msg_error,
        ];
        return new ViewModel($view);
    }
}
