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

/**
 * UsuariosController
 *
 * Resumen general:
 * Este controlador centraliza las operaciones de gestión de usuarios en el
 * módulo "Backend". Sus responsabilidades principales incluyen:
 * - Listar usuarios con filtros y paginación (`indexAction`).
 * - Crear/editar la ficha de un usuario y gestionar datos relacionados
 *   (inscripciones, inscritos, candidaturas, menores) (`fichaAction`).
 * - Borrado de usuarios (`borrarAction`).
 * - Gestión de subida/eliminación de currículums (`cvAction`).
 * - Exportación de usuarios a Excel (`xlsAction`).
 * - Importaciones masivas y actualizaciones desde ficheros Excel
 *   (`importarcolegiadosAction`, `actualizacolAction`).
 * - Gestión de fotos, menores asociados, carpetas y permisos.
 * - Endpoints AJAX para autocompletar y búsquedas (`xaAction`).
 *
 * Notas pedagógicas y de seguridad:
 * - Las acciones que construyen condiciones SQL usando concatenación
 *   (p.ej. `Utilidades::generaCondicion` o construcciones con operadores
 *   como 'id_usu = '.$id) son puntos de atención por riesgo de inyección.
 *   Se recomienda usar consultas parametrizadas en refactors futuros.
 * - Las acciones de exportación usan headers directos y `die()`/`exit`. Es
 *   preferible devolver un `StreamResponse` para mantener el control del
 *   flujo de ejecución y facilitar testing.
 * - Los uploads (CV, fotos) validan tamaño y extensión; comprobar también
 *   MIME-type y evitar path traversal al guardar archivos.
 *
 * Estructura de los métodos:
 * - Cada `*Action` corresponde a una ruta y generalmente devuelve un
 *   `ViewModel` con datos para renderizar o una respuesta JSON en endpoints
 *   tipo AJAX.
 */
class UsuariosController extends AbstractActionController{

    // Usuario autenticado que hace la petición (objeto Usuario)
    protected $_usuario;
    // Contenedor de sesión usado para persistir filtros/paginación entre peticiones
    protected $_container;
    // Tipo/rol auxiliar si se necesita
    protected $_tipo;

    /**
     * Constructor
     * - Inicializa el servicio de autenticación, obtiene la identidad y
     *   carga el objeto Usuario correspondiente.
     * - Crea un contenedor de sesión (`namespace`) para guardar datos
     *   de buscadores y paginación entre peticiones.
     */
    public function __construct(){
        // Crear servicio de autenticación y comprobar identidad
        $auth = new AuthenticationService();
        // Obtener los datos de identidad del usuario autenticado
        $identity = $auth->getIdentity();
        // Si hay un usuario autenticado, cargar su entidad y el contenedor
        if ($auth->hasIdentity()) {
            // Cargar la entidad Usuario con el id obtenido de la identidad
            $usuario = new Usuario($identity->id_usu);
            // Guardar el objeto usuario en la propiedad del controlador
            $this->_usuario = $usuario;
            // Inicializar un contenedor de sesión para este namespace
            $this->_container = new Container('namespace');
        }
    }
	
