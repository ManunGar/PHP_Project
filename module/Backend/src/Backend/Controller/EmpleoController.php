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

/**
 * EmpleoController
 *
 * Resumen general (bloque explicativo al inicio de la clase):
 * Este controlador agrupa la lógica relacionada con la gestión de ofertas
 * de empleo, candidaturas y sectores dentro del módulo "Backend".
 *
 * Responsabilidades principales:
 * - Mostrar, crear, editar y borrar ofertas de empleo.
 * - Listar ofertas públicas para el área de empleo (`ofertasempleoAction`).
 * - Gestionar candidaturas (presentar CV, listar, borrar, exportar a Excel).
 * - Gestionar sectores vinculados a ofertas.
 * - Proveer endpoints AJAX para búsquedas y autocompletado.
 *
 * Contexto de uso:
 * - Este controlador asume que el usuario está autenticado; en el
 *   constructor se obtiene la identidad y se inicializa la variable
 *   `$_usuario` (entidad `Usuario`) y el contenedor de sesión `$_container`.
 * - Muchas acciones usan entidades del dominio (`Oferta`, `Candidatura`,
 *   `Sector`) que implementan lógica de acceso a la base de datos.
 * - Las acciones devuelven `ViewModel` con datos que las plantillas renderizan
 *   (estructura típica MVC de Zend Framework).
 *
 * Nota para principiantes en PHP/Zend:
 * - Cada "Action" (método que termina en "Action") puede ser invocado por
 *   una ruta; por ejemplo, `/backend/empleo/ofertas` ejecuta `ofertasAction()`.
 */
class EmpleoController extends AbstractActionController{
    
    // Entidad Usuario asociada a la identidad autenticada
    protected $_usuario;
    // Contenedor de sesión para guardar filtros/paginación temporales
    protected $_container;
    // (no usado en este controlador pero definido) tipo auxiliar
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
	
    // indexAction: redirige a la lista de ofertas (acción `ofertasAction`)
    public function indexAction(){
        // Línea a línea:
        // return $this->redirect()->toRoute(...): construye una respuesta
        // RedirectResponse que indica al navegador ir a la ruta 'backend/default'
        // con parámetros controller=empleo, action=ofertas y v1=1
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertas','v1' => 1));
    }
	
    // ofertasAction: lista y filtra ofertas de empleo.
    public function ofertasAction(){
        // 1) Ajusta título del layout para mostrar en la página
        $this->layout()->title = 'Ofertas';

        // 2) Lee parámetros de ruta y prepara mensajes
        // $idm: código de mensaje opcional pasado en la ruta (v2)
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Mensajes intercambiados por otras acciones (por ejemplo borrar)
        if($idm == 525){
            // 525 indica que se borró correctamente una oferta
            $msg_ok = 'La oferta ha sido borrada correctamente.';
        }else if($idm == 536){
            // 536 indica que no se pudo borrar por dependencias
            $msg_error = 'No se ha podido borrar la oferta porque tiene otras entidades relacionadas.';
        }

        // 3) Prepara objeto para acceder a ofertas y orden por defecto
        $db_ofertas = new Ofertas();
        $orderby = 'id_ofe DESC';

        // 4) Si la petición es POST se interpretan parámetros de búsqueda
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Recoger parámetros del formulario y limpiarlos
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            $data['titulo'] = $filter->filter($this->request->getPost('titulo'));
            $data['estado'] = (int)$this->request->getPost('estado');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['id_sec'] = (int)$this->request->getPost('id_sec');

            // Guardar filtros en la sesión para mantener estado al paginar
            $this->_container->ofer_buscador = $data;
            // Reiniciar la página al aplicar nuevos filtros
            $this->_container->ofer_page = 0;
        }else{
            // Si no es POST, se comprueba si se solicitó limpiar filtros (idm == 114)
            if($idm == 114){
                if(isset($this->_container->ofer_buscador)){
                    unset($this->_container->ofer_buscador);
                    $this->_container->ofer_page = 0;
                }
            }
        }

        // 5) Paginación: determinar la página actual
        $page = (int)$this->params()->fromRoute('v1',-1);
        if($page == -1){
            // Si no se pasa page por ruta, usar la página guardada en sesión o 1
            if(isset($this->_container->ofer_page)){
                $page= $this->_container->ofer_page;
            }else{
                $page = 1;
                $this->_container->ofer_page = $page;
            }
        }else{
            // Si se pasó explicitamente, guardarla en sesión
            $this->_container->ofer_page = $page;
        }
        if($page == 0){
            $page = 1;
        }
        // Offset para consultas LIMIT/OFFSET
        $offset = 50*($page - 1);

        // 6) Construcción de la condición WHERE según filtros
        if(isset($this->_container->ofer_buscador)){
            $where = Utilidades::generaCondicion('ofertas', $this->_container->ofer_buscador);
            // Guardar la condición para depuración/visualización en la sesión
            $this->_container->ofer_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->ofer_buscador;
        }else{
            // Valores por defecto cuando no hay filtros
            $buscador = array('id_ofe' => 0,'titulo' => null,'estado' => -1,'fechaDesde' => null,'fechaHasta' => null,'id_emp' => null,'id_sec' => null);
            $where = null;
        }

