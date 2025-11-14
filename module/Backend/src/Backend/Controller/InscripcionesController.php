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
/**
 * InscripcionesController
 *
 * Resumen general:
 * Este controlador gestiona las rutas públicas y privadas relacionadas con la
 * creación y gestión de inscripciones para cursos/actividades desde la parte
 * "Backend" (área protegida / usuarios autenticados). Incluye:
 * - Formularios de inscripción individual, por empresa y para eventos infantiles.
 * - Gestión de creación de usuarios/menores/trabajadores cuando procede.
 * - Páginas de resumen, confirmación y cancelación de inscripciones.
 * - Un endpoint AJAX (`xaAction`) usado por formularios para validaciones/consultas.
 *
 * Principales responsabilidades por función:
 * - __construct: inicializa usuario autenticado y servicio de envío de correo.
 * - indexAction: redirige al listado de empleo/ofertas (comportamiento por defecto).
 * - inscripcionAction: formulario para inscribirse (empresa/individual) y su flujo de creación.
 * - eventoinfantilAction: manejo de inscripciones para eventos infantiles (menores).
 * - individualAction / empresaAction: flujos específicos para distintos tipos de inscripción.
 * - resumeninscripcionAction: revisión/confirmación de una inscripción ya creada.
 * - confirmacionAction: envía notificación de nueva inscripción y redirige al front.
 * - cancelarAction: permite que el usuario cancele su inscripción (cambia estado).
 * - xaAction: endpoint AJAX para validaciones rápidas (email/nif) y consultas.
 *
 * Notas de seguridad y diseño:
 * - Muchas consultas forman cláusulas SQL por concatenación (p. ej. 'id_cur = ' . $id);
 *   revisar `Utilidades::generaCondicion` y la capa de datos para evitar inyección SQL.
 * - Los formularios deberían usar CSRF tokens y validaciones más estrictas en servidor.
 * - Operaciones masivas o envíos de correo deberían procesarse en background si hay
 *   riesgo de ser costosas.
 */

    protected $_usuario;
    protected $_container;
    protected $_tipo;
    protected $sendMail;

    public function __construct(SendMail $send_mail){
        $auth = new AuthenticationService();
        $identity = $auth->getIdentity();
        // Constructor: inicializa el usuario autenticado y el contenedor de sesión
        // 1) Inyecta el servicio SendMail en la propiedad $this->sendMail
        $this->sendMail = $send_mail;

        // 2) Usar AuthenticationService para obtener la identidad del usuario actual
        if ($auth->hasIdentity()) {
            // 3) Crear una entidad Usuario a partir del id de identidad
            $usuario = new Usuario($identity->id_usu);
            // 4) Guardar la entidad Usuario para su uso en otras acciones
            $this->_usuario = $usuario;
            // 5) Inicializar un contenedor de sesión para persistir filtros/paginación
            $this->_container = new Container('namespace');
        }
    }
	
    public function indexAction(){
        // indexAction: entrada por defecto que redirige a otro controlador/acción.
        // En este proyecto la index redirige a las ofertas de empleo; no realiza lógica propia.
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertas','v1' => 1));
    }
	

    public function inscripcionAction(){
        // inscripcionAction: formulario general para iniciar una inscripción
        // - Si la petición es POST, procesa datos del formulario y crea/actualiza la entidad Empresa
        //   y marca al usuario actual como vinculado a esa empresa.
        // - Si la petición no es POST, muestra el formulario con datos del curso y sectores.

        // 1) Preparar título del layout
        $this->layout()->title = 'Nueva | Inscripción';

        // 2) Si el formulario fue enviado (POST), recoger y sanitizar campos
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // id del curso relacionado
            $id = (int)$this->request->getPost('id_cur');

            // Datos de empresa recibidos por el formulario
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['razonsocial'] = $filter->filter($this->request->getPost('razonsocial'));
            // Normalizar CIF: quitar guiones/espacios y pasar a mayúsculas
            $data['cif'] = str_replace(['-',' '],'',mb_strtoupper($filter->filter($this->request->getPost('cif'))));
            $data['telefono'] = $filter->filter($this->request->getPost('telefono'));
            $data['email'] = $filter->filter($this->request->getPost('email'));
            $data['estado'] = 0; // estado inicial
            $data['id_sec'] = (int)$this->request->getPost('id_sec');
            $data['alta'] = date('d-m-Y'); // fecha alta actual

            // 3) Comprobar si ya existe una empresa con ese CIF
            $db_emp = new \Application\Model\Entity\Empresas();
            $id_emp = (int)$db_emp->getByCif($data['cif']);

            // 4) Si existe, reutilizar la empresa; si no, crear una nueva entidad Empresa
            if($id_emp){
                $algun_valor_vacio = 0;
                $autorizado = 2; // empresa ya existente
            }else{
                $empresa = new Empresa(0);
                // set() valida campos obligatorios; devuelve >0 si faltan
                $algun_valor_vacio = $empresa->set($data);
                if($algun_valor_vacio > 0){
                    // Mensaje de error si faltan campos obligatorios
                    $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                }else{
                    // Guardar nueva empresa y marcar autorizado = 1
                    $id_emp = $empresa->save();
                    $autorizado = 1;
                }
            }

            // 5) Si no hubo errores de validación, actualizar al usuario actual con id_emp y autorizado
            if($algun_valor_vacio == 0){
                $db_usuarios = new Usuarios();
                $data_usu = [];
                $data_usu['id_emp'] = $id_emp;
                $data_usu['autorizado'] = $autorizado;
                // actualizar fila del usuario actual (se usa concatenación directa en WHERE)
                $db_usuarios->update($data_usu, 'id_usu = ' . $this->_usuario->get('id'));
                // Redirigir al flujo de empresa para añadir trabajadores/inscritos
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'empresa', 'v1' => $id));
            }
        }else{
            // 6) Si no es POST, leer id de la ruta (v1)
            $id = (int)$this->params()->fromRoute('v1',0);
        }

        // 7) Preparar datos para la vista: curso, empresa vacía y lista de sectores
        $curso = new Curso($id);
        $empresa = new Empresa(0);
        $db_sectores = new Sectores();
        $sectores = $db_sectores->get(null, 'nombre asc');

        $msg_error = null;

        // 8) Validar que el curso tenga inscripciones abiertas; si es tipo 2 (evento infantil), redirigir
        if($id > 0 && (int)$curso->get('estado') == 1){
            if($curso->get('tipo') == 2){
                // los eventos infantiles usan otro flujo
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'eventoinfantil', 'v1' => $id));
            }
        }else{
            $msg_error = 'El curso no tiene inscripciones abiertas.';
        }

        // 9) Preparar ViewModel con los datos necesarios para el formulario
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
        // eventoinfantilAction: flujo de inscripción para actividades dirigidas a menores
        // Este endpoint gestiona tanto el alta de nuevos menores como la creación de la
        // inscripción colectiva para un evento infantil.

        // 1) Título de la página
        $this->layout()->title = 'Inscripción | Evento infantil';

        // 2) Leer códigos de la ruta para mensajes y preparar variables
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;

        // 3) Si se ha enviado el formulario (POST), procesar los menores recibidos
        if($this->request->isPost()){
            $filter = new StripTags();
            // id del curso enviado por POST
            $id = (int)$this->request->getPost('id_cur');
            $curso = new Curso($id);

            // 4) Recoger keys de id_men seleccionados (si existen)
            $id_men = $this->request->getPost('id_men');

            $menores = [];
            if(is_array($id_men)) {
                foreach ($id_men as $index => $value):
                    // Las claves del array son los ids de menores seleccionados
                    $menores[] = $index;
                endforeach;
            }

            // 5) Recoger arrays de campos para nuevos menores (nombre, apellidos, nacimiento, observaciones)
            $nombres = $this->request->getPost('nombre');
            $apellidos = $this->request->getPost('apellidos');
            $nacimiento = $this->request->getPost('nacimiento');
            $observaciones = $this->request->getPost('observaciones');
            if(is_array($nombres)){
                foreach($nombres as $index => $value):
                    // Preparar datos de cada menor nuevo y validarlos con Menor::set
                    $data_menor = [];
                    $data_menor['id_men'] = 0;
                    $data_menor['nombre'] = $filter->filter($value);
                    $data_menor['apellidos'] = $filter->filter($apellidos[$index]);
                    $data_menor['nacimiento'] = $filter->filter($nacimiento[$index]);
                    $data_menor['observaciones'] = $filter->filter($observaciones[$index]);
                    $data_menor['id_usu'] = $this->_usuario->get('id');

                    $menor = new Menor(0);
                    // set($data, 2) valida campos segun reglas internas; devuelve 0 si ok
                    $algun_valor_vacio = $menor->set($data_menor, 2);
                    if($algun_valor_vacio == 0){
                        // Guardar menor y añadir su id al array de menores para la inscripción
                        $menores[] = $menor->save();
                    }
                endforeach;
            }

            // 6) Si no hay menores para inscribir, marcar error
            if(count($menores) == 0){
                $msg_error = 'Debe rellenar los datos de al menos un menor.';
            }

            // 7) Si todo OK, delegar la creación de la inscripción al método del curso
            if(!isset($msg_error)){
                $id_ins = $curso->generaInscricionEventoInfantil($this->_usuario, $menores);
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'resumeninscripcion', 'v1' => $id_ins));
            }
        }else{
            // 8) Si no es POST, obtener id de ruta y cargar curso
            $id = (int)$this->params()->fromRoute('v1',0);
            $curso = new Curso($id);
        }

        // 9) Evitar crear inscripciones duplicadas: excluir participantes ya asociados
        $db_inscripciones = new Inscripciones();
        $participantes = $db_inscripciones->getParticipantes('id_cur = ' . $curso->get('id') . ' and id_usu = ' . $this->_usuario->get('id'));

        $where = 'id_usu = ' . $this->_usuario->get('id');
        foreach($participantes as $participante):
            $where .= ' AND id_men != ' . $participante['id_men'];
        endforeach;

        // 10) Obtener la lista de menores disponibles (filtrada)
        $db_menores = new Menores();
        $menores = $db_menores->get($where, ['nombre asc', 'apellidos asc']);

        // 11) Validar condiciones del curso: debe existir, estar activo y ser tipo 2
        if($curso->get('id') > 0 && $curso->get('estado') == 1 && $curso->get('tipo') == 2){
            $this->layout()->title = $curso->get('nombre').' | Inscripción';
        }else{
            // Redirigir si no cumple condiciones
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        // 12) Preparar ViewModel
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
        // individualAction: flujo rápido para que el usuario actual se inscriba individualmente
        $this->layout()->title = 'Inscripción | Individual';

        // 1) Leer id del curso desde la ruta
        $id = (int)$this->params()->fromRoute('v1',0);
        $curso = new Curso($id);

        // 2) Validar que el curso existe, esté activo y no sea un evento infantil (tipo == 2)
        if($curso->get('id') == 0 || $curso->get('estado') != 1 || $curso->get('tipo') == 2){
            // Si no cumple las condiciones, redirigir al índice
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }else{
            // 3) Comprobar si el usuario ya tiene una inscripción en ese curso
            $db_inscripciones = new Inscripciones();
            $inscripciones = $db_inscripciones->get('id_cur = ' . $curso->get('id') . ' and id_usu = ' . $this->_usuario->get('id'));
            if(count($inscripciones) > 0){
                // 4) Si ya existe, redirigir al resumen con v2 = 125 (codigo interno)
                $id_ins = current($inscripciones)->get('id');
                $x = 125;
            }else{
                // 5) Si no existe, generar nueva inscripción para el usuario
                $id_ins = $curso->generaInscripcionCursoEvento($this->_usuario, [0 => $this->_usuario->get('id')]);
                $x = 0;
            }
            // 6) Redirigir al resumen de inscripción
            return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'resumeninscripcion', 'v1' => $id_ins,'v2' => $x));
        }

    }

    public function empresaAction(){
        // empresaAction: flujo para que una empresa (usuario con id_emp) añada trabajadores
        // y genere una inscripción por empresa para un curso.

        // 1) Título y parámetros de control
        $this->layout()->title = 'Inscripción | Empresa';

        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;

        // 2) Si POST: procesar la lista de trabajadores y/o creación de nuevos usuarios
        if($this->request->isPost()){
            $filter = new StripTags();
            $id = (int)$this->request->getPost('id_cur');
            $curso = new Curso($id);

            // 3) Recoger id_usu (usuarios ya existentes marcados) y convertir claves a ids
            $id_usu = $this->request->getPost('id_usu');
            $trabajadores = [];
            if(is_array($id_usu)) {
                foreach ($id_usu as $index => $value):
                    $trabajadores[] = $index;
                endforeach;
            }

            // 4) Recoger datos de nuevos usuarios enviados por filas (nombre, apellidos, etc.)
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

            // 5) Iterar filas de nuevos usuarios y crear las entidades Usuario cuando proceda
            if(is_array($nombres)){
                foreach($nombres as $index => $value):
                    $data_usuario = [];
                    $data_usuario['id_usu'] = 0;

                    // Valores por defecto y metadatos necesarios para la creación
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
                    // Campos del formulario sanitizados
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
                    // Generar clave temporal para el nuevo usuario
                    $data_usuario['clave'] = Utilidades::generaPass();
                    $data_usuario['pago_pendiente'] = 0;
                    $data_usuario['observaciones'] = null;
                    $data_usuario['delegacion'] = null;

                    // 6) Si se proporciona email, comprobar si ya existe un usuario y reutilizar id
                    if(!empty($data_usuario['email'])){
                        $db_usu = new Usuarios();
                        $usuarioExistente = $db_usu->getByEmail($data_usuario['email']);
                        $data_usuario['id_usu'] = $usuarioExistente->get('id');
                    }
                    
                    // 7) Crear entidad Usuario y validar/guardar
                    $usuario = new Usuario(0);
                    $algun_valor_vacio = $usuario->set($data_usuario, 2);

                    if($algun_valor_vacio == 0){
                        $id_tra = $usuario->save();
                        if(isset($id_usu_new[$index])){
                            // Si la fila tenía flag para añadirse, incluir en el array de trabajadores
                            $trabajadores[] = $id_tra;
                        }
                    }
                endforeach;
            }

            // 8) Validar que hay al menos un trabajador para añadir a la inscripción
            if(count($trabajadores) == 0){
                $msg_error = 'Debe seleccionar al menos un trabajador.';
            }else{
                // 9) Generar la inscripción para la empresa con los trabajadores seleccionados
                $id_ins = $curso->generaInscripcionCursoEvento($this->_usuario, $trabajadores);
                return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'resumeninscripcion', 'v1' => $id_ins));
            }
        }else{
            // 10) Si no POST, cargar el curso desde la ruta
            $id = (int)$this->params()->fromRoute('v1',0);
            $curso = new Curso($id);
        }

        /*
         * 11) Quitar los inscritos existentes para no duplicar: obtener inscritos y excluirlos
         */
        $db_inscripciones = new Inscripciones();
        $inscritos = $db_inscripciones->getInscritos('id_cur = ' . $curso->get('id') . ' and (id_emp = ' . $this->_usuario->get('id_emp') . ' or id_usu = ' . $this->_usuario->get('id') . ')');

        $where = 'id_emp = ' . $this->_usuario->get('id_emp');
        foreach($inscritos as $inscrito):
            $where .= ' AND id_usu != ' . $inscrito['id_usu'];
        endforeach;

        // 12) Obtener trabajadores disponibles para añadir
        $db_trabajadores = new Usuarios();
        $trabajadores = $db_trabajadores->get($where, ['nombre asc', 'apellidos asc']);

        // 13) Comprobar permisos y condiciones: el usuario debe pertenecer a una empresa y estar autorizado
        if($curso->get('id') > 0 && $curso->get('estado') == 1 && $this->_usuario->get('id_emp') > 0 && (int)$this->_usuario->get('autorizado')){
            $this->layout()->title = $curso->get('nombre').' | Inscripción';
        }else{
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        // 14) Preparar ViewModel
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
        // resumeninscripcionAction: revisar y confirmar los datos de una inscripción
        $this->layout()->title = 'Inscripción | Resumen inscripción';
        $msg_ok = null;
        $msg_error = null;
        $existe = 0;

        // 1) Si POST: actualizar datos de la inscripción (p. ej. empresa y datos de contacto)
        if($this->request->isPost()){
            $filter = new StripTags();
            $id_ins = (int)$this->request->getPost('id_ins');
            $data = [];
            $data['beca'] =(int)$this->request->getPost('beca');
            $existe = (int)$this->request->getPost('existe');
            $data['estado'] = 1; // marcar como confirmada

            // Campos de datos administrativos y de contacto
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

            // 2) Si 'existe' es 0, actualizar la fila en BD con los nuevos datos
            if($existe == 0){
                $db_inscripciones = new Inscripciones();
                $db_inscripciones->update($data, 'id_ins = ' . $id_ins);
            }
            // 3) Redirigir a confirmación
            return $this->redirect()->toRoute('backend/default', array('controller' => 'inscripciones', 'action' => 'confirmacion', 'v1' => $id_ins));
        }else{
            // 4) Si no es POST: leer id_ins desde la ruta y cargar la inscripción
            $id_ins = (int)$this->params()->fromRoute('v1',0);
            $existe = (int)$this->params()->fromRoute('v2',0);
            $inscripcion = new Inscripcion($id_ins);
            if($existe == 125){
                // Mensajes si el usuario ya estaba inscrito
                if($inscripcion->get('estado') > 0){
                    $msg_ok = 'Ya se ha inscrito a esta actividad anteriormente.';
                }else{
                    $msg_error = 'Debe confirmar la inscripción para que tenga validez.';
                }
            }
        }

        // 5) Cargar el curso asociado a la inscripción y validar que el usuario es propietario
        $curso = $inscripcion->get('curso');
        if(($inscripcion->get('id') > 0 || $curso->get('id') > 0) && $this->_usuario->get('id')  == $inscripcion->get('id_usu')){
            $this->layout()->title = $curso->get('nombre').' | Resumen Inscripción';
        }else{
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        // 6) Dependiendo del tipo de curso, obtener inscritos o participantes
        if($curso->get('tipo') != 2 && $inscripcion->get('id') > 0){
            $db_inscriciones = new Inscripciones();
            $inscritos = $db_inscriciones->getInscritos('id_ins = ' . $inscripcion->get('id'));
        }else{
            $db_inscriciones = new Inscripciones();
            $inscritos = $db_inscriciones->getParticipantes('id_ins = ' . $inscripcion->get('id'));
        }

        // 7) Preparar ViewModel con todos los datos relevantes
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
        // confirmacionAction: enviar notificación de nueva inscripción y redirigir al front
        $id_ins = (int)$this->params()->fromRoute('v1',0);
        if($id_ins){
            // Cargar la inscripción y delegar envío de aviso a Notificaciones
            $inscripcion = new Inscripcion($id_ins); 
            Notificaciones::enviarAvisoNuevaInscripcion($inscripcion, $this->sendMail);
        }
        // Redirigir al front público indicando que la inscripción se ha realizado
        header('Location:'.Utilidades::systemOptions('cursos', 'web').'/inscripcion-realizada');
        // Terminar ejecución (comportamiento original)
        die();
    }
    
    public function cancelarAction(){
        // cancelarAction: permitir al usuario cancelar su propia inscripción
        $id_ins = (int)$this->params()->fromRoute('v1',0);
        $inscripcion = new Inscripcion($id_ins);
        $curso = $inscripcion->get('curso');
        // Solo el propietario de la inscripción puede cancelarla
        if($this->_usuario->get('id') == $inscripcion->get('id_usu')){
            // setEstado(5) marca la inscripción como cancelada según la convención del proyecto
            $inscripcion->setEstado(5);
        }
        // Preparar vista para mostrar el estado actualizado
        $view = [
            'usuario'       => $this->_usuario,
            'curso'         => $curso,
            'inscripcion'   => $inscripcion
        ];
        $this->layout()->setTemplate('layout/app');
        return new ViewModel($view);
    }
    
    public function xaAction(){
        // xaAction: endpoint AJAX para validaciones rápidas (ej. comprobar email / nif)
        // 1) Verificar que la petición es AJAX
        if(!$this->getRequest()->isXmlHttpRequest()){
            return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
        }

        // 2) Leer tipo de operación desde la ruta (v1)
        $ajax = (int)$this->params()->fromRoute('v1',0);
        $answer = [];
        if($ajax == 1){
            // 3) Obtener parámetros desde la query string de forma segura usando fromQuery
            $email = $this->params()->fromQuery('email', '');
            $nif = $this->params()->fromQuery('nif', '');
            $db = new Usuarios();
            // 4) Comprobar existencia de email y nif en la tabla usuarios
            $answer['status'] = 0;
            $coincide_email = (int)$db->num('email LIKE "' . $email . '"');
            if($coincide_email > 0){
                $answer['status'] += 1; // marcar bit para email existente
            }
            $coincide_nif = (int)$db->num('nif LIKE "' . $nif . '"');
            if($coincide_nif > 0){
                $answer['status'] += 2; // marcar bit para nif existente
            }
        }

        // 5) Devolver JSON con el estado resultante
        return $this->getResponse()->setContent(Json::encode($answer));
    }
}