    /**
     * indexAction
     *
     * Propósito: mostrar listado de usuarios con filtros y paginación.
     * Ruta típica: /backend/usuarios (según configuración de rutas del módulo).
     */
    public function indexAction(){
        // Establecer el título de la página en el layout
        $this->layout()->title = 'Usuarios';

        // Leer código de mensaje (parámetro de ruta v2) para mostrar mensajes de resultado
        $idm = (int)$this->params()->fromRoute('v2', 0);

        // Inicializar variables de mensajes
        $msg_ok = null;
        $msg_error = null;

        // Códigos de mensajes de otras acciones (se usan para retroalimentar al usuario)
        if($idm == 125){
            // 125: usuario borrado correctamente
            $msg_ok = 'El usuario ha sido borrado correctamente.';
        }else if($idm == 136){
            // 136: fallo al borrar por relaciones existentes
            $msg_error = 'No se ha podido borrar el usuario porque tiene otras entidades relacionadas.';
        }

        // Instanciar el mapper/objeto que maneja usuarios en la capa de modelo
        $db_usuarios = new Usuarios();
        // Orden por defecto para la consulta
        $orderby = 'nombre ASC';

        // Si la petición es POST, se han enviado filtros desde el formulario
        if($this->request->isPost()){
            // Array que contendrá los filtros recogidos
            $data = [];
            // Filtro para sanear HTML/etiquetas
            $filter = new StripTags();
            // Parámetros de búsqueda recibidos vía POST
            $data['id_usu'] = (int)$this->request->getPost('id_usu');
            // Sanitizar campos que pueden contener texto
            $data['nif'] = $filter->filter($this->request->getPost('nif'));
            $data['colegiado'] = $filter->filter($this->request->getPost('colegiado'));
            $data['telefono'] = $filter->filter($this->request->getPost('telefono'));
            $data['email'] = $filter->filter($this->request->getPost('email'));
            // Campos numéricos cast a int
            $data['sitcol'] = (int)$this->request->getPost('sitcol');
            $data['rol'] = $filter->filter($this->request->getPost('rol'));
            $data['autorizado'] = (int)$this->request->getPost('autorizado');

            // Guardar el buscador en la sesión para persistir filtros en paginación
            $this->_container->usua_buscador = $data;
            // Resetear la página a 0 (se usará 1 por defecto más abajo)
            $this->_container->usua_page = 0;
        }else{
            // Si no es POST, comprobar si la ruta indica limpiar filtros
            if($idm == 114){
                // 114: petición para limpiar los filtros
                if(isset($this->_container->usua_buscador)){
                    // Eliminar filtros guardados en sesión
                    unset($this->_container->usua_buscador);
                    $this->_container->usua_page = 0;
                }
            }
        }

        // Manejo de paginación: se usa el parámetro de ruta v1 para la página
        $page = (int)$this->params()->fromRoute('v1',-1);
        if($page == -1){
            // Si no hay página en la ruta, usar la guardada en sesión o inicializar a 1
            if(isset($this->_container->usua_page)){
                $page= $this->_container->usua_page;
            }else{
                $page = 1;
                $this->_container->usua_page = $page;
            }
        }else{
            // Si viene en la ruta, guardarla en sesión
            $this->_container->usua_page = $page;
        }
        // Asegurar que la página mínima sea 1
        if($page == 0){
            $page = 1;
        }
        // Calcular offset para consultas (50 elementos por página)
        $offset = 50*($page - 1);

        // Construcción de la condición ($where) según parámetros de búsqueda guardados
        if(isset($this->_container->usua_buscador)){
            // Generar la cláusula WHERE a partir del array de filtros
            $where = Utilidades::generaCondicion('usuarios', $this->_container->usua_buscador);
            // Guardar la cláusula generada dentro del buscador (útil para debug/visualización)
            $this->_container->usua_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->usua_buscador;
        }else{
            // Si no hay filtros, inicializar un buscador vacío con valores por defecto
            $buscador = array('id_usu' => 0,'nif' => null,'colegiado' => null,'telefono' => null,'email' => null,'sitcol' => -1,'rol' => -1,'autorizado' => -1);
            $where = null;
        }

        // Leer de la base de datos usando el mapper y la condición calculada
        $usuarios = $db_usuarios->get($where,$orderby,50,$offset);
        // Obtener el número total de resultados (para paginación)
        $num = $db_usuarios->num($where);
        if($num == 0){
            // Mensaje si no se encuentran resultados
            if(isset($this->_container->usua_buscador)){
                $msg_error = 'No hay ning&uacute;n usuario con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ning&uacute;n usuario guardado.';
            }
        }

        // Preparar array con variables que se pasarán a la vista
        $view = array(
            'usuario'   =>  $this->_usuario,
            'buscador'  =>  $buscador,
            'page'      =>  $page,
            'ok'        =>  $msg_ok,
            'ko'        =>  $msg_error,
            'num'       =>  $num,
            'usuarios'  =>  $usuarios
        );
        // Devolver ViewModel para renderizar la plantilla correspondiente
        return new ViewModel($view);
    }
	
