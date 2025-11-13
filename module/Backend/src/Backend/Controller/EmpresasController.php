<?php
namespace Backend\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Zend\Filter\StripTags;
use Zend\Json\Json;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Usuarios;
use Application\Model\Entity\Ofertas;
use Application\Model\Entity\Empresa;
use Application\Model\Entity\Empresas;
use Application\Model\Entity\Inscripciones;
use Application\Model\Utility\Exportar;
use Application\Model\Utility\Utilidades;

class EmpresasController extends AbstractActionController{
    /**
     * EmpresasController
     *
     * Resumen general:
     * Este controlador gestiona la administración de empresas en el módulo
     * Backend. Sus responsabilidades principales incluyen:
     * - Listar empresas y aplicar filtros/paginación (`indexAction`).
     * - Crear y editar la ficha de una empresa, gestionar sus entidades
     *   relacionadas (ofertas, inscripciones, usuarios) (`fichaAction`).
     * - Borrar empresas (`borrarAction`).
     * - Exportar empresas a Excel (`xlsAction`).
     * - Relacionar / desrelacionar usuarios con empresas (`usuariosAction`).
     * - Proveer endpoints AJAX para autocompletar (`xaAction`).
     * - Importar empresas desde una fuente externa (`importarAction`).
     *
     * Contexto y notas:
     * - El controlador asume que existe una identidad de usuario; en el
     *   constructor se inicializa `$_usuario` (entidad Usuario) y
     *   `$_container` (contendor de sesión) usados para persistir filtros
     *   y páginas.
     * - Muchas operaciones delegan la validación y persistencia en las
     *   entidades del modelo (por ejemplo `Empresa`, `Empresas`).
     * - Algunas acciones usan `StripTags` para limpiar entrada y forzan
     *   casts (int) para parámetros numéricos; aún así conviene revisar
     *   la sanitización y parametrización de consultas en la capa de datos.
     */

    protected $_usuario;
    protected $_container;
    protected $_tipo;

    public function __construct(){
        // Inicializar servicio de autenticación y leer identidad si existe
        $auth = new AuthenticationService();
        $identity = $auth->getIdentity();
        // Si hay sesión autenticada, cargar la entidad Usuario y contenedor de sesión
        if ($auth->hasIdentity()) {
            // $identity contiene información del usuario autenticado (por ejemplo id_usu)
            $usuario = new Usuario($identity->id_usu);
            // Guardamos la entidad Usuario en la propiedad $_usuario para uso en las actions
            $this->_usuario = $usuario;
            // Contenedor de sesión usado para persistir filtros/paginación
            $this->_container = new Container('namespace');
        }
    }
	
    public function indexAction(){
        // indexAction: lista y filtra empresas en el backend
        // 1) Ajustar título del layout mostrado en la plantilla
        $this->layout()->title = 'Empresas';

        // 2) Leer código de mensaje desde la ruta (v2) para mostrar notificaciones
        $idm = (int)$this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Interpretación de códigos recibidos de otras acciones
        if ($idm == 525) {
            // 525: borrado correcto
            $msg_ok = 'La empresa ha sido borrada correctamente.';
        } else if ($idm == 536) {
            // 536: fallo por dependencias
            $msg_error = 'No se ha podido borrar la empresa porque tiene otras entidades relacionadas.';
        }

        // 3) Preparar acceso a la capa de datos y orden por defecto
        $db_empresas = new Empresas();
        $orderby = 'empresasNombre ASC';

        // 4) Manejo de filtros desde el formulario (POST)
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Recoger parámetros de búsqueda del POST y limpiarlos donde procede
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['cif'] = $filter->filter($this->request->getPost('cif'));
            $data['id_sec'] = (int)$this->request->getPost('id_sec');
            $data['estado'] = (int)$this->request->getPost('estado');

            // Guardar filtros en sesión para mantener estado entre páginas
            $this->_container->empr_buscador = $data;
            // Reiniciar la página al aplicar nuevos filtros
            $this->_container->empr_page = 0;
        } else {
            // Si no es POST, comprobar si se solicita limpiar filtros (v2 == 114)
            if ($idm == 114) {
                if (isset($this->_container->empr_buscador)) {
                    unset($this->_container->empr_buscador);
                    $this->_container->empr_page = 0;
                }
            }
        }