        // 7) Leer de base de datos las ofertas según condiciones y paginación
        $ofertas = $db_ofertas->getOfertas($where,$orderby,50,$offset);
        $num = $db_ofertas->numOfertas($where);
        if($num == 0){
            if(isset($this->_container->ofer_buscador)){
                $msg_error = 'No hay ninguna oferta con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna oferta guardada.';
            }
        }

        // 8) Preparar y devolver ViewModel con datos para la plantilla
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
        // ofertaAction: crear/editar una oferta y mostrar detalle con candidaturas.
        // 1) Título del layout
        $this->layout()->title = 'Nueva | Oferta';
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';
        $set = 1; // variable auxiliar (no usada explícitamente más adelante)

        // 2) Si llega un POST, es un guardado desde formulario
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Campos recibidos del formulario
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            // Si el usuario es administrador puede elegir empresa; si no, se asigna la de su perfil
            if($this->_usuario->get('rol') == '4dmin'){
                $data['id_emp'] =(int)$this->request->getPost('id_emp');
            }else{
                $data['id_emp'] =(int)$this->_usuario->get('id_emp');
            }

            // Resto de campos
            $data['titulo'] = $filter->filter($this->request->getPost('titulo'));
            $data['descripcion'] = $filter->filter($this->request->getPost('descripcion'));
            $data['info'] = $filter->filter($this->request->getPost('info'));
            $data['plazas'] = (int)$this->request->getPost('plazas');
            $data['categoria'] = $filter->filter($this->request->getPost('categoria'));
            $data['experiencia'] = (int)$this->request->getPost('experiencia');
            $data['estado'] = (int)$this->request->getPost('estado');
            $data['fecha'] = $filter->filter($this->request->getPost('fecha'));
            $data['id_usu'] = (int)$this->request->getPost('id_usu');