    /**
     * fichaAction
     *
     * Propósito: mostrar y procesar el formulario de ficha de usuario (crear/editar)
     * y gestionar pestañas relacionadas como inscripciones, inscritos, menores y permisos.
     */
    public function fichaAction(){
        // Establecer título del layout para esta vista
        $this->layout()->title = 'Nuevo | Usuario';

        // Inicialización de mensajes y variables auxiliares
        $msg_ok = null;           // Mensaje de éxito a mostrar
        $msg_error = null;        // Mensaje de error a mostrar
        $array_errores = [];      // Array para validaciones (por ejemplo de contraseña)
        $clave = null;            // Valor temporal de la clave si se actualiza
        $tab = 'default';         // Pestaña activa en la vista de ficha
        $set = 1;                 // Control de flujo interno para distinguir subacciones

        // Parametro v6 usado para códigos de resultado de operaciones relacionadas
        $id_mens_ins= (int)$this->params()->fromRoute('v6', 0);
        // Interpretar códigos y ajustar mensajes y pestaña
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

        // Parametro v5 usado para resultados relacionados con menores
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

        // Si la petición es POST, procesar datos enviados desde el formulario
        if($this->request->isPost()){
            $data = [];
            // StripTags para sanear entradas de texto
            $filter = new StripTags();
            // 'set' controla sub-flujos del formulario (1 = guardar datos generales, otro = cambiar clave)
            $set = (int)$this->request->getPost('set');

            // Determinar id_usu según rol del usuario autenticado (si es admin puede editar otros)
            if($this->_usuario->get('rol') == '4dmin'){
                $data['id_usu'] = (int)$this->request->getPost('id_usu');
            }else{
                // Si no es admin, solo puede editar su propia ficha
                $data['id_usu'] = (int)$this->_usuario->get('id');
            }

            // Ramo principal: guardar datos de la ficha
            if($set == 1){
                // Si el usuario que edita es admin, permitimos más campos y posibilidad de crear nuevo usuario
                if($this->_usuario->get('rol') == '4dmin'){
                    $case = 2; // Indica que se trata de creación/edición por admin
                    $usuario = new Usuario(0); // Entidad vacía para set
                    // Campos administrativos permitidos
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
                    // Edición por parte del propio usuario
                    $case = 4; // Indica edición parcial
                    $usuario = new Usuario($data['id_usu']);
                }

                // Campos comunes a creación/edición
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

                // Validar y preparar la entidad Usuario con el array de datos y el caso (2/4)
                $algun_valor_vacio = $usuario->set($data,$case);
                // Revisar si email o nif chocan con otros usuarios
                $ko = $usuario->revisaEmailYNif();
                if($algun_valor_vacio > 0){
                    // Hay campos obligatorios vacíos
                    $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                    $id = $data['id_usu'];
                }else if($ko == 1){
                    // Email duplicado
                    $msg_error = 'No se ha guardado el usuario porque el e-mail introducido ya pertenece a otro cliente/usuario.';
                    $id = $data['id_usu'];
                }else if($ko == 2){
                    // NIF duplicado
                    $msg_error = 'No se ha guardado el usuario porque el NIF introducido ya pertenece a otro cliente/usuario.';
                    $id = $data['id_usu'];
                }else if($ko == 3){
                    // Email y NIF duplicados
                    $msg_error = 'No se ha guardado el usuario porque el e-mail y el NIF introducidos ya pertenece a otro cliente/usuario.';
                    $id = $data['id_usu'];
                }else{
                    // Guardar la entidad y obtener el id resultante
                    $id = $usuario->save();
                    if($data['id_usu'] == 0){
                        $msg_ok = 'El usuario ha sido creado correctamente.';
                    }else{
                        $msg_ok = 'El usuario ha sido actualizado correctamente.';
                    }
                }
            }else{
                // Rama para actualizar la contraseña solo
                $data['clave'] = $this->request->getPost('clave');
                $clave = $data['clave'];
                $usuario = new Usuario($data['id_usu']);
                // Validar la clave con la lógica de la entidad
                $array_errores = $usuario->validaClave($data['clave']);
                $entra = true;
                // Si algún error es 0, hay fallo
                for($i = 0; $i < count($array_errores); $i++){
                    if($array_errores[$i] == 0){
                        $entra = false;
                    }
                }
                if($entra){
                    // Establecer la clave y marcar que se ha cambiado
                    $usuario->setClave($data['clave']);
                    $set = 1;
                    $msg_ok = 'La clave ha sido actualizada correctamente';
                }
                $id = $data['id_usu'];
            }
        }else{
            // Si no es POST: determinar qué usuario visualizar (admin puede pasar id en ruta)
            if($this->_usuario->get('rol') == '4dmin'){
                $id = (int)$this->params()->fromRoute('v1',0);
            }else{
                $id = (int)$this->_usuario->get('id');
            }
            // Cargar la entidad Usuario para mostrar datos en la vista
            $usuario = new Usuario($id);
        }

        // Si existe usuario (id>0) cargar pestañas relacionadas: inscripciones, inscritos, candidaturas, menores, carpetas y permisos
        if($id > 0){
            // Ajustar el título del layout con el nombre del usuario
            $this->layout()->title = $usuario->get('nombre-completo').' | Usuario';

            // Inscripciones relacionadas: paginación similar a indexAction
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
            // Atención: aquí se construye WHERE concatenando 'id_usu = '.$id -> revisar parametrización en refactor
            $inscripciones = $db_ins->getInscripciones('id_usu = '.$id,'id_usu DESC',50,$offseti);
            $numi = $db_ins->numInscripciones('id_usu = '.$id);

            // Inscritos relacionados (otra paginación)
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

            // Carpetas y permisos: obtener carpetas y los permisos del usuario para cada carpeta
            $db_carpetas = new Carpetas();
            $carpetas = $db_carpetas->get(null, 'nombre ASC');
            $db_permisos = new Permisos();
            $permisos = [];
            foreach($carpetas as $carpeta):
                // Obtener permiso concreto para esta carpeta y el usuario
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
            // Si no hay ID (usuario nuevo), inicializar variables vacías para la vista
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

        // Preparar datos para la vista y devolver ViewModel
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
	
    /**
     * borrarAction
     *
     * Propósito: borrar un usuario por id y redirigir al listado con código de resultado.
     */
    public function borrarAction(){
        // Obtener id desde la ruta (v1)
        $id = (int)$this->params()->fromRoute('v1',0);
        // Instanciar entidad Usuario con ese id
        $object = new Usuario($id);
        // Intentar eliminar el registro; remove() devuelve código (ok/error)
        $ok = $object->remove();
        // Redirigir al listado de usuarios pasando la página actual y el código de resultado
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'index','v1' => (int)$this->_container->usua_page,'v2' => $ok));
    }