        // 5) Paginación: determinar la página actual
        $page = (int)$this->params()->fromRoute('v1', -1);
        if ($page == -1) {
            // Si no se pasó page por ruta, usar la página guardada en sesión o inicializar
            if (isset($this->_container->empr_page)) {
                $page = $this->_container->empr_page;
            } else {
                $page = 1;
                $this->_container->empr_page = $page;
            }
        } else {
            // Si se pasó explícitamente, guardarla en sesión
            $this->_container->empr_page = $page;
        }
        if ($page == 0) {
            $page = 1;
        }
        // Offset para consultas LIMIT/OFFSET (50 items por página)
        $offset = 50 * ($page - 1);

        // 6) Construcción de la condición WHERE según filtros en sesión
        if (isset($this->_container->empr_buscador)) {
            // Utilidades::generaCondicion construye la cláusula SQL a partir del array de filtros
            $where = Utilidades::generaCondicion('empresas', $this->_container->empr_buscador);
            // Guardar la condición en sesión para depuración/visualización
            $this->_container->empr_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->empr_buscador;
        } else {
            // Valores por defecto cuando no hay filtros
            $buscador = array('id_emp' => 0, 'empresasNombre' => null, 'cif' => null, 'id_sec' => null, 'estado' => -1);
            $where = null;
        }

        // 7) Leer de la base de datos las empresas que cumplen la condición
        $empresas = $db_empresas->getEmpresas($where, $orderby, 50, $offset);
        $num = $db_empresas->num($where);
        if ($num == 0) {
            if (isset($this->_container->empr_buscador)) {
                $msg_error = 'No hay ninguna empresa con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ninguna empresa guardada.';
            }
        }