            // 3) Guardado mediante la entidad Oferta
            $oferta = new Oferta(0);
            $algun_valor_vacio = $oferta->set($data);
            if($algun_valor_vacio > 0){
                // Algún campo obligatorio quedó vacío
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_ofe'];
            }else{
                // Inserta o actualiza y devuelve id
                $id = $oferta->save();
                if($data['id_ofe'] == 0){
                    $msg_ok = 'La oferta ha sido creada correctamente.';
                }else{
                    $msg_ok = 'La oferta ha sido actualizada correctamente.';
                }
            }
        }else{
            // 4) Si no es POST, se está mostrando el formulario/ detalle
            $id = (int)$this->params()->fromRoute('v1',0);
            $oferta = new Oferta($id);
            // Mensajes opcionales por ruta (v3)
            $idm = (int)$this->params()->fromRoute('v3', 0);
            if($idm == 1){
                $msg_ok = 'La oferta ha sido publicada.';
                $oferta->setEstado(1);
            }else if($idm == 3){
                $msg_ok = 'La oferta ha sido rechazada.';
                $oferta->setEstado(3);
            }
        }

        // 5) Control de permisos: si el usuario no es admin no puede editar ofertas de otras empresas
        if($this->_usuario->get('rol') != '4dmin' && $oferta->get('id') > 0 &&
            ($oferta->get('id_emp') != $this->_usuario->get('id_emp') || $this->_usuario->get('autorizado') != 1)){
            // Redirige a la lista pública de ofertas de empleo si no tiene permisos
            return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertasempleo', 'v1' => $oferta->get('id') ));
        }

        // 6) Preparar información de la empresa para mostrar en la vista
        if($this->_usuario->get('rol') != '4dmin'){
            $empresa = $this->_usuario->get('empresa');
        }else{
            $empresa = null;
        }

        // 7) Si existe la oferta, cargar candidaturas relacionadas y paginarlas
        if($id > 0){
            $this->layout()->title = $oferta->get('titulo').' | Oferta';
            // Paginación de candidaturas (v2)
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

        // 8) Preparar la vista
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
        // ofertasempleoAction: listado público de ofertas para candidatos
        // Esta acción muestra las ofertas con estado=1 (publicadas) y permite
        // filtrar por categoría. Además obtiene las candidaturas del usuario
        // autenticado para marcar las ofertas en las que ya ha aplicado.

        // 1) Título del layout (se muestra en la plantilla)
        $this->layout()->title = 'Ofertas de empleo';

        // 2) Parámetros de ruta
        // v1: id de oferta seleccionada (por ejemplo para abrir un modal)
        $oferta_seleccionada = (int)$this->params()->fromRoute('v1', 0);
        // v2: código de mensaje pasado por otras acciones para mostrar notificaciones
        $idm = (int)$this->params()->fromRoute('v2', 0);

        $msg_ok = null;
        $msg_error = null;
        // 3) Interpretar códigos de mensaje (valores usados por otras acciones)
        if($idm == 100){
            // Error genérico al presentar candidatura
            $msg_error = 'No se ha podido presentar la candidatura. Inténtelo más tarde.';
        }else if($idm == 101){
            // Faltan datos obligatorios en el formulario de candidatura
            $msg_error = 'Debe de rellenar todos los datos del formulario para presentar la candidatura.';
        }else if($idm == 200){
            // Candidatura presentada correctamente
            $msg_ok = 'La candidatura se ha presentado correctamente.';
        }

        // 4) Preparar el acceso a ofertas y criterios por defecto
        $db_ofertas = new Ofertas();
        $orderby = 'fecha DESC';
        // Mostrar sólo ofertas publicadas
        $where = 'estado = 1';

        // 5) Si la petición es POST, el usuario ha enviado el formulario de búsqueda
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Recoger y limpiar parámetros de búsqueda (categoria es entero)
            $data['categoria'] = (int)$this->request->getPost('categoria');

            // Guardar filtros en sesión para mantener el estado al navegar/paginar
            $this->_container->ofer_empleo_buscador = $data;
        }else{
            // 6) Si no es POST, comprobar si la ruta indica limpiar filtros (idm==114)
            if($idm == 114){
                if(isset($this->_container->ofer_empleo_buscador)){
                    unset($this->_container->ofer_empleo_buscador);
                }
            }
        }

        // 7) Construcción de la condición WHERE según filtros almacenados en sesión
        if(isset($this->_container->ofer_empleo_buscador)){
            // Si hay una categoría seleccionada y es mayor que 0, añadirla al WHERE
            if($this->_container->ofer_empleo_buscador['categoria'] > 0){
                // operador ternario para añadir con AND si $where ya existe
                (isset($where))?($where .= ' AND categoria = ' . $this->_container->ofer_empleo_buscador['categoria']):($where = 'categoria = ' . $this->_container->ofer_empleo_buscador['categoria']);
            }
            // Guardar la condición en la sesión para depuración/visualización en la UI
            $this->_container->ofer_empleo_buscador['where']=$where .' ///////// '.$orderby;
            $buscador = $this->_container->ofer_empleo_buscador;
        }else{
            // Valores por defecto cuando no hay filtros
            $buscador = array('categoria' => 0);
        }

        // 8) Leer ofertas de la base de datos con la condición y orden definidos
        $ofertas = $db_ofertas->getOfertas($where,$orderby);
        $num = $db_ofertas->numOfertas($where);

        // 9) Obtener candidaturas del usuario autenticado para marcar ofertas ya candidatas
        $db_candidaturas = new Candidaturas();
        // Se solicita por id_usu (identificador del usuario que hizo login)
        $candidaturas = $db_candidaturas->getCandidaturas('id_usu = ' . $this->_usuario->get('id'));

        // 10) Construir un array simple con los ids de oferta en los que el usuario ya se postuló
        $candidaturasCursosUsuario = [];
        foreach($candidaturas as $candidatura):
            // Cada $candidatura es un array/objeto con clave 'id_ofe'
            $candidaturasCursosUsuario[] = $candidatura['id_ofe'];
        endforeach;

        // 11) Mensajes cuando no hay resultados
        if($num == 0){
            if(isset($this->_container->ofer_empleo_buscador)){
                $msg_error = 'No hay ninguna oferta con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna oferta de emplo guardada.';
            }
        }

        // 12) Preparar datos para la vista (array que la plantilla usará)
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
        // presentarcandidaturaAction: procesa la presentación de una candidatura
        // - Recibe un POST con un archivo (CV) y datos adicionales.
        // - Valida el fichero, lo guarda en disco y crea la entidad Candidatura.
        // - Al terminar redirige a la lista pública de ofertas con un código de resultado.

        // Código de resultado por defecto: 100 (error genérico)
        $ok = 100;

        // Comprobar que la petición es POST (el formulario fue enviado)
        if($this->request->isPost()) {
            // Crear una entidad vacía para manipular la candidatura
            $candidatura = new Candidatura(0);

            // Directorio destino para los CVs (constante definida en la entidad)
            $nombreDirectorio = $candidatura::FILE_DIRECTORY_CV;

            // Array donde construiremos los datos a guardar y filtro para limpiar input
            $data = [];
            $filter = new StripTags();

            // 1) Preparar adaptador HTTP para recibir archivos
            // Zend\File\Transfer\Adapter\Http maneja la subida en PHP
            $httpadapter = new \Zend\File\Transfer\Adapter\Http();

            // 2) Validadores: tamaño y extensión permitida
            // Size: máximo 10MB; Extension: pdf, doc, docx
            $filesize = new \Zend\Validator\File\Size(array('min' => '0kB', 'max' => '10MB'));
            $extension = new \Zend\Validator\File\Extension(array('extension' => array('pdf', 'doc', 'docx')));
            $httpadapter->setValidators(array($filesize, $extension));

            // 3) Obtener información del fichero enviado en el campo 'cv'
            $files = $httpadapter->getFileInfo('cv');

            // 4) Comprobar si el archivo cumple las validaciones
            if ($httpadapter->isValid()) {
                // Nombre original del fichero
                $fichero = $files['cv']['name'];
                // Extraer extensión (sin el punto)
                $ext = pathinfo($fichero, PATHINFO_EXTENSION);
                // Generar nombre único basado en timestamp + extensión
                $fichero = time() . "." . $ext;

                // Añadir filtro Rename para renombrar el fichero al recibirlo
                $httpadapter->addFilter('Rename', $fichero);
                // Establecer directorio destino (debe existir y tener permisos de escritura)
                $httpadapter->setDestination($nombreDirectorio);

                // 5) Recibir el fichero: mueve el temporal a la carpeta destino
                // El método receive devuelve true si ha podido mover el archivo
                if ($httpadapter->receive($files['cv']['name'])) {
                    // Preparar los datos para la entidad Candidatura
                    $data['id_can'] = 0; // nueva candidatura
                    // id del usuario autenticado (propiedad inicializada en el constructor)
                    $data['id_usu'] = $this->_usuario->get('id');
                    // id de la oferta a la que se postula (campo hidden en el form)
                    $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
                    // Comentario opcional del candidato, limpiado con StripTags
                    $data['comentario'] = $filter->filter($this->request->getPost('comentario'));
                    // Fecha de presentación (formato d-m-Y H:i:s usado en la app)
                    $data['fecha'] = date('d-m-Y H:i:s');
                    // Nombre del fichero guardado
                    $data['cv'] = $fichero;
                    // Estado por defecto (1 = presentado / activo)
                    $data['estado'] = 1;

                    // 6) Validar y asignar datos en la entidad (set devuelve >0 si faltan valores)
                    // El segundo parámetro '2' probablemente controla el modo de validación interno
                    $algunValorVacio = $candidatura->set($data, 2);
                    if($algunValorVacio == 0){
                        // 7) Guardar en BD y marcar código de éxito 200
                        $id_cand = $candidatura->save();
                        $ok = 200;
                    }
                }
            }else{
                // Validación del fichero falló (tamaño o extensión)
                $ok = 101;
            }
        }

        // 8) Redirigir a la lista de ofertas con el código resultado (v2)
        // Valores: 200 = OK, 101 = fallo de fichero, 100 = error genérico
        return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'ofertasempleo','v1' => 0,'v2' => $ok));
    }
	
    public function borrarofertaAction(){
        // borrarofertaAction: elimina una oferta dada su id y redirige a la lista
        // 1) Extraer el id de la oferta desde los parámetros de la ruta (v1)
        //    (fromRoute devuelve string; casteamos a int para seguridad)
        $id = (int)$this->params()->fromRoute('v1',0);

        // 2) Instanciar la entidad Oferta con el id. La clase Oferta
        //    encapsula la lógica de acceso/operaciones sobre la tabla 'ofertas'.
        $object = new Oferta($id);

        // 3) Llamar al método remove() de la entidad. Normalmente este método
        //    borrará el registro e informará con un código en $ok (por ejemplo 525 ok,
        //    536 fallo por dependencias). No asumimos el valor exacto, solo lo reencolamos.
        $ok = $object->remove();

        // 4) Redirigir a la acción 'ofertas' manteniendo la página actual guardada
        //    en sesión ($this->_container->ofer_page) y pasando el código $ok
        //    en el parámetro v2 para que la lista muestre el mensaje correspondiente.
        return $this->redirect()->toRoute(
            'backend/default',
            array(
                'controller' => 'empleo',
                'action' => 'ofertas',
                'v1' => (int)$this->_container->ofer_page,
                'v2' => $ok
            )
        );
    }
    
    public function xlsofertasAction(){
        // xlsofertasAction: exporta las ofertas resultantes de la búsqueda a un fichero Excel
        // 1) Construir la condición WHERE usando los filtros guardados en sesión
        $where = Utilidades::generaCondicion('ofertas', $this->_container->ofer_buscador);

        // 2) Obtener las ofertas que cumplen la condición, ordenadas por título
        $db = new Ofertas();
        $objects = $db->getOfertas($where,'titulo');

        // 3) Usar la utilidad Exportar::ofertas para generar un objeto PHPExcel
        //    que contiene la hoja de cálculo con los datos
        $objPHPExcel = Exportar::ofertas($objects);

        // 4) Enviar los headers HTTP apropiados para forzar la descarga como XLS
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        // 5) Crear el writer de PHPExcel y volcar el contenido al output
        //    'Excel5' genera un fichero .xls compatible con Excel antiguos
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');

        // 6) Terminar la ejecución. Se utiliza die/exit para asegurarse de que
        // el framework no intente renderizar una vista después de la descarga.
        die('Excel generado');
        exit;
    }
	
    public function candidaturasAction(){
        // candidaturasAction: lista y filtra candidaturas en el área de administración
        // Explicación general:
        // - Permite buscar candidaturas por varios campos (id, usuario, oferta, empresa y rango de fechas)
        // - Soporta paginación guardando la página en sesión
        // - Restringe el resultado para usuarios con rol 'c4legiado' (solo ver sus propias candidaturas)

        // 1) Título del layout
        $this->layout()->title = 'Candidaturas';

        // 2) Parámetros de ruta y mensajes
        // v2 (idm) sirve para recibir códigos desde otras acciones (p.ej. borrado)
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Interpretar códigos de mensaje de otras acciones
        if($idm == 525){
            $msg_ok = 'La candidatura ha sido borrada correctamente.';
        }else if($idm == 536){
            $msg_error = 'No se ha podido borrar la candidatura porque tiene otras entidades relacionadas.';
        }

        // 3) Preparar acceso a la capa de datos y orden por defecto
        $db_candidaturas = new Candidaturas();
        $orderby = 'usuariosNombre ASC';

        // 4) Manejo de filtros: si es POST, recogemos los parámetros del formulario
        if($this->request->isPost()){
            $data = [];
            $filter = new StripTags();
            // Recoger y castear/limpiar cada campo del formulario
            $data['id_can'] = (int)$this->request->getPost('id_can');
            $data['id_usu'] = (int)$this->request->getPost('id_usu');
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['candidaturasEstado'] = (int)$this->request->getPost('candidaturasEstado');

            // Guardar filtros en sesión para mantener el estado al paginar
            $this->_container->cand_buscador = $data;
            // Reiniciar la página al aplicar nuevos filtros
            $this->_container->cand_page = 0;
        }else{
            // Si no es POST: comprobar si la ruta solicita limpiar filtros (idm == 114)
            if($idm == 114){
                if(isset($this->_container->cand_buscador)){
                    unset($this->_container->cand_buscador);
                    $this->_container->cand_page = 0;
                }
            }
        }

        // 5) Paginación: determinar la página actual (v1) o usar la guardada en sesión
        $page = (int)$this->params()->fromRoute('v1',-1);
        if($page == -1){
            if(isset($this->_container->cand_page)){
                $page = $this->_container->cand_page;
            }else{
                $page = 1;
                $this->_container->cand_page = $page;
            }
        }else{
            // Guardar la página solicitada en sesión
            $this->_container->cand_page = $page;
        }
        if($page == 0){
            $page = 1;
        }
        // Calcular offset para consultas LIMIT/OFFSET (50 items por página)
        $offset = 50 * ($page - 1);

        // 6) Construcción de la condición WHERE según filtros en sesión
        if(isset($this->_container->cand_buscador)){
            // Utilidades::generaCondicion crea la cláusula SQL a partir de los valores
            $where = Utilidades::generaCondicion('candidaturas', $this->_container->cand_buscador);
            // Guardar la condición completa en sesión para depuración/visualización
            $this->_container->cand_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->cand_buscador;
        }else{
            // Valores por defecto del buscador cuando no hay filtros
            $buscador = array(
                'id_can' => 0,
                'id_usu' => null,
                'id_ofe' => null,
                'id_emp' => null,
                'fechaDesde' => null,
                'fechaHasta' => null,
                'candidaturasEstado' => -1
            );
            $where = null;
        }

        // 7) Restricción por rol: si el usuario es 'c4legiado' solo ver sus candidaturas
        if($this->_usuario->get('rol') == 'c4legiado'){
            // Si ya existe $where, concatenar con AND; si no, crear la condición
            (isset($where)) ? ($where .= ' AND id_usu = ' . $this->_usuario->get('id')) : ($where = 'id_usu = ' . $this->_usuario->get('id'));
        }

        // 8) Leer de la base de datos las candidaturas según where/order/paginación
        $candidaturas = $db_candidaturas->getCandidaturas($where, $orderby, 50, $offset);
        $num = $db_candidaturas->numCandidaturas($where);

        // 9) Mensajes si no hay resultados
        if($num == 0){
            if(isset($this->_container->cand_buscador)){
                $msg_error = 'No hay ninguna candidatura con las caracter&iacute;sticas seleccionadas.';
            }else{
                $msg_error = 'No hay ninguna candidatura guardada.';
            }
        }

        // 10) Preparar y devolver ViewModel con los datos que usa la plantilla
        $view = array(
            'usuario'   => $this->_usuario,
            'buscador'  => $buscador,
            'page'      => $page,
            'ok'        => $msg_ok,
            'ko'        => $msg_error,
            'num'       => $num,
            'candidaturas'  => $candidaturas
        );
        return new ViewModel($view);
    }
	
    public function candidaturaAction(){
        // candidaturaAction: crear / editar una candidatura y ver su detalle
        // 1) Título por defecto del layout
        $this->layout()->title = 'Nueva | Candidatura';

        // 2) Variables para mensajes y pestaña activa en la vista
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';

        // 3) Si llega un POST, procesamos el guardado desde el formulario
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();

            // Recoger campos del formulario, casteando/limpiando donde procede
            $data['id_can'] = (int)$this->request->getPost('id_can');
            $data['id_usu'] = (int)$this->request->getPost('id_usu');
            $data['id_ofe'] = (int)$this->request->getPost('id_ofe');
            $data['comentario'] = $filter->filter($this->request->getPost('comentario'));
            // La fecha puede venir como texto; se limpia con StripTags
            $data['fecha'] = $filter->filter($this->request->getPost('fecha'));
            $data['estado'] = (int)$this->request->getPost('estado');

            // 4) Instanciar entidad y asignar valores
            $candidatura = new Candidatura(0);
            // set() devuelve >0 si falta algún campo obligatorio
            $algun_valor_vacio = $candidatura->set($data);
            if ($algun_valor_vacio > 0) {
                // Algún campo obligatorio quedó vacío: preparar mensaje de error
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_can'];
            } else {
                // 5) Guardar en BD: inserta o actualiza y devuelve id
                $id = $candidatura->save();
                if ($data['id_can'] == 0) {
                    $msg_ok = 'La candidatura ha sido creada correctamente.';
                } else {
                    $msg_ok = 'La candidatura ha sido actualizada correctamente.';
                }
            }
        } else {
            // 6) Si no es POST, estamos mostrando el formulario / detalle
            $id = (int)$this->params()->fromRoute('v1', 0);
            $candidatura = new Candidatura($id);

            // 7) Mensajes/acciones en base a códigos pasados por ruta (v2)
            $idm = (int)$this->params()->fromRoute('v2', 0);
            if ($idm == 2) {
                // Preseleccionada
                $msg_ok = 'La candidatura ha sido preseleccionada.';
                $candidatura->setEstado(2);
            } else if ($idm == 3) {
                // Seleccionada
                $msg_ok = 'La candidatura ha sido seleccionada.';
                $candidatura->setEstado(3);
            } else if ($idm == 4) {
                // Descartada
                $msg_ok = 'La candidatura ha sido descartada.';
                $candidatura->setEstado(4);
            }
        }

        // 8) Obtener la oferta asociada (la entidad Candidatura expone 'oferta')
        $oferta = $candidatura->get('oferta');

        // 9) Control de permisos: los usuarios no administradores tienen restricciones
        if ($this->_usuario->get('rol') != '4dmin') {
            $allow = false; // flag de permiso
            // Permitir acceso si:
            // - La candidatura existe y la empresa de la oferta coincide con la del usuario
            //   y además el usuario está autorizado (autorizado == 1)
            // OR
            // - El usuario tiene rol 'c4legiado' y es el propio candidato (puede ver su candidatura)
            if ($candidatura->get('id') > 0 && ((
                $oferta->get('id_emp') == $this->_usuario->get('id_emp')
                && $this->_usuario->get('autorizado') == 1
            ) || (
                $this->_usuario->get('rol') == 'c4legiado' && $this->_usuario->get('id') == $candidatura->get('id_usu')
            ))) {
                $allow = true;
            }
            if (!$allow) {
                // No tiene permisos para ver/editar: redirigir al listado de candidaturas
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empleo', 'action' => 'candidaturas'));
            }
        }

        // 10) Si se está editando (id > 0) ajustar título con el identificador formateado
        if ($id > 0) {
            $this->layout()->title = 'Candidatura ' . str_pad($candidatura->get('id'), 5, '0', STR_PAD_LEFT);
        }

        // 11) Preparar datos para la vista y devolver ViewModel
        $view = array(
            'usuario' => $this->_usuario,
            'candidatura' => $candidatura,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'tab_candidatura' => $tab,
            // 'cliente' es el candidato (entidad cargada dentro de Candidatura)
            'cliente' => $candidatura->get('candidato'),
            'oferta' => $oferta
        );
        return new ViewModel($view);
    }
	
    public function borrarcandidaturaAction(){
        // borrarcandidaturaAction: elimina una candidatura y redirige al listado
        // 1) Obtener el id de la candidatura desde la ruta (v1). Casteamos a int
        //    para evitar inyecciones y asegurar que trabajamos con número.
        $id = (int)$this->params()->fromRoute('v1', 0);

        // 2) Instanciar la entidad Candidatura con el id proporcionado.
        //    La entidad encapsula la lógica de borrado en su método remove().
        $object = new Candidatura($id);

        // 3) Ejecutar la operación de borrado. Se espera que remove() devuelva
        //    un código que indica el resultado (por ejemplo 525 = OK, 536 = fallo
        //    por dependencias). No asumimos el significado exacto aquí; lo
        //    reencolamos en la redirección para que la acción de listado muestre
        //    el mensaje adecuado.
        $ok = $object->remove();

        // 4) Redirigir al listado de candidaturas. Se pasa en la ruta el número
        //    de página actual (guardado en sesión en `cand_page`) y el código
        //    $ok en `v2` para que el listado muestre un mensaje acorde al resultado.
        return $this->redirect()->toRoute(
            'backend/default',
            array(
                'controller' => 'empleo',
                'action'     => 'candidaturas',
                'v1'         => (int)$this->_container->cand_page,
                'v2'         => $ok
            )
        );
    }
    
    public function xlscandidaturasAction(){
        // xlscandidaturasAction: exporta las candidaturas filtradas a un fichero Excel
        // 1) Generar la condición WHERE a partir de los filtros guardados en sesión
        $where = Utilidades::generaCondicion('candidaturas', $this->_container->cand_buscador);

        // 2) Obtener las candidaturas que cumplen la condición
        $db = new Candidaturas();
        // Se piden campos ordenados por nombre y apellidos para el Excel
        $objects = $db->getCandidaturas($where, ['usuariosNombre','apellidos']);

        // 3) Usar la utilidad Exportar::candidaturas para construir el objeto PHPExcel
        $objPHPExcel = Exportar::candidaturas($objects);

        // 4) Enviar headers HTTP para forzar la descarga como fichero Excel (.xls)
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        // 5) Crear el writer de PHPExcel y escribir al output directo
        //    Se elige 'Excel5' para compatibilidad con .xls antiguos
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');

        // 6) Finalizar la ejecución para que el framework no intente renderizar
        //    una vista adicional después de la descarga. Se mantiene die/exit
        //    tal y como estaba en el código original.
        die('Excel generado');
        exit;
    }
    
    public function sectoresAction(){
        // sectoresAction: administra los sectores (buscar, editar, guardar múltiples y paginar)
        // 1) Ajustar título del layout
        $this->layout()->title = 'Sectores';

        // 2) Códigos de mensaje que pueden venir por la ruta (v2)
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        if ($idm == 525) {
            $msg_ok = 'El sector ha sido borrado correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar el sector porque tiene registros relacionados.';
        }

        // 3) Instancia de la capa de datos y orden por defecto
        $db = new Sectores();
        $orderby = 'nombre ASC';

        // 4) Manejo del POST: diferentes botones producen distintas acciones
        if ($this->request->isPost()) {
            $data = [];
            // 'boton' indica qué acción se ha pulsado en el formulario
            $boton = $this->request->getPost('boton');

            if ($boton == 'buscar') {
                // Guardar el filtro de búsqueda en sesión
                $data['id_sec'] = (int)$this->request->getPost('id_sec');
                $this->_container->sec_buscador = $data;

            } else if ($boton == 'guardar-todos') {
                // Guardar todos los sectores enviados en arrays paralelos
                $id_secs = $this->request->getPost('id_sec');
                $nombres = $this->request->getPost('nombre');
                foreach ($id_secs as $i => $id_sec):
                    if (!empty($nombres[$i])) {
                        $data = [
                            'id_sec' => $id_sec,
                            'nombre' => $nombres[$i]
                        ];
                        // Crear/actualizar cada sector
                        $object = new Sector(0);
                        $object->set($data);
                        $object->save();
                    }
                endforeach;
                $msg_ok = 'Los sectores han sido actualizados correctamente.';

            } else {
                // Caso: actualizar un único sector identificado por el índice
                $i = $boton;
                $id_secs = $this->request->getPost('id_sec');
                $nombres = $this->request->getPost('nombre');
                if (!empty($nombres[$i])) {
                    $data = [
                        'id_sec' => $id_secs[$i],
                        'nombre' => $nombres[$i]
                    ];
                    $object = new Sector(0);
                    $object->set($data);
                    $object->save();
                }
                $msg_ok = 'El sector ha sido actualizado correctamente.';
            }

        } else {
            // 5) No es POST: comprobamos si la ruta indica limpiar filtros (idm == 114)
            if ($idm == 114) {
                if (isset($this->_container->sec_buscador)) {
                    unset($this->_container->sec_buscador);
                    $this->_container->sec_page = 0;
                }
            }
        }

        // 6) Paginación: determinar la página actual (v1) o usar la guardada en sesión
        $page = (int)$this->params()->fromRoute('v1', -1);
        if ($page == -1) {
            if (isset($this->_container->sec_page)) {
                $page = $this->_container->sec_page;
            } else {
                $page = 1;
                $this->_container->sec_page = $page;
            }
        } else {
            $this->_container->sec_page = $page;
        }
        if ($page == 0) {
            $page = 1;
        }
        $offset = 50 * ($page - 1);

        // 7) Construcción de la condición WHERE según los parámetros de búsqueda en sesión
        if (isset($this->_container->sec_buscador)) {
            $where = Utilidades::generaCondicion('sectores', $this->_container->sec_buscador);
            $this->_container->sec_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->sec_buscador;
        } else {
            $buscador = array('id_sec' => 0, 'nombre' => null);
            $where = null;
        }

        // 8) Leer de la base de datos los sectores según condición, orden y paginación
        $objects = $db->get($where, $orderby, 50, $offset);
        $num = $db->num($where);
        if ($num == 0) {
            if (isset($this->_container->sec_buscador)) {
                $msg_error = 'No hay ningún sector con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ningún sector guardado.';
            }
        }

        // 9) Preparar datos para la vista y devolver ViewModel
        $view = array(
            'usuario' => $this->_usuario,
            'buscador' => $buscador,
            'page' => $page,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'num' => $num,
            'sectores' => $objects
        );
        return new ViewModel($view);
    }
    
    public function borrarsectorAction(){
        // borrarsectorAction: elimina un sector por id y redirige a la lista
        // 1) Leer el parámetro de la ruta 'v1' que contiene el id del sector
        //    fromRoute devuelve string por defecto; casteamos a int para seguridad
        $id = (int)$this->params()->fromRoute('v1', 0);

        // 2) Crear la entidad Sector con el id. La entidad encapsula
        //    operaciones sobre la tabla de sectores (incluyendo remove()).
        $object = new Sector($id);

        // 3) Invocar el borrado en la entidad. Se espera que remove() devuelva
        //    un código ($ok) indicando el resultado (p.ej. 525 = OK, 536 = fallo
        //    por tener registros relacionados). No interpretamos el código aquí;
        //    lo reenviamos a la vista/listado para mostrar el mensaje adecuado.
        $ok = $object->remove();

        // 4) Redirigir a la acción 'sectores' del controlador 'empleo'.
        //    Pasamos en la ruta la página actual almacenada en sesión
        //    (`sec_page`) para que la lista vuelva a la misma página, y
        //    el código `v2` con el resultado del borrado para mostrar mensajes.
        return $this->redirect()->toRoute(
            'backend/default',
            array(
                'controller' => 'empleo',
                'action'     => 'sectores',
                'v1'         => (int)$this->_container->sec_page,
                'v2'         => $ok
            )
        );
    }

    public function xaAction(){
        // xaAction: endpoint AJAX multiuso utilizado por autocompletados (p.ej. select2).
        // Entrada:
        // - v1 (ruta): código de operación (int). Define el tipo de búsqueda.
        // - GET q: término de búsqueda (texto o id según la operación).
        // Salida: JSON con array de objetos {id, text} listos para el widget frontend.

        // 1) Leer el código de operación desde la ruta y forzarlo a entero
        //    para evitar que valores no numéricos alteren la lógica.
        $ajax = (int)$this->params()->fromRoute('v1', 0);

        // 2) Array que contendrá los resultados; será codificado a JSON al final.
        $answer = [];

        // NOTA (seguridad): el código original construye cláusulas SQL con
        // concatenación de términos (`LIKE "%$term%"`). Esto es inseguro si
        // el acceso a BD no parametriza correctamente. A continuación
        // mantenemos el comportamiento original pero documentamos los puntos
        // donde se debería mejorar la sanitización/parametrización.

        // 3) Operación 1: búsqueda por texto en el título de ofertas
        if ($ajax == 1) {
            // `q` se espera como texto libre. En la implementación existente
            // se lee directamente de $_GET; aquí comprobamos su existencia.
            $term = isset($_GET['q']) ? $_GET['q'] : '';

            // Instancia del repositorio/colección de ofertas
            $db = new Ofertas();

            // Construir WHERE con LIKE; en un refactor ideal usar consultas
            // parametrizadas o escapar el término correctamente.
            $where = 'titulo LIKE "%' . $term . '%"';

            // Si el usuario no es administrador, restringir la búsqueda a su empresa
            if ($this->_usuario->get('rol') != '4dmin') {
                $where .= ' AND id_emp = ' . $this->_usuario->get('id_emp');
            }

            // Ejecutar la consulta y ordenar por título
            $objects = $db->get($where, 'titulo');

            // Construir la respuesta en formato {id, text}
            if (count($objects) > 0) {
                foreach ($objects as $object) :
                    $answer[] = ["id" => $object->get('id'), "text" => $object->get('titulo')];
                endforeach;
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }

        // 4) Operación 2: obtener una oferta por id (q es id)
        } else if ($ajax == 2) {
            // Forzar entero para seguridad y coherencia
            $term = (int)(isset($_GET['q']) ? $_GET['q'] : 0);
            $object = new Oferta($term);
            if ($object->get('id') > 0) {
                $answer[] = ["id" => $object->get('id'), "text" => $object->get('titulo')];
            } else {
                $answer[] = ["id" => "0", "text" => ""]; // sin texto si no existe
            }

        // 5) Operación 3: búsqueda de candidaturas por nombre/apellidos (solo admin)
        } else if ($ajax == 3 && $this->_usuario->get('rol') == '4dmin') {
            $term = isset($_GET['q']) ? $_GET['q'] : '';
            $db = new Candidaturas();

            // Construcción similar con LIKE; se devuelven registros coincidentes
            $objects = $db->getCandidaturas('nombre LIKE "%' . $term . '%" OR apellidos LIKE "%' . $term . '%"', ['nombre', 'apellidos']);
            if (count($objects) > 0) {
                foreach ($objects as $object) :
                    // Atención: el código original concatenaba nombre + nombre.
                    // Mantenemos ese comportamiento para no cambiar la salida,
                    // aunque lo correcto sería nombre + apellidos.
                    $answer[] = ["id" => $object['id_can'], "text" => $object['nombre'] . ' ' . $object['nombre']];
                endforeach;
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }

        // 6) Operación 4: obtener candidatura por id (solo admin)
        } else if ($ajax == 4 && $this->_usuario->get('rol') == '4dmin') {
            $term = (int)(isset($_GET['q']) ? $_GET['q'] : 0);
            $db = new Candidaturas();
            $objects = $db->getCandidaturas('id_can = ' . $term);
            if (count($objects) > 0) {
                $object = current($objects);
                $answer[] = ["id" => $object['id_can'], "text" => $object['nombre'] . ' ' . $object['nombre']];
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }

        // 7) Operación 5: búsqueda de sectores por nombre
        } else if ($ajax == 5) {
            $term = isset($_GET['q']) ? $_GET['q'] : '';
            $db = new Sectores();
            $objects = $db->get('nombre LIKE "%' . $term . '%"', 'nombre');
            if (count($objects) > 0) {
                foreach ($objects as $object) :
                    $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
                endforeach;
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }

        // 8) Operación 6: obtener sector por id
        } else if ($ajax == 6) {
            $term = (int)(isset($_GET['q']) ? $_GET['q'] : 0);
            $object = new Sector($term);
            if ($object->get('id') > 0) {
                $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
            } else {
                $answer[] = ["id" => "0", "text" => ""];
            }
        }

        // 9) Devolver JSON: setContent evita renderizar una vista; el frontend recibirá
        //    el array codificado. Mantener la coherencia con el comportamiento previo.
        return $this->getResponse()->setContent(Json::encode($answer));
    }
}