    /**
     * cvAction
     *
     * Propósito: subir o eliminar el currículum (CV) de un usuario.
     * - POST: procesar upload y guardar nombre de fichero en la entidad Usuario.
     * - GET/otro: eliminar el CV asociado al usuario.
     */
    public function cvAction(){
        // Código de resultado por defecto (error genérico al subir)
        $msg = 550;
        // Si la petición es POST procesamos un upload
        if($this->request->isPost()) {
            // Obtener id de usuario enviado en POST
            $id_usu = (int)$this->request->getPost('id_usu');
            // Cargar entidad Usuario
            $usuario = new Usuario($id_usu);
            // Directorio donde se guardan los CV (constante definida en la entidad)
            $nombreDirectorio = $usuario::FILE_DIRECTORY_CV;

            $data = [];
            // Usamos StripTags para sanear entradas de texto si hiciera falta
            $filter = new StripTags();

            // Configurar adaptador HTTP para recibir archivos
            $httpadapter = new \Zend\File\Transfer\Adapter\Http();
            // Validadores: tamaño máximo 10MB y extensiones permitidas
            $filesize = new \Zend\Validator\File\Size(array('min' => '0kB', 'max' => '10MB'));
            $extension = new \Zend\Validator\File\Extension(array('extension' => array('pdf', 'doc', 'docx')));
            $httpadapter->setValidators(array($filesize, $extension));

            // Obtener información del fichero enviado (campo 'cv')
            $files = $httpadapter->getFileInfo('cv');
            // Si pasa validadores
            if ($httpadapter->isValid()) {
                // Nombre original del fichero
                $fichero = $files['cv']['name'];
                // Extraer extensión
                $ext = pathinfo($fichero, PATHINFO_EXTENSION);
                // Generar nombre único basado en timestamp
                $fichero = time() . "." . $ext;
                // Añadir filtro para renombrar el fichero al destino
                $httpadapter->addFilter('filerename', ['target' => $nombreDirectorio . $fichero]);
                // Establecer el directorio destino
                $httpadapter->setDestination($nombreDirectorio);

                // Recibir/mover el fichero al destino
                if ($httpadapter->receive($files['cv']['name'])) {
                    // Guardar el nombre de fichero en la entidad Usuario
                    $usuario->setCv($fichero);
                    // Código de éxito para subida CV
                    $msg = 548;
                }
            }else{
                // Si no pasó validaciones, marcar con código específico
                $msg = 549;
            }
        }else{
            // Si no es POST, se entiende que se solicita eliminar el CV
            $id_usu = (int)$this->params()->fromRoute('v1',0);
            $usuario = new Usuario($id_usu);
            // Lógica de la entidad para borrar fichero y referencia
            $usuario->removeCv();
            $msg = 551;

        }
        // Redirigir a la ficha del usuario pasando el código de resultado en v6
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => $id_usu,'v2' => -1,'v3' => -1,'v4' => -1,'v5' => -1,'v6' => $msg));
    }
    
    /**
     * xlsAction
     *
     * Propósito: exportar la lista de usuarios a un fichero Excel.
     * Nota: actualmente envía headers y termina con die(); se recomienda
     * refactorizar para devolver un StreamResponse.
     */
    public function xlsAction(){
        // Generar la cláusula WHERE a partir del buscador guardado en sesión
        $where = Utilidades::generaCondicion('usuarios', $this->_container->usua_buscador);
        // Instanciar mapper Usuarios y obtener objetos que cumplan la condición
        $db = new Usuarios();
        $objects = $db->get($where,['apellidos','nombre']);
        // Usar utilidad Exportar para crear el objeto PHPExcel con los datos
        $objPHPExcel = Exportar::usuarios($objects);

        // Envío de headers para forzar descarga como Excel (xls)
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        // Crear writer para formato Excel5 y guardar a la salida estándar
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        // Finalizar ejecución (nota: usar StreamResponse es mejor práctica)
        die('Excel generado');
        exit;
    }

    /**
     * importarcolegiadosAction
     *
     * Propósito: importar usuarios/colegiados desde un fichero Excel y crear
     * cuentas automáticamente en base a las columnas definidas.
     * Nota: este script asume una estructura de columnas concreta; usar con
     * cuidado y validar el contenido del fichero antes de ejecutar en
     * producción.
     */
    public function importarcolegiadosAction(){
        // Ruta del fichero de importación (fichero predefinido)
        $file_import = './data/import/ListadoGenerado_Definitivo.xlsx';
        // Detectar tipo de fichero y cargarlo con PHPExcel
        $inputFileType = \PHPExcel_IOFactory::identify($file_import);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($file_import);

        // Usar la primera hoja
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $i = 0; // contador de registros importados
        // Iterar filas a partir de la 2 (suponiendo cabecera en fila 1)
        for ($row = 2; $row <= $highestRow; $row++){

            $data = [];

            // Preparar array de datos con mapeo de columnas
            $data['id_usu'] = 0;
            $data['nombre'] = $sheet->getCell("C".$row)->getValue();
            $data['apellidos'] = $sheet->getCell("B".$row)->getValue();
            $data['colegiado'] = $sheet->getCell("A".$row)->getValue();
            $data['telefono'] = $sheet->getCell("H".$row)->getValue();
            $data['email'] = $sheet->getCell("I".$row)->getValue();
            $data['nif'] = $sheet->getCell("D".$row)->getValue();
            // Normalizar sexo según valores en la hoja
            if($sheet->getCell("E".$row)->getValue() == 'H'){
                $data['sexo'] = 2;
            }else if($sheet->getCell("E".$row)->getValue() == 'M'){
                $data['sexo'] = 1;
            }else{
                $data['sexo'] = 0;
            }
            // Fecha de nacimiento: puede venir con hora -> tomar la parte de fecha
            $nacimiento = explode(' ', $sheet->getCell("G".$row)->getValue());
            if($nacimiento[0] != ''){
                $data['nacimiento'] = date('d-m-Y', strtotime(Utilidades::giraFecha($nacimiento[0])));
            }else{
                $data['nacimiento'] = null;
            }

            $data['clave'] = $sheet->getCell("J".$row)->getValue();

            // Valores por defecto para campos no incluidos en el Excel
            $data['rol'] = 'c4legiado';
            $data['cv'] = null;
            $data['id_emp'] = null;
            $data['autorizado'] = 0;
            $data['sitlab'] = 0;

            // Determinar sitcol según columnas booleanas
            $sitcol = 0;
            if($sheet->getCell("M".$row)->getValue() == 'True'){
                $sitcol = 1;
            }
            if($sheet->getCell("N".$row)->getValue() == 'True'){
                $sitcol = 2;
            }
            if($sheet->getCell("O".$row)->getValue() == 'True'){
                $sitcol = 3;
            }
            if($sheet->getCell("P".$row)->getValue() == 'True'){
                $sitcol = 4;
            }

            $data['sitcol'] = $sitcol;
            $data['titulacion'] = null;
            $data['master'] = null;
            // Atención: debajo hay accesos a índices $row['empleo'] etc. que parecen erróneos
            // Mantener el comportamiento original, pero deberían revisarse en un refactor.
            $data['empleo'] = (int)$row['empleo'];
            $data['experiencia'] = (int)$row['experiencia'];
            $data['especialidad'] = null;
            $data['jornada'] = (int)$row['jornada'];
            $data['alta'] = date('d-m-Y');
            $data['baja'] = null;
            $data['sincro'] = date('d-m-Y');

            // Crear y guardar usuario con caso de creación (2)
            $usuario = new Usuario(0);
            $usuario->set($data, 2);
            $id_usu = $usuario->save();
            if($id_usu > 0){
                $i++;
            }
        }

        // Mostrar resultado en salida (este script está pensado para ejecución CLI o similar)
        echo 'Se han importado ' . $i . ' colegiados.';

        die();
    }
    
    /**
     * actualizacolAction
     *
     * Propósito: actualizar datos de colegiados desde un Excel (precol o col)
     * - Si existe el usuario lo actualiza, si no lo crea.
     */
    public function actualizacolAction(){
        // Parámetro v1 indica tipo (precol o col)
        $precol = (int)$this->params()->fromRoute('v1',0);
        if((int)$precol){
            $file_import = './data/import/ListadoPrecol.xlsx';
        }else{
            $file_import = './data/import/ListadoCol.xlsx';
        }
        // Detectar y cargar fichero con PHPExcel
        $inputFileType = \PHPExcel_IOFactory::identify($file_import);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($file_import);

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $db_usu = new Usuarios();
        $i = 0;
        // Iterar filas
        for ($row = 2; $row <= $highestRow; $row++){
            $data = [];
            // Leer número de colegiado y buscar usuario existente
            $colegiado = $sheet->getCell("A".$row)->getValue();
            $usuario = $db_usu->getByColegiado($colegiado);

            // Normalizar sexo
            if($sheet->getCell("E".$row)->getValue() == 'H'){
                $data['sexo'] = 2;
            }else if($sheet->getCell("E".$row)->getValue() == 'M'){
                $data['sexo'] = 1;
            }else{
                $data['sexo'] = 0;
            }
            // Delegación
            $data['delegacion'] = $sheet->getCell("I".$row)->getValue();

            if((int)$precol){
                // Si es precol, ciertos campos están en columnas distintas
                $data['observaciones'] = $sheet->getCell("L".$row)->getValue();
                $data['sitcol'] = 1;
                $pp = $sheet->getCell("K".$row)->getValue();
            }else{
                $data['observaciones'] = $sheet->getCell("N".$row)->getValue();
                $data['sitcol'] = 0;
                $pp = $sheet->getCell("M".$row)->getValue();
            }
            // Pago pendiente bandera
            if($pp == 'True'){
                $data['pago_pendiente'] = 1;
            }else{
                $data['pago_pendiente'] = 0;
            }

            if((int)$usuario->get('id')){
                // Si el usuario existe, actualizarlo (caso 3)
                $usuario->set($data,3);
                $usuario->save();
            }else{
                // Si no existe, crear un nuevo registro combinando más columnas
                $data['colegiado'] = $colegiado;
                $data['apellidos'] = $sheet->getCell("B".$row)->getValue();
                $data['nombre'] = $sheet->getCell("C".$row)->getValue();
                $data['nif'] = $sheet->getCell("D".$row)->getValue();
                $data['telefono'] = $sheet->getCell("G".$row)->getValue();
                $data['email'] = $sheet->getCell("H".$row)->getValue();
                // Fecha nacimiento similar al anterior
                $nacimiento = explode(' ', $sheet->getCell("F".$row)->getValue());
                if($nacimiento[0] != ''){
                    $data['nacimiento'] = date('d-m-Y', strtotime(Utilidades::giraFecha($nacimiento[0])));
                }else{
                    $data['nacimiento'] = null;
                }
                // Valores por defecto para creación
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
        // Mostrar contador y finalizar
        echo $i;
        die();
    }
    
    public function fotoAction(){
        
    }
    
    /**
     * menoresAction
     *
     * Propósito: añadir/editar menores asociados a un usuario desde un formulario
     * que puede guardar todos los menores o uno en concreto.
     */
    public function menoresAction(){
        // Solo procesar si la petición es POST (envío del formulario)
        if($this->request->isPost()){
            $data = [];
            // 'boton' indica la acción: 'guardar-todos' o índice de fila
            $boton = $this->request->getPost('boton');
            $id_usu = $this->request->getPost('id_usu');
            $id_mens = $this->request->getPost('id_men');
            $nombres = $this->request->getPost('nombre');
            $apellidos = $this->request->getPost('apellidos');
            $observaciones = $this->request->getPost('observaciones');
            $nacimiento = $this->request->getPost('nacimiento');
            if($boton == 'guardar-todos'){
                // Guardar todos los menores enviados en arrays
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
                        // Crear entidad Menor y guardarla
                        $object = new Menor(0);
                        $object->set($data);
                        $object->save();
                    }
                endforeach;
            }else{
                // Guardar solo el menor indicado por índice (botón individual)
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
            // Redirigir a la ficha del usuario y mostrar mensaje de éxito (v5 = 547)
            return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => (int)$id_usu,'v2' => 0,'v3' => 0,'v4' => 0,'v5' => 547));
        }else{
            // Si no es POST, redirigir al listado de usuarios
            return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'index','v1' => (int)$this->_container->usua_page));
        }
    }
    
    /**
     * borrarmenorAction
     *
     * Propósito: eliminar un menor por id y redirigir a la ficha del usuario propietario.
     */
    public function borrarmenorAction(){
        // Obtener id menor desde ruta
        $id = (int)$this->params()->fromRoute('v1',0);
        // Cargar entidad Menor
        $object = new Menor($id);
        // Eliminar y guardar código resultado
        $ok = $object->remove();
        // Redirigir a ficha del usuario asociado y pasar código en v5
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => (int)$object->get('id_usu'),'v2' => 0,'v3' => 0,'v4' => 0,'v5' => $ok));
    }

    /**
     * carpetasAction
     *
     * Propósito: listar y editar carpetas (gestor documental) con paginación y
     * actualización en lote.
     */
    public function carpetasAction(){
        // Título del layout
        $this->layout()->title = 'Carpetas';
        // Código de mensaje en v2
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Interpretar códigos de resultado
        if($idm == 525){
            $msg_ok = 'La carpeta ha sido borrada correctamente.';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar la carpeta porque tiene usuarios relacionados.';
        }
        // Instanciar mapper Carpetas
        $db = new Carpetas();
        $orderby = 'nombre ASC';
        if($this->request->isPost()){
            $data = [];
            // 'boton' indica la acción: buscar, guardar-todos o índice
            $boton = $this->request->getPost('boton');
            if($boton == 'buscar'){
                // Guardar filtro por id_car en sesión
                $data['id_car'] = (int)$this->request->getPost('id_car');
                $this->_container->car_buscador = $data;
            }else if($boton == 'guardar-todos'){
                // Actualizar múltiples carpetas enviadas como arrays
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
                // Guardar un único registro según índice del botón
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
            // Si la ruta indica limpiar filtros (v2 == 114), borrar buscador en sesión
            if($idm == 114){
                if(isset($this->_container->car_buscador)){
                    unset($this->_container->car_buscador);
                    $this->_container->car_page = 0;
                }
            }
        }

        // Paginación (similar al patrón usado en indexAction)
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

        // Construcción de la condición de búsqueda desde sesión
        if(isset($this->_container->car_buscador)){
            $where = Utilidades::generaCondicion('carpetas', $this->_container->car_buscador);
            $this->_container->car_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->car_buscador;
        }else{
            $buscador = array('id_car' => 0,'nombre' => null);
            $where = null;
        }

        // Leer carpetas y número total
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

    /**
     * borrarcarpetaAction
     *
     * Propósito: eliminar una carpeta por id y redirigir a la lista con código.
     */
    public function borrarcarpetaAction(){
        $id = (int)$this->params()->fromRoute('v1',0);
        $object = new Carpeta($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'carpetas','v1' => (int)$this->_container->car_page,'v2' => $ok));
    }


    /**
     * permisosAction
     *
     * Propósito: guardar permisos de acceso a carpetas para un usuario. Se
     * puede guardar en lote ('guardar-todos') o individualmente según botón.
     */
    public function permisosAction(){
        if($this->request->isPost()){
            $data = [];
            $boton = $this->request->getPost('boton');
            if($boton == 'guardar-todos'){
                // Arrays con ids y permisos enviados desde el formulario
                $id_pers =  $this->request->getPost('id_per');
                $id_cars =  $this->request->getPost('id_car');
                $permisos =  $this->request->getPost('permiso');
                $id_usu =  $this->request->getPost('id_usu');

                // Iterar y guardar cada permiso
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
                // Guardar un permiso individual según índice del botón
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
            // Código resultado para mostrar en la ficha
            $ok = 552;
        }

        // Redirigir a la ficha del usuario pasando el código de resultado
        return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha','v1' => $id_usu, 'v2' => -1, 'v3' => -1, 'v4' => -1, 'v5' => -1,'v6' => $ok));
    }

    /**
     * xaAction
     *
     * Propósito: endpoint AJAX usado por autocompletes/búsquedas en la UI.
     * Parámetros vía ruta (v1) y query (q). Devuelve JSON con resultados.
     */
    public function xaAction(){
        // Determinar tipo de operación AJAX según parámetro de ruta v1
        $ajax = (int)$this->params()->fromRoute('v1',0);
        $answer = [];
        if($ajax == 1){
            // Autocomplete por término de búsqueda (campo 'q' en query string)
            // Nota: en el código original se usa $_GET; es preferible usar
            // $this->params()->fromQuery('q') para mayor claridad y testabilidad.
            $term = $this->params()->fromQuery('q');
            $db = new Usuarios();
            // Atención: la consulta construida con concatenación es vulnerable a inyección
            $objects = $db->get('nombre LIKE "%'.$term.'%" OR apellidos LIKE "%'.$term.'%" OR CONCAT(nombre," ",apellidos) LIKE "%'.$term.'%"', ['nombre','apellidos']);
            if(count($objects)>0){
                foreach($objects as $object):
                    $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('nombre-completo')];
                endforeach;
            }else{
                $answer[] = ["id"=>"0","text"=>"No existen resultados."];
            }
        }else if($ajax == 2){
            // Búsqueda por id (campo 'q' con id numérico)
            $term = (int)$this->params()->fromQuery('q');
            $object = new Usuario($term);
            if($object->get('id')>0){
                $answer[] = ["id"=>$object->get('id'),"text"=>$object->get('nombre-completo')];
            }else{
                $answer[] = ["id"=>"0","text"=>""];
            }
        }
        // Devolver JSON al cliente
        return $this->getResponse()->setContent(Json::encode($answer));
    }
}
