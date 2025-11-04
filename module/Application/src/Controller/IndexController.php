<?php
namespace Application\Controller;

use Application\Model\Utility\Notificaciones;
use Zend\Filter\StripTags;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Application\Model\Utility\Utilidades;
use Application\Model\Entity\Inscripciones;
use Application\Model\Entity\Inscripcion;
use Application\Model\Utility\RedsysAPI;
use Application\Service\SendMail;

class IndexController extends AbstractActionController{

    public $dbAdapter;
    protected $_container;
    protected $sendMail;

    public function __construct(SendMail $send_mail){
        $this->_container = new Container('namespace');
        $this->sendMail = $send_mail;
    }
// redirige inmediatamente a la ruta del backend; no renderiza vista pública.
    public function indexAction(){
        $this->layout()->title = 'Intranet';
        return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
    }

// inscripcionAction(): si el usuario está autenticado, redirige a backend/inscripciones; si no, guarda id_cur_inscripcion en sesión y redirige a auth para login.
    public function inscripcionAction(){
        $id = (int)$this->params()->fromRoute('id',0);
        $auth = new AuthenticationService();
        $identity = $auth->getIdentity();
        if ($auth->hasIdentity()) {
            return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'inscripcion', 'v1' => $id));
        }else{
            $container = new Container('namespace');
            $container->id_cur_inscripcion = $id;
            return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'index'));
        }
    }

// redirectAction(): maneja redirecciones a secciones según un código; si no autenticado guarda el código en la sesión y manda a auth.
    public function redirectAction(){
        $id_sec = (int)$this->params()->fromRoute('v1',0);
        $auth = new AuthenticationService();
        $identity = $auth->getIdentity();
        if ($auth->hasIdentity()) {
            $section = Utilidades::sectionsRedirect($id_sec);
            return $this->redirect()->toRoute($section['module'], array('controller' => $section['controller'], 'action' => $section['action']));
        }else{
            $container = new Container('namespace');
            $container->redirect_section = $id_sec;
            return $this->redirect()->toRoute('auth/default', array('controller' => 'index', 'action' => 'index'));
        }
    }

// Prepara los parámetros para integrar con TPV (Redsys). Usa RedsysAPI para generar merchant parameters y firma.
// Construye URL de retorno OK/KO, calcula importe (multiplica por 100), genera $params y $signature.
// Atención: contiene clave HMAC ($kc) codificada en el controlador: esto es un secreto embebido (malo).
// Devuelve ViewModel con datos para formulario de pago.
    public function pagarinscripcionAction(){
        $this->layout()->title = 'Pago de inscripción';
        $hash = $this->params()->fromRoute('hash',0);
        $id = (int)Utilidades::encriptaIdCurso($hash, 'dec');
        $inscripcion = new Inscripcion($id);

        $msg_ok = null;
        $msg_error = null;

        if($inscripcion->get('id') > 0){
            $usuario = $inscripcion->get('creador');
            $curso = $inscripcion->get('curso');

            // Redsys Parameters
            $miObj = new RedsysAPI;

            // Valores de entrada que no hemos cmbiado para ningun ejemplo
            $fuc="185001450";
            $terminal="001";
            $moneda="978";
            $trans="0";

            $basePath = $this->getRequest()->getBasePath();
            $uri = new \Zend\Uri\Uri($this->getRequest()->getUri());
            $uri->setPath($basePath);
            $uri->setQuery(array());
            $uri->setFragment('');
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost() . '' . $uri->getPath();

            $url="";
            $urlOK = $baseUrl . '/confirmacion-pago/'.($inscripcion->get('id') * 91);
            $urlKO = $baseUrl . '/confirmacion-pago/0';
            $id = substr($inscripcion->get('id') . 'T' . date('sihdmy'),0,12);
            $amount = $inscripcion->get('total') * 100;
            $concepto = substr($curso->get('nombre-sin-tildes'),0,50).' - ';
            $num_colegiado = $usuario->get('colegiado');
            if(!empty($num_colegiado)){
                $concepto .= '('.$num_colegiado.') ';
            }
            $concepto .= Utilidades::cleanString($usuario->get('nombre-completo'),1);
            
            // Se Rellenan los campos
            $miObj->setParameter("DS_MERCHANT_AMOUNT",$amount);
            $miObj->setParameter("DS_MERCHANT_ORDER",$id);
            $miObj->setParameter("DS_MERCHANT_MERCHANTCODE",$fuc);
            $miObj->setParameter("DS_MERCHANT_CURRENCY",$moneda);
            $miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE",$trans);
            $miObj->setParameter("DS_MERCHANT_TERMINAL",$terminal);
            $miObj->setParameter("DS_MERCHANT_MERCHANTURL",$url);
            $miObj->setParameter("DS_MERCHANT_URLOK",$urlOK);
            $miObj->setParameter("DS_MERCHANT_URLKO",$urlKO);
            $miObj->setParameter("DS_MERCHANT_PRODUCTDESCRIPTION",substr($concepto,0,120));
            //$miObj->setParameter("DS_MERCHANT_DATA",substr($curso->get('nombre').' - '.$usuario->get('nombre-completo'),0,120));

            //Datos de configuración
            $version="HMAC_SHA256_V1";
            $kc = 'FEX9jJKAgUvb2uVBOpJOYX52yYc6N2p+';//Clave recuperada de CANALES
            // Se generan los parámetros de la petición
            $request = "";
            $params = $miObj->createMerchantParameters();
            $signature = $miObj->createMerchantSignature($kc);
        }else{
            $msg_error = 'La inscripción no existe.';
            $usuario = [];
            $curso = [];
            $version = null;
            $params = [];
            $signature = null;
        }
        //$url_pago = 'https://sis-t.redsys.es:25443/sis/realizarPago';
        $url_pago = 'https://sis.redsys.es/sis/realizarPago';

        // Preparar datos para la vista
        $view = [
            'usuario'       =>  $usuario,
            'curso'         =>  $curso,
            'inscripcion'   =>  $inscripcion,
            'version'       =>  $version,
            'params'        =>  $params,
            'signature'     =>  $signature,
            'ok'            =>  $msg_ok,
            'ko'            =>  $msg_error,
            'urlpago'       => $url_pago
        ];
        return new ViewModel($view);
    }

