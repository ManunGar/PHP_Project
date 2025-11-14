<?php
namespace Backend\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Inscripciones;
use Application\Model\Entity\Candidaturas;
use Application\Model\Entity\Cursos;
use Application\Model\Entity\Ofertas;
use Application\Model\Entity\Empresas;
use Application\Model\Entity\Carpetas;
use Application\Model\Entity\Carpeta;
use Application\Model\Utility\Utilidades;

class IndexController extends AbstractActionController{

    /**
     * IndexController
     * 
     * Resumen de responsabilidades de este controlador:
     * - Presentar la página de inicio del área Backend (`indexAction`) con
     *   múltiples listados (inscripciones, candidaturas, cursos, ofertas, empresas)
     *   que varían según el rol del usuario autenticado.
     * - Proveer acceso al gestor documental (`documentosAction`) filtrando
     *   las carpetas a las que el usuario tiene permiso.
     * - Rutas simples de error y permiso (`errorAction`, `permisoAction`).
     * - Acción de desarrollo/administración (`devAction`) para ejecutar tareas
     *   puntuales (scripts). Esta acción devuelve texto directo y actualmente
     *   finaliza la ejecución con `die()`.
     * 
     * Notas para un lector sin conocimientos de PHP:
     * - Cada método con sufijo `Action` corresponde a una ruta que se mapea en
     *   el framework y normalmente devuelve una `ViewModel` con datos para la vista.
     * - Este controlador usa una entidad `Usuario` cargada en el constructor a
     *   partir del servicio de autenticación para conocer rol, id y permisos.
     * - Muchas consultas preparadas en este controlador construyen condiciones
     *   SQL como cadenas. Esto funciona aquí porque las entidades internas esperan
     *   dichas condiciones, pero es una práctica de riesgo si no se parametriza
     *   correctamente (riesgo de inyección SQL). Ver las notas de seguridad.
     * 
     * Métodos principales:
     * - __construct: carga la identidad del usuario logueado y prepara el contenedor de sesión.
     * - indexAction: lógica de presentación de la página principal con listados.
     * - documentosAction: preparación de datos para el gestor documental (carpetas y permisos).
     * - errorAction / permisoAction: vistas simples para mostrar mensajes de error/permiso.
     * - devAction: utilidad de administración que ejecuta scripts según un parámetro de ruta.
     * 
     * Riesgos / recomendaciones rápidas:
     * - Evitar concatenar datos no validados dentro de condiciones SQL.
     * - Reemplazar `die()` por respuestas controladas si se convierte en funcionalidad pública.
     */

    protected $_usuario;
    protected $_container;
    protected $_tipo;

    /**
     * Constructor
     * - Carga la identidad del usuario si hay una sesión de autenticación.
     * - Inicializa `$this->_usuario` como entidad `Usuario` y un contenedor de sesión.
     */
    public function __construct(){
        // Instancia del servicio de autenticación de Zend
        $auth = new AuthenticationService();

        // Obtener la identidad (si existe) del servicio de autenticación
        $identity = $auth->getIdentity();

        // Comprobar si hay un usuario autenticado
        if ($auth->hasIdentity()) {
            // Crear la entidad Usuario usando el id obtenido de la identidad
            $usuario = new Usuario($identity->id_usu);

            // Guardar la entidad en la propiedad de la clase para usar en otras acciones
            $this->_usuario = $usuario;

            // Instanciar un contenedor de sesión (espacio de nombres 'namespace')
            // Se usa para almacenar datos persistentes de la sesión en otras acciones
            $this->_container = new Container('namespace');
        }
    }

