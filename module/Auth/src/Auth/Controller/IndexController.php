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
use Application\Model\Utility\Notificaciones;
use Application\Service\SendMail;

/**
 * IndexController (Auth)
 * Gestiona:
 *  - Registro y login de usuarios (indexAction)
 *  - Logout (logoutAction)
 *  - Recuperación de contraseña (passAction + requestpassAction)
 *  - Redirección a pantalla de error genérica (errorAction)
 *
 * Notas de seguridad:
 *  - Consultas construidas con concatenación (email LIKE "...") podrían parametrizarse.
 *  - No hay límite de intentos de login (riesgo fuerza bruta).
 *  - El hash de recuperación se decodifica sin validaciones adicionales de caducidad.
 */
class IndexController extends AbstractActionController{

    protected $storage;
    protected $authservice;
    protected $container;
    protected $sendMail;

    /**
     * __construct: inyecta servicio de correo y crea contenedor de sesión.
     */
    public function __construct(SendMail $send_mail){
        $this->container = new Container('namespace');
        $this->sendMail  = $send_mail;
    }

    /**
     * indexAction: muestra formulario de login/registro y procesa POST.
     */
    public function indexAction(){
        // Título para la vista/layout
        $this->layout()->title = 'Intranet';
        // Mensajes que se mostrarán al usuario
        $msg_ok = null; $msg_ko = null; $msg_info = null;
        // Entidad usuario temporal antes de registro definitivo
        $usuario = new Usuario(0);

        if($this->request->isPost()){
            // Filtro básico para entradas del formulario
            $filter = new StripTags();
            // Bandera para decidir si ejecutar login tras posible registro
            $login = true;

            // Registro de nuevo usuario si viene id_usu en POST
            $id_usu = $this->request->getPost('id_usu');
            if(isset($id_usu)){
                // Construir datos del nuevo usuario
                $data = [
                    'id_usu'       => 0,
                    'rol'          => 'us4ario',
                    'alta'         => date('d-m-Y'),
                    'baja'         => null,
                    'cv'           => null,
                    'id_emp'       => null,
                    'autorizado'   => 0,
                    'sincro'       => date('d-m-Y H:i:s'),
                    'empleo'       => 0,
                    'experiencia'  => 0,
                    'especialidad' => null,
                    'jornada'      => 0,
                    'nombre'       => $filter->filter($this->request->getPost('nombre')),
                    'apellidos'    => $filter->filter($this->request->getPost('apellidos')),
                    'colegiado'    => $filter->filter($this->request->getPost('colegiado')),
                    'telefono'     => $filter->filter($this->request->getPost('telefono')),
                    'email'        => $filter->filter($this->request->getPost('email')),
                    'nif'          => $filter->filter($this->request->getPost('nif')),
                    'sexo'         => (int)$this->request->getPost('sexo'),
                    'nacimiento'   => $filter->filter($this->request->getPost('nacimiento')),
                    'sitlab'       => (int)$this->request->getPost('sitlab'),
                    'sitcol'       => (int)$this->request->getPost('sitcol'),
                    'titulacion'   => $filter->filter($this->request->getPost('titulacion')),
                    'master'       => $filter->filter($this->request->getPost('master')),
                    'clave'        => $filter->filter($this->request->getPost('pwd')),
                ];
                // Asignar datos con validaciones internas (modo 2)
                $algun_valor_vacio = $usuario->set($data, 2);
                // Revisar duplicidades de email y NIF
                $ko = $usuario->revisaEmailYNif();
                // Validaciones de registro
                if(!(int)$this->request->getPost('politica_privacidad')){
                    $msg_ko = 'Debes aceptar la política de privacidad.'; $login = false;
                }else if($algun_valor_vacio > 0){
                    $msg_ko = 'Debes rellenar los campos del formulario.'; $login = false;
                }else if($ko == 1){
                    $msg_ko = 'El e-mail introducido ya pertenece a otro usuario.'; $login = false;
                }else if($ko == 2){
                    $msg_ko = 'El NIF introducido ya pertenece a otro usuario.'; $login = false;
                }else{
                    // Guardar el nuevo usuario
                    $nuevo_id = $usuario->save();
                    if($nuevo_id == 0){
                        $msg_info = 'Ha ocurrido un error en la inscripción.'; $login = false;
                    }
                }
            }

            // Proceso de login si procede
            if($login){
                $email      = $filter->filter($this->request->getPost('email'));
                $pwd        = $filter->filter($this->request->getPost('pwd'));
                $recordarme = $this->request->getPost('recordarme');
                if($email !== '' && $pwd !== ''){
                    $dbAdapter = \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter();
                    if(strpos($email,'@') === false){
                        $identity_column = 'colegiado';
                        $email = str_pad($email, 5, '0', STR_PAD_LEFT);
                    }else{
                        $identity_column = 'email';
                    }
                    $authAdapter = new AuthAdapter(
                        $dbAdapter,
                        'usuarios',
                        $identity_column,
                        'clave',
                        "AES_ENCRYPT(?,'" . $usuario->getLlave() . "')"
                    );
                    $authAdapter->setIdentity($email)->setCredential($pwd);
                    $this->auth = $this->getAuth();
                    $result = $this->auth->authenticate($authAdapter);
                    switch($result->getCode()){
                        case Result::FAILURE_IDENTITY_NOT_FOUND:
                            $msg_ko = 'El usuario no está registrado en el sistema.'; break;
                        case Result::FAILURE_CREDENTIAL_INVALID:
                            $msg_ko = 'Usuario o contraseña incorrectos.'; break;
                        case Result::SUCCESS:
                            if($this->auth->hasIdentity()){
                                $storage = $this->auth->getStorage();
                                $storage->write($authAdapter->getResultRowObject(null,'usr_password'));
                                if($recordarme){ (new \Zend\Session\SessionManager())->rememberMe(1209600); }
                                $identity = $this->auth->getIdentity();
                                $usuario = new Usuario($identity->id_usu);
                                $baja = $usuario->get('baja');
                                if($baja !== null){
                                    $this->auth->clearIdentity();
                                    $msg_ko = 'Ficha inactiva. Contacte con administración.';
                                }else{
                                    $usuario->registro(0);
                                    if($this->container->id_cur_inscripcion > 0){
                                        $id_ins = $this->container->id_cur_inscripcion; $this->container->id_cur_inscripcion = 0;
                                        return $this->redirect()->toRoute('backend/default',[ 'controller'=>'inscripciones','action'=>'inscripcion','v1'=>$id_ins ]);
                                    }else if($this->container->redirect_section > 0){
                                        $redir = $this->container->redirect_section; $this->container->redirect_section = 0;
                                        return $this->redirect()->toRoute('application/default',[ 'controller'=>'index','action'=>'redirect','v1'=>$redir ]);
                                    }else{
                                        return $this->redirect()->toRoute('backend/default',[ 'controller'=>'index','action'=>'index' ]);
                                    }
                                }
                            }
                            break;
                        default:
                            $msg_ko = 'Usuario o contraseña incorrectos.';
                            break;
                    }
                }else{
                    $msg_ko = 'Debes rellenar los campos del formulario.';
                }
            }
        }else{ // GET: mostrar formulario inicial
            $auth = new AuthenticationService();
            if($auth->hasIdentity()){
                return $this->redirect()->toRoute('auth/default',[ 'controller'=>'index','action'=>'logout' ]);
            }
            if(isset($this->container->id_cur_inscripcion)){
                $msg_info = 'Para comenzar la inscripción debe identificarse. Si es colegiado use su número. Si es nuevo usuario, regístrese.';
            }
            $id_msg = $this->params()->fromRoute('v1',0);
            if($id_msg == 1){ $msg_ok = 'Se ha enviado un email para restablecer la contraseña.'; }
            else if($id_msg == 2){ $msg_ko = 'No hemos podido enviar el email, inténtelo más tarde.'; }
            else if($id_msg == 3){ $msg_ok = 'La contraseña ha sido restablecida correctamente.'; }
            else if($id_msg == 4){ $msg_ko = 'El email no está registrado.'; }
        }

        return new ViewModel([
            'title'   => 'Identificación de usuario',
            'ok'      => $msg_ok,
            'ko'      => $msg_ko,
            'info'    => $msg_info,
            'usuario' => $usuario,
        ]);
    }