// Maneja la notificación/retorno del TPV. Aquí se procesa la firma y, si OK, marca la inscripción como pagada (setEstado(3,1)), actualiza DB y envía notificación por email.
// Observación importante: existen bloques comentados y hay un if(1) en la lógica (parece que forzó siempre la ruta de éxito para pruebas). También usa header('Location') y die(), lo que evade el flujo MVC normal.
    public function recepcionaredsysAction(){
        $msg_ok = null;
        $msg_error = null;

        $inscripcion = [];
        $usuario = [];
        $curso = [];
        $kc = 'FEX9jJKAgUvb2uVBOpJOYX52yYc6N2p+'; //Clave recuperada de CANALES
        $miObj = new RedsysAPI;
        $ok = 0;
        $id_ins = (int)$this->params()->fromRoute('v1',0) / 91;
        if(1){
            $inscripcion = new Inscripcion($id_ins);
            if ($inscripcion->get('id') > 0) {
                $inscripcion->setEstado(3,1);
                $ok = 1;
            }
        }else{
            if (!empty( $_POST ) ) {    //URL DE RESP. ONLINE
                $version = $_POST["Ds_SignatureVersion"];
                $datos = $_POST["Ds_MerchantParameters"];
                $signatureRecibida = $_POST["Ds_Signature"];

                $decodec = $miObj->decodeMerchantParameters($datos);
                $decodeJson = json_decode($decodec, true);
                $numOrder = explode('T', rawurldecode($decodeJson['Ds_Order']));
                $id = $numOrder[0];
                
                /*var_dump($datos);
                var_dump($decodec);
                var_dump($decodeJson);
                var_dump($numOrder);die();*/

                $inscripcion = new Inscripcion($id);
                if ($inscripcion->get('id') > 0) {
                    $usuario = $inscripcion->get('creador');
                    $curso = $inscripcion->get('curso');
                    $firma = $miObj->createMerchantSignatureNotif($kc, $datos);
                    if ($firma === $signatureRecibida) {
                        $msg_ok = 'El pago ha sido realizado correctamente.';
                        $ok = 1;
                        $db_inscripciones = new Inscripciones();
                        $data = [];
                        $data['estado'] = 3;
                        $db_inscripciones->update($data, 'id_ins = ' . $id);
                    } else {
                        $msg_error = 'El pago ha sido rechazado.';
                    }
                } else {
                    $msg_error = 'La inscripción no existe.';
                }
            }else {
                if (!empty($_GET)) {//URL DE RESP. ONLINE
                    $version = $_GET["Ds_SignatureVersion"];
                    $datos = $_GET["Ds_MerchantParameters"];
                    $signatureRecibida = $_GET["Ds_Signature"];

                    $decodec = $miObj->decodeMerchantParameters($datos);
                    $decodeJson = json_decode($decodec, true);
                    $numOrder = explode('-', rawurldecode($decodeJson['Ds_Order']));
                    $id = $numOrder[0];
                    
                    /*var_dump($datos);
                    var_dump($decodec);
                    var_dump($decodeJson);
                    var_dump($numOrder);die();*/

                    $inscripcion = new Inscripcion($id);
                    if ($inscripcion->get('id') > 0) {
                        $usuario = $inscripcion->get('creador');
                        $curso = $inscripcion->get('curso');
                        $firma = $miObj->createMerchantSignatureNotif($kc, $datos);

                        if ($firma === $signatureRecibida) {
                            $msg_ok = 'El pago ha sido realizado correctamente.';
                            $ok = 1;
                            $db_inscripciones = new Inscripciones();
                            $data = [];
                            $data['estado'] = 3;
                            $db_inscripciones->update($data, 'id_ins = ' . $id);
                        } else {
                            $msg_error = 'El pago ha sido rechazado.';
                        }
                    } else {
                        $msg_error = 'La inscripción no existe.';
                    }
                } else {
                    $msg_error = 'Hubo un problema en el pago.';
                }
            }
        }
        
        if((int)$ok){
            Notificaciones::enviarConfirmacionPagoInscripcion($inscripcion, $this->sendMail);
            header('Location:'.Utilidades::systemOptions('cursos', 'web').'/pago-realizado');
        }else{
            header('Location:'.Utilidades::systemOptions('cursos', 'web').'/error-pago');
        }
        die();
    }