    /**
     * indexAction
     * - Presenta la pantalla de inicio del backend con distintos listados según rol.
     * - Devuelve una `ViewModel` con los arrays necesarios para la vista.
     */
    public function indexAction(){
        // Si el usuario tiene el rol de administrador (valor en este proyecto '4dmin')
        // ejecutar tareas cron internas (función de utilidad).
        if($this->_usuario->get('rol') == '4dmin'){
            // Ejecuta tareas programadas internas (sin parámetros aquí)
            Utilidades::ejecutaCronJobs();
        }

        // Establece el título que usará la plantilla/layout en la vista
        $this->layout()->title = 'Inicio';

        // Inicializar contador de autorizados pendientes (se calculará sólo para admin)
        $num_autorizados_por_confirmar = 0;

        // Determinar qué datos mostrar según el rol del usuario
        if($this->_usuario->get('rol') == '4dmin'){
            // Para administrador: mostrar inscripciones con ciertos estados
            $where_inscripciones = 'estado = 1 OR estado = 2 OR estado = 6';

            // Cursos abiertos
            $where_cursos = 'estado = 1';

            // No se muestran inscritos por defecto para admin (variable nula)
            $where_inscritos = null;

            // Empresas pendientes por aprobar
            $where_empresas = 'estado = 0';

            // Ofertas pendientes por aprobar
            $where_ofertas = 'estado = 0';

            // Calcular fecha hace 7 días para filtrar candidaturas recientes
            $fecha_menos_7_dias = strtotime ( '-7 day' , strtotime ( date('Y-m-d H:i:s') ) ) ;
            // Formatear la fecha para comparar en la base de datos
            $fecha_menos_7_dias = date ( 'Y-m-d  H:i:s' , $fecha_menos_7_dias );
            // Condición para candidaturas desde la fecha calculada
            $where_candidaturas = 'candidaturasFecha >= "' .  $fecha_menos_7_dias . '"';

            // Títulos que se mostrarán en la interfaz
            $titulo_inscripciones = 'Inscripciones';
            $titulo_candidaturas = 'Últimas candidaturas';
            $titulo_cursos = 'Cursos abiertos';
            $titulo_ofertas = 'Ofertas por aprobar';
            $titulo_empresas = 'Empresas por aprobar';
            $titulo_inscritos = '';

            // Contador de usuarios autorizados pendientes por confirmar
            $db_usu = new \Application\Model\Entity\Usuarios();
            // Llamada a método que devuelve número de usuarios con condición
            $num_autorizados_por_confirmar = $db_usu->num('autorizado = 2');

        }else if($this->_usuario->get('rol') == 'c4legiado'){
            // Para colegiado ('c4legiado'): configurar filtros personalizados
            $where_inscripciones = null; // no se muestran inscripciones generales
            $where_cursos = 'estado = 1';
            // Mostrar inscripciones del propio usuario o creadas por él, excluyendo cursoEstado 3
            $where_inscritos = '(id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ') AND cursoEstado != 3';
            $where_empresas = null;
            $where_ofertas = 'estado = 1';

            // Si el colegiado está autorizado, puede ver candidaturas ampliadas (por empresa o usuario)
            if($this->_usuario->get('autorizado') == 1){
                $where_candidaturas = '(id_usu = ' . $this->_usuario->get('id') . ' OR id_emp = ' . $this->_usuario->get('id_emp') . ') AND ofertasEstado != 4';
            }else{
                // Si no está autorizado, sólo veremos las candidaturas del propio usuario
                $where_candidaturas = 'id_usu = ' . $this->_usuario->get('id') . ' AND ofertasEstado != 4';
            }

            // Títulos para la interfaz de colegiado
            $titulo_inscripciones = '';
            $titulo_candidaturas = 'Candidaturas no descartados';
            $titulo_cursos = 'Cursos abiertos';
            $titulo_ofertas = 'Ofertas abiertas';
            $titulo_empresas = '';
            $titulo_inscritos = 'Inscripciones a cursos no terminados';

        }else if($this->_usuario->get('rol') == 'us4ario'){
            // Usuario normal: menos datos, sólo cursos abiertos e inscritos propios
            $where_inscripciones = null;
            $where_cursos = 'estado = 1';
            $where_inscritos = '(id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ')  AND cursoEstado != 3';
            $where_empresas = null;
            $where_ofertas = null;
            $where_candidaturas = null;