    /** Devuelve instancia de AuthenticationService */
    public function getAuth(){
        $this->auth = new AuthenticationService();
        return $this->auth;
    }

    /** logoutAction: limpia identidad y cookie rememberMe */
    public function logoutAction(){
        $auth = new AuthenticationService();
        if ($auth->hasIdentity()) {
            $identity = $auth->getIdentity();
            $usuario  = new Usuario($identity->id_usu);
            $usuario->registro(1); // registrar evento de salida
        }
        $auth->clearIdentity();
        (new \Zend\Session\SessionManager())->forgetMe();
        return $this->redirect()->toRoute('auth/default',[ 'controller'=>'index','action'=>'index' ]);
    }

    /** errorAction: redirige a la pantalla de error del backend */
    public function errorAction(){
        return $this->redirect()->toRoute('backend/default',[ 'controller'=>'index','action'=>'error' ]);
    }

    /** passAction: inicia recuperación enviando email si existe */
    public function passAction(){
        $id_msg = 0;
        if($this->request->isPost()){
            $filter = new StripTags();
            $email  = $filter->filter($this->request->getPost('email'));
            $db_users = new Usuarios();
            $usuarios = $db_users->get('email LIKE "' . $email . '"');
            if(count($usuarios) > 0){
                $usuario  = current($usuarios);
                $dev_mail = Notificaciones::recuperarClave($usuario,$this->sendMail);
                $id_msg   = $dev_mail['status'] ? 1 : 2;
            }else{
                $id_msg = 4;
            }
        }
        return $this->redirect()->toRoute('auth/default',[ 'controller'=>'index','action'=>'index','v1'=>$id_msg ]);
    }