// Permite subir un justificante (archivo) mediante Zend\File\Transfer\Adapter\Http, valida tamaño y extensiones, guarda archivo en disco y actualiza registro de inscripción.
// Envía notificación al responsable mediante Notificaciones::enviarConfirmacionJustificantePagoInscripcion.
// Usa StripTags para filtrar input y crea directorio si no existe.
    public function justificantepagoAction(){
        $this->layout()->title = 'Justificante de pago por transferencia';
        $hash = $this->params()->fromRoute('hash',0);
        $msg = $this->params()->fromRoute('msg',0);
        $id = (int)Utilidades::encriptaIdCurso($hash, 'dec');
        $inscripcion = new Inscripcion($id);

        $msg_ok = null;
        $msg_error = null;
        if($inscripcion->get('id') > 0){

            $usuario = $inscripcion->get('creador');
            $curso = $inscripcion->get('curso');

            if($this->request->isPost()) {
                $msg = 550;
                $id_ins = (int)$this->request->getPost('id_ins');
                $inscripcion = new Inscripcion($id_ins);
                $nombreDirectorio = $inscripcion::FILE_DIRECTORY_JUSTIFICANTE;

                $data = [];
                $filter = new StripTags();


                $httpadapter = new \Zend\File\Transfer\Adapter\Http();
                $filesize = new \Zend\Validator\File\Size(array('min' => '0kB', 'max' => '10MB')); //1KB
                $extension = new \Zend\Validator\File\Extension(array('extension' => array('pdf', 'doc', 'docx', 'jpg', 'png', 'jpeg')));
                $httpadapter->setValidators(array($filesize, $extension));

                $files = $httpadapter->getFileInfo('justificante_pago');
                if ($httpadapter->isValid()) {
                    if(!file_exists($nombreDirectorio)){
                        mkdir($nombreDirectorio, 0750);
                    }
                    $fichero = $files['justificante_pago']['name'];
                    $ext = pathinfo($fichero, PATHINFO_EXTENSION);
                    $fichero = time() . "." . $ext;
                    $httpadapter->addFilter('filerename', ['target' => $nombreDirectorio . $fichero]);
                    $httpadapter->setDestination($nombreDirectorio);

                    if ($httpadapter->receive($files['justificante_pago']['name'])) {
                        $inscripcion->setJustificante($fichero);
                        $inscripcion = new Inscripcion($id_ins);
                        $inscripcion->setAttribute('pago', 2);
                        $inscripcion->setAttribute('estado', 6);
                        $inscripcion->save();
                        $msg = 548;
                        Notificaciones::enviarConfirmacionJustificantePagoInscripcion($inscripcion, $this->sendMail);
                        return $this->redirect()->toUrl('/justificante-pago/' . $hash . '/' . $msg);
                    }
                }else{
                    $msg = 549;
                }
            }else{
                if($inscripcion->get('pago') == 1){
                    $msg = 553;
                }else if(!empty($inscripcion->get('justificante_pago')) && file_exists(\Application\Model\Entity\Inscripcion::FILE_DIRECTORY_JUSTIFICANTE . $inscripcion->get('justificante_pago')) && $msg == 0) {
                    $msg = 552;
                }
            }

        }else{
            $msg_error = 'La inscripción no existe.';
            $usuario = [];
            $curso = [];
        }


        if($msg == 548){
            $msg_ok = 'El justificante de pago ha sido subido correctamente.';
        }else if($msg == 549){
            $msg_error = 'El justificante de pago no tiene une extensión válida.';
        }else if($msg == 550){
            $msg_error = 'Ha ocurrido un error al subir el justificante de pago.';
        }else if($msg == 551){
            $msg_ok = 'El justificante de pago ha sido borrado correctamente.';
        }else if($msg == 552){
            $msg_error = 'La inscripción ya tiene adjunto un justificante de pago.';
        }else if($msg == 553){
            $msg_error = 'La inscripción ha sido abonada mediante TPV.';
        }

        // Preparar datos para la vista
        $view = [
            'usuario'       =>  $usuario,
            'curso'         =>  $curso,
            'inscripcion'   =>  $inscripcion,
            'ok'            =>  $msg_ok,
            'ko'            =>  $msg_error,
        ];
        return new ViewModel($view);
    }

}