            $titulo_inscripciones = '';
            $titulo_candidaturas = '';
            $titulo_cursos = 'Cursos abiertos';
            $titulo_ofertas = '';
            $titulo_empresas = '';
            $titulo_inscritos = 'Inscripciones a cursos no terminados';
        }

        /*
         * Preparar los datos que se pasarán a la vista.
         * Cada bloque comprueba si existe la condición `where_*` y, en caso afirmativo,
         * llama a la entidad correspondiente para recuperar los registros.
         */

        // Inscripciones
        if(isset($where_inscripciones)){
            // Instancia del modelo de inscripciones
            $db_inscripciones = new Inscripciones();
            // Orden por defecto
            $orderby_inscripciones = 'id_ins DESC';
            // Leer de base de datos (máx 200 registros en este llamado)
            $inscripciones = $db_inscripciones->getInscripciones($where_inscripciones,$orderby_inscripciones,200);
            // Contar inscripciones que cumplen la condición
            $num_inscripciones = $db_inscripciones->numInscripciones($where_inscripciones);
        }else{
            // Si no hay condición, inicializar arrays vacíos para la vista
            $inscripciones = [];
            $num_inscripciones = 0;
        }

        // Candidaturas
        if(isset($where_candidaturas)){
            $db_candidaturas = new Candidaturas();
            $orderby_candidaturas = 'usuariosNombre ASC';
            // Leer de base de datos
            $candidaturas = $db_candidaturas->getCandidaturas($where_candidaturas,$orderby_candidaturas);
            // Contar resultados
            $num_candidaturas = $db_candidaturas->numCandidaturas($where_candidaturas);
        }else{
            $candidaturas = [];
            $num_candidaturas = 0;
        }

        // Cursos
        if(isset($where_cursos)){
            $db_cursos = new Cursos();
            $orderby_cursos = 'nombre ASC';
            // Leer cursos
            $cursos = $db_cursos->get($where_cursos,$orderby_cursos);
            $num_cursos = $db_cursos->num($where_cursos);
        }else{
            $cursos = [];
            $num_cursos = 0;
        }

        // Ofertas de empleo
        if(isset($where_ofertas)) {
            $db_ofertas = new Ofertas();
            $orderby_ofertas = 'titulo ASC';
            // Leer ofertas
            $ofertas = $db_ofertas->getOfertas($where_ofertas, $orderby_ofertas);
            $num_ofertas = $db_ofertas->num($where_ofertas);
        }else{
            $ofertas = [];
            $num_ofertas = 0;
        }

        // Empresas
        if(isset($where_empresas)){
            $db_empresas = new Empresas();
            $orderby_empresas = 'empresasNombre ASC';
            // Leer empresas
            $empresas = $db_empresas->getEmpresas($where_empresas,$orderby_empresas);
            $num_empresas = $db_empresas->num($where_empresas);
        }else{
            $empresas = [];
            $num_empresas = 0;
        }

        // Inscripciones a cursos no terminados (inscritos)
        if(isset($where_inscritos)){
            $db_inscritos = new Inscripciones();
            $orderby_inscritos = 'usuariosNombre ASC';
            // Leer inscritos
            $inscritos = $db_inscritos->getInscritos($where_inscritos,$orderby_inscritos);
            $num_inscritos = $db_inscritos->numInscritos($where_inscritos);
        }else{
            $inscritos = [];
            $num_inscritos = 0;
        }

        // Preparar el array que pasaremos a la vista
        $view = array(
            'usuario'               =>  $this->_usuario,
            'title'                 =>  'Inicio',
            'inscripciones'         =>  $inscripciones,
            'num_inscripciones'     =>  $num_inscripciones,
            'candidaturas'          =>  $candidaturas,
            'num_candidaturas'      =>  $num_candidaturas,
            'cursos'                =>  $cursos,
            'num_cursos'            =>  $num_cursos,
            'ofertas'               =>  $ofertas,
            'num_ofertas'           =>  $num_ofertas,
            'empresas'              =>  $empresas,
            'num_empresas'          =>  $num_empresas,
            'inscritos'             =>  $inscritos,
            'num_inscritos'         =>  $num_inscritos,
            'titulo_inscripciones'  =>  $titulo_inscripciones,
            'titulo_candidaturas'   =>  $titulo_candidaturas,
            'titulo_cursos'         =>  $titulo_cursos,
            'titulo_ofertas'        =>  $titulo_ofertas,
            'titulo_empresas'       =>  $titulo_empresas,
            'titulo_inscritos'      =>  $titulo_inscritos,
            'num_autorizados_por_confirmar' => $num_autorizados_por_confirmar
        );

        // Devolver ViewModel con los datos para la plantilla
        return new ViewModel($view);
    }

    /**
     * documentosAction
     * - Prepara los datos necesarios para el gestor documental del usuario.
     * - Construye una lista de carpetas con permisos (lectura/escritura/oculto)
     *   y la serializa para que la vista la recupere desde el layout.
     */
    public function documentosAction(){
        // Título para la vista/layout
        $this->layout()->title = 'Gestor documental';

        // Mensajes por defecto
        $msg_ok = null;
        $msg_error = null;

        // Instanciar modelo de carpetas para obtener permisos de usuario
        $db_carpetas = new Carpetas();

        // Obtener permisos: true si id_usu es NULL (permiso global) o es igual al usuario actual
        $permisos = $db_carpetas->getPermisosUsuarios('id_usu IS NULL OR id_usu = ' . $this->_usuario->get('id'), 'nombrea ASC');

        // Array que almacenará los datos de carpetas que el usuario puede ver
        $carpetas = [];

        // Recorrer los permisos devueltos por el modelo
        foreach($permisos as $per):
            // Si hay un permiso y es mayor que 0 significa que hay algún nivel de acceso
            if(isset($per['permiso']) && $per['permiso'] > 0){
                // Si el permiso es 1: sólo lectura en la raíz (pattern '/^\/$/') y sin escritura
                if($per['permiso'] == 1){
                    $arr_per = [
                        'pattern' => '/^\/$/',
                        'read'    => true,
                        'write'   => false,
                        'locked'  => true,
                        'hidden'  => false,
                    ];
                }else{
                    // Nivel de permiso mayor: lectura y escritura permitidas
                    $arr_per = [
                        'read'    => true,
                        'write'   => true,
                        'locked'  => true,
                        'hidden'  => false,
                    ];
                }

                // Crear entidad Carpeta con la información del permiso
                $carpeta = new Carpeta($per['id_car']);

                // Añadir al array de salida: id (hash), nombre y permisos calculados
                $carpetas[] = [
                    'id'       => $carpeta->getHashFolder(),
                    'nombre'   => $per['nombre'],
                    'permisos'  => $arr_per
                ];
            }
        endforeach;

        // Si hay carpetas con permisos, serializarlas y pasar por layout (uso en frontend JS)
        if(count($carpetas) > 0){
            // `serialize` convierte el array en string; `urlencode` lo prepara para transportarlo
            $this->layout()->carpetas_permisos = urlencode(serialize($carpetas));
        }else{
            // Si no tiene permisos para ninguna carpeta, preparar mensaje de error
            $msg_error = 'No tiene permisos para este apartado.';
        }

        // Preparar datos para la vista y devolver ViewModel
        $view = array(
            'usuario'   =>  $this->_usuario,
            'ko'        =>  $msg_error,
        );
        return new ViewModel($view);
    }

    /**
     * errorAction
     * - Vista simple para mostrar un error de ejecución.
     */
    public function errorAction(){
        // Título de la vista
        $this->layout()->title = 'Error de ejecución';
        // Pasar usuario a la vista
        $view = array(
            'usuario'   =>  $this->_usuario,
        );
        return new ViewModel($view);
    }

    /**
     * permisoAction
     * - Vista simple para mostrar un error por falta de permisos.
     */
    public function permisoAction(){
        // Título de la vista
        $this->layout()->title = 'Error de permisos';
        // Pasar usuario a la vista
        $view = array(
            'usuario'   =>  $this->_usuario,
        );
        return new ViewModel($view);
    }

    /**
     * devAction
     * - Acción de desarrollo/administración que ejecuta tareas según un parámetro
     *   de ruta llamado `v1`.
     * - ATENCIÓN: actualmente termina la ejecución con `die($msg)` y por tanto no
     *   se recomienda exponerla en producción.
     */
    public function devAction(){
        // Tomar el parámetro `v1` de la ruta y convertirlo a entero (por seguridad)
        $tarea = (int) $this->params()->fromRoute('v1', 0);

        // Comprar la tarea solicitada y ejecutar bloques de código en función de su valor
        if($tarea == 1){
            // Ejemplo: instancia del modelo de usuarios
            $db_usu = new \Application\Model\Entity\Usuarios();
            // Llamada comentada: rellena número de colegiado (script desactivado)
            // $db_usu->rellenaNumColegiado();
        }else if($tarea == 2){
            // Otra tarea de ejemplo: instancia de empresas
            $db = new Empresas();
            // Llamada comentada: eliminar duplicados (desactivada)
            // $db->eliminarDuplicados();
        }

        // Según si se indicó una tarea, preparar mensaje de salida
        if($tarea){
            $msg = 'Script finalizado';
        }else{
            $msg = 'Debe indicar una tarea';
        }

        // NOTA: `die()` detiene inmediatamente la ejecución y devuelve el texto.
        // En entornos más controlados se preferiría devolver una Response o ViewModel.
        die($msg);
    }
}