    /** requestpassAction: aplica nueva contraseña usando hash de email */
    public function requestpassAction(){
        $this->layout()->title = 'Recuperar clave';
        $msg_error = null; $hash_param = null;
        if($this->request->isPost()){
            $filter = new StripTags();
            $hash = $filter->filter($this->request->getPost('hash'));
            $pass = $filter->filter($this->request->getPost('pwd'));
            $passrepeat = $filter->filter($this->request->getPost('pwdrepeat'));
            if($pass !== $passrepeat){
                $msg_error = 'Las contraseñas no coinciden.';
            }else if(!isset($hash[1]) || $hash[1] == ''){
                return $this->redirect()->toRoute('auth/default',[ 'controller'=>'index','action'=>'index' ]);
            }else{
                $parts = explode('h/h', base64_decode($hash));
                $id_usu = hexdec($parts[0]) / 7 / 11;
                $email  = $parts[1];
                $db_users = new Usuarios();
                $usuarios = $db_users->get('email like "' . $email . '"');
                if(count($usuarios) > 0){
                    $usuario = array_values($usuarios)[0];
                    $usuario->setClave($pass);
                    return $this->redirect()->toRoute('auth/default',[ 'controller'=>'index','action'=>'index','v1'=>3 ]);
                }else{
                    return $this->redirect()->toRoute('auth/default',[ 'controller'=>'index','action'=>'index','v1'=>4 ]);
                }
            }
            $hash_param = $hash; // Re-mostrar hash si hubo error de coincidencia
        }else{
            $auth = new AuthenticationService();
            if ($auth->hasIdentity()){
                return $this->redirect()->toRoute('auth/default',[ 'controller'=>'index','action'=>'logout' ]);
            }
            $hash_param = $this->params()->fromRoute('v1',0); // Se reutiliza v1 para transportar el hash codificado
        }
        return new ViewModel([
            'title' => 'Recuperar contraseña',
            'hash'  => $hash_param,
            'ko'    => $msg_error,
        ]);
    }
}