        // 8) Preparar y devolver ViewModel con los datos para la plantilla
        $view = array(
            'usuario' => $this->_usuario,
            'buscador' => $buscador,
            'page' => $page,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'num' => $num,
            'empresas' => $empresas
        );
        return new ViewModel($view);
    }
	
    public function fichaAction(){
        // fichaAction: crear/editar una empresa y mostrar su ficha con relaciones
        // 1) Ajustar título del layout y preparar variables de mensaje
        $this->layout()->title = 'Nueva | Empresa';
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';

        // 2) Si el formulario fue enviado (POST) procesar guardado
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();

            // Recoger y limpiar/castear campos del formulario
            $data['id_emp'] = (int)$this->request->getPost('id_emp');
            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['razonsocial'] = $filter->filter($this->request->getPost('razonsocial'));
            $data['cif'] = $filter->filter($this->request->getPost('cif'));

            // Solo administradores pueden cambiar el estado; si no, mantener el existente
            if ($this->_usuario->get('rol') == '4dmin') {
                $data['estado'] = (int)$this->request->getPost('estado');
            } else {
                // Cargar la empresa actual para extraer su estado y no permitir cambios
                $empresa = new Empresa($data['id_emp']);
                $data['estado'] = $empresa->get('estado');
            }

            // Resto de campos del formulario, limpiados con StripTags cuando son strings
            $data['id_sec'] = (int)$this->request->getPost('id_sec');
            $data['alta'] = $filter->filter($this->request->getPost('alta'));
            $data['web'] = $filter->filter($this->request->getPost('web'));
            $data['direccion'] = $filter->filter($this->request->getPost('direccion'));
            $data['cp'] = $filter->filter($this->request->getPost('cp'));
            $data['localidad'] = $filter->filter($this->request->getPost('localidad'));
            $data['provincia'] = $filter->filter($this->request->getPost('provincia'));
            $data['email'] = $filter->filter($this->request->getPost('email'));
            $data['telefono'] = $filter->filter($this->request->getPost('telefono'));

            // 3) Validar y guardar mediante la entidad Empresa
            $empresa = new Empresa(0);
            $algun_valor_vacio = $empresa->set($data);
            if ($algun_valor_vacio > 0) {
                // set() devolvió >0: faltan campos obligatorios
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_emp'];
            } else {
                // Guardar (inserta o actualiza) y obtener id
                $id = $empresa->save();
                if ($data['id_emp'] == 0) {
                    // Si era creación, notificar y si no es admin asignar empresa al usuario
                    $msg_ok = 'La empresa ha sido creada correctamente.';
                    if ($this->_usuario->get('rol') != '4dmin') {
                        $this->_usuario->setIdEmp($id);
                    }
                } else {
                    $msg_ok = 'La empresa ha sido actualizada correctamente.';
                }
            }
        } else {
            // 4) Si no es POST, preparar vista/editar existente: leer id de ruta
            $id = (int)$this->params()->fromRoute('v1', 0);
            $empresa = new Empresa($id);

            // 5) Interpretar códigos adicionales en v5 para mensajes y acciones rápidas
            $idm = (int)$this->params()->fromRoute('v5', 0);
            if ($idm == 1) {
                $msg_ok = 'La empresa ha sido activada.';
                $empresa->setEstado(1);
            } else if ($idm == 2) {
                $msg_ok = 'La empresa ha sido rechazada.';
                $empresa->setEstado(2);
            } else if ($idm == 111) {
                $msg_ok = 'Se ha relacionado correctamente al usuario con la empresa.';
            } else if ($idm == 112) {
                $msg_error = 'El NIF indicado no corresponde con ningún usuario.';
            } else if ($idm == 113) {
                $msg_ok = 'El usuario ya no está relacionado con la empresa.';
            } else if ($idm == 114) {
                $msg_error = 'No se ha podido eliminar la relación entre el usuario y la empresa.';
            }
        }

        // 6) Control de permisos: usuarios no administradores tienen restricciones
        if ($this->_usuario->get('rol') != '4dmin') {
            // Empresa asociada al usuario autenticado
            $empresaUser = $this->_usuario->get('empresa');
            $allow = false;
            // Permitir si el usuario está autorizado y tiene empresa, o si no tiene empresa asignada
            if (($empresaUser->get('id') > 0 && $this->_usuario->get('autorizado') == 1) || $this->_usuario->get('id_emp') == 0) {
                $allow = true;
            }
            // Si tiene permiso pero intenta acceder a otra empresa, redirigir a su propia ficha
            if ($allow && $empresa->get('id') != $empresaUser->get('id')) {
                return $this->redirect()->toRoute('backend/default', array('controller' => 'empresas', 'action' => 'ficha', 'v1' => $empresaUser->get('id')));
            } else if (!$allow) {
                // No tiene permiso: enviar al dashboard
                return $this->redirect()->toRoute('backend/default', array('controller' => 'index', 'action' => 'index'));
            }
        }

        // 7) Si es edición (id > 0) cargar relaciones para mostrarlas en la vista
        if ($id > 0) {
            // Ajustar título con nombre de la empresa
            $this->layout()->title = $empresa->get('nombre') . ' | Empresa';

            // Paginación y carga de ofertas relacionadas
            $pago = (int)$this->params()->fromRoute('v2', -1);
            if ($pago == -1) {
                if (isset($this->_container->uins_page)) {
                    $pago = $this->_container->uins_page;
                } else {
                    $pago = 0;
                    $this->_container->uins_page = $pago;
                }
            } else {
                $this->_container->uins_page = $pago;
                $tab = 'ofertas';
            }
            if ($pago == 0) {
                $pago = 1;
            }
            $offseto = 50 * ($pago - 1);
            $db_ofe = new Ofertas();
            // Obtener ofertas de la empresa paginadas
            $ofertas = $db_ofe->getOfertas('id_emp = ' . $id, 'id_emp DESC', 50, $offseto);
            $numo = $db_ofe->num('id_emp = ' . $id);

            // Paginación y carga de inscripciones relacionadas
            $pagi = (int)$this->params()->fromRoute('v3', -1);
            if ($pagi == -1) {
                if (isset($this->_container->uins_page)) {
                    $pagi = $this->_container->uins_page;
                } else {
                    $pagi = 0;
                    $this->_container->uins_page = $pagi;
                }
            } else {
                $this->_container->uins_page = $pagi;
                $tab = 'inscripciones';
            }
            if ($pagi == 0) {
                $pagi = 1;
            }
            $offseti = 50 * ($pagi - 1);
            $db_ins = new Inscripciones();
            $inscripciones = $db_ins->getInscripciones('id_emp = ' . $id, 'id_emp DESC', 50, $offseti);
            $numi = $db_ins->num('id_emp = ' . $id);

            // Paginación y carga de usuarios relacionados
            $pagu = (int)$this->params()->fromRoute('v4', -1);
            if ($pagu == -1) {
                if (isset($this->_container->uins_page)) {
                    $pagu = $this->_container->uins_page;
                } else {
                    $pagu = 0;
                    $this->_container->uins_page = $pagu;
                }
            } else {
                $this->_container->uins_page = $pagu;
                $tab = 'usuarios';
            }
            if ($pagu == 0) {
                $pagu = 1;
            }
            $offsetu = 50 * ($pagu - 1);
            $db_usu = new Usuarios();
            $usuarios = $db_usu->get('id_emp = ' . $id, 'id_emp DESC', 50, $offsetu);
            $numu = $db_usu->num('id_emp = ' . $id);
        } else {
            // Valores por defecto si la empresa es nueva
            $ofertas = [];
            $numo = 0;
            $pago = 0;
            $inscripciones = [];
            $numi = 0;
            $pagi = 0;
            $usuarios = [];
            $numu = 0;
            $pagu = 0;
        }

        // 8) Preparar ViewModel con todos los datos que la vista requiere
        $view = array(
            'usuario' => $this->_usuario,
            'empresa' => $empresa,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'ofertas' => $ofertas,
            'numo' => $numo,
            'pago' => $pago,
            'inscripciones' => $inscripciones,
            'numi' => $numi,
            'pagi' => $pagi,
            'usuarios' => $usuarios,
            'numu' => $numu,
            'pagu' => $pagu,
            'tab_empresa' => $tab,
            'autorizado' => $empresa->get('autorizado')
        );
        return new ViewModel($view);
    }
	
    public function borrarAction(){
        // borrarAction: eliminar una empresa
        // 1) Leer id desde la ruta y castear a entero para seguridad básica
        $id = (int)$this->params()->fromRoute('v1', 0);

        // 2) Cargar la entidad Empresa y delegar el borrado a la entidad
        //    La implementación de Empresa::remove() puede devolver códigos que
        //    indican éxito o fallo por dependencias; mantener ese contrato.
        $object = new Empresa($id);
        $ok = $object->remove();

        // 3) Redirigir al listado preservando la página actual (desde contenedor
        //    de sesión) y transmitiendo el código $ok en v2 para mostrar mensajes
        //    en indexAction. No renderizamos una vista propia aquí.
        return $this->redirect()->toRoute('backend/default', array(
            'controller' => 'empresas',
            'action' => 'index',
            'v1' => (int)$this->_container->empr_page,
            'v2' => $ok
        ));
    }
    
    public function xlsAction(){
        // xlsAction: exportar las empresas filtradas a Excel
        // 1) Reconstruir la condición WHERE desde el buscador guardado en sesión
        $where = Utilidades::generaCondicion('empresas', $this->_container->empr_buscador);

        // 2) Obtener la colección de empresas que cumplirían esos filtros
        $db = new Empresas();
        $objects = $db->getEmpresas($where, 'empresasNombre');

        // 3) Delegar la creación del objeto PHPExcel a la utilidad Exportar
        //    Exportar::empresas() devuelve un objeto PHPExcel ya poblado
        $objPHPExcel = Exportar::empresas($objects);

        // 4) Preparar headers HTTP para forzar la descarga como .xls
        // Nota: aquí se usan headers y se escribe directamente a php://output.
        // En una refactorización sería mejor devolver un Response con Stream para
        // facilitar testing y evitar el uso de die/exit.
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        // 5) Crear el escritor (formato Excel5) y volcar al output
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');

        // 6) Finalizar petición. El die() aquí corta la ejecución; es intencional
        //    en este patrón, pero reduce testabilidad. Mantengo el comportamiento.
        die('Excel generado');
        exit;
    }
    
    public function usuariosAction(){
        // usuariosAction: relacionar/desrelacionar usuarios con una empresa
        // Esta acción tiene dos modos:
        //  - POST: buscar usuario por NIF y relacionarlo con la empresa indicada
        //  - GET/ruta: eliminar la relación de un usuario (id_usu) con la empresa
        if ($this->request->isPost()) {
            $filter = new StripTags();
            // NIF introducido por el administrador (limpiado con StripTags)
            $nif = $filter->filter($this->request->getPost('nif'));
            $id_emp = (int)$this->request->getPost('id_emp');

            // Buscar usuario por NIF mediante la capa de datos
            $db_usu = new Usuarios();
            $usuario = $db_usu->getByNif($nif);

            // Si existe, asociar la empresa y redirigir con código 111 (éxito)
            if ((int)$usuario->get('id')) {
                $usuario->setEmpresa($id_emp);
                return $this->redirect()->toRoute('backend/default', array(
                    'controller' => 'empresas',
                    'action' => 'ficha',
                    'v1' => (int)$id_emp,
                    'v2' => 0,
                    'v3' => 0,
                    'v4' => 0,
                    'v5' => 111
                ));
            } else {
                // Usuario no encontrado por NIF -> redirect con código 112
                return $this->redirect()->toRoute('backend/default', array(
                    'controller' => 'empresas',
                    'action' => 'ficha',
                    'v1' => (int)$id_emp,
                    'v2' => 0,
                    'v3' => 0,
                    'v4' => 0,
                    'v5' => 112
                ));
            }
        } else {
            // Eliminar relación: se esperan v1 = id_usu y v2 = id_emp en la ruta
            $id_usu = (int)$this->params()->fromRoute('v1', 0);
            $id_emp = (int)$this->params()->fromRoute('v2', 0);
            $usuario = new Usuario($id_usu);

            // Si el usuario existe, eliminar la relación y redirigir con código 113
            if ((int)$usuario->get('id')) {
                $usuario->eliminaRelacionEmpresa();
                return $this->redirect()->toRoute('backend/default', array(
                    'controller' => 'empresas',
                    'action' => 'ficha',
                    'v1' => (int)$id_emp,
                    'v2' => 0,
                    'v3' => 0,
                    'v4' => 0,
                    'v5' => 113
                ));
            } else {
                // Usuario no existe -> redirigir con código 114 indicando fallo
                return $this->redirect()->toRoute('backend/default', array(
                    'controller' => 'empresas',
                    'action' => 'ficha',
                    'v1' => (int)$id_emp,
                    'v2' => 0,
                    'v3' => 0,
                    'v4' => 0,
                    'v5' => 114
                ));
            }

        }
    }

    public function xaAction(){
        // xaAction: endpoints ligeros para peticiones AJAX/autocomplete
        // Recibe un código en v1 que indica el tipo de consulta y devuelve JSON
        $ajax = (int)$this->params()->fromRoute('v1', 0);
        $answer = [];

        // Nota de seguridad: el código original lee directamente de $_GET['q'] y
        // concatena en cláusulas LIKE; eso funciona pero es frágil. Recomendable
        // usar $this->params()->fromQuery('q') y consultas parametrizadas en la
        // capa de datos. Mantengo la lógica pero lo comento para que se vea el
        // lugar donde mejorar.
        if ($ajax == 1) {
            // 1) Autocompletar por texto: buscar empresas cuyo nombre contenga q
            $term = $_GET['q']; // sugerir: $this->params()->fromQuery('q')
            $db = new Empresas();
            // Atención: aquí se construye un LIKE concatenando term
            $objects = $db->getEmpresas('empresasNombre LIKE "%' . $term . '%"', 'empresasNombre');
            if (count($objects) > 0) {
                // Formato esperado por select2 / componentes similares: id/text
                foreach ($objects as $object) :
                    $answer[] = ["id" => $object['id_emp'], "text" => $object['empresasNombre']];
                endforeach;
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 2) {
            // 2) Obtener detalle por id (q contiene id numérico)
            $term = (int)$_GET['q']; // sugerir: (int)$this->params()->fromQuery('q')
            $object = new Empresa($term);
            if ($object->get('id') > 0) {
                $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
            } else {
                $answer[] = ["id" => "0", "text" => ""];
            }
        }

        // Devolver JSON codificado
        return $this->getResponse()->setContent(Json::encode($answer));
    }
    
    public function importarAction(){
        // importarAction: puente para lanzar la importación masiva de empresas
        // 1) Delegar todo el proceso en la capa de modelo/servicio; se asume que
        //    Empresas::importarEmpresas() procesa ficheros, valida filas y crea
        //    registros. Aquí sólo llamamos y terminamos la petición.
        $db = new Empresas();
        $db->importarEmpresas();

        // 2) Finalizar ejecución. Se usa die() para interrumpir el flujo y no
        //    renderizar ninguna vista; esto es intencional pero puede ocultar
        //    errores. Alternativamente, devolver un Response con información
        //    del resultado sería más robusto.
        die();
    }
}