<?php

namespace Backend\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Authentication\AuthenticationService;
use Zend\Filter\StripTags;
use Zend\Json\Json;
use Application\Model\Entity\Usuario;
use Application\Model\Entity\Categoria;
use Application\Model\Entity\Categorias;
use Application\Model\Entity\Curso;
use Application\Model\Entity\Cursos;
use Application\Model\Entity\Inscripcion;
use Application\Model\Entity\Inscripciones;
use Application\Model\Entity\Inscrito;
use Application\Model\Entity\Participante;
use Application\Model\Entity\Menores;
use Application\Model\Entity\Usuarios;
use Application\Model\Entity\Menor;
use Application\Model\Utility\Exportar;
use Application\Model\Utility\Utilidades;
use Application\Model\Utility\Notificaciones;
use Application\Model\Utility\Imprimir;
use Application\Service\ApiClientRest;
use Application\Service\SendMail;

/**
 * FormacionController
 *
 * Resumen general:
 * Este controlador gestiona todo lo relativo a la formación dentro del
 * módulo Backend: cursos, inscripciones, inscritos, participantes,
 * categorías y utilidades relacionadas (exportar, sincronizar con WP,
 * generación de certificados, notificaciones, etc.).
 *
 * Principales responsabilidades:
 * - Mostrar listados y filtros (cursos, inscripciones, inscritos, participantes, categorías).
 * - Crear/editar cursos e inscripciones y gestionar sus relaciones.
 * - Operaciones auxiliares: exportar Excel, sincronizar con WordPress,
 *   enviar correos, subir justificantes y certificados.
 *
 * Contexto y notas:
 * - En el constructor se inicializa la identidad del usuario (`$_usuario`) y
 *   un contenedor de sesión (`$_container`) para persistir filtros/paginación.
 * - Muchas acciones usan `StripTags` para limpiar entrada y `Utilidades::generaCondicion`
 *   para construir condiciones SQL desde arrays de filtros. Revisar parametrización.
 * - Varias acciones finalizan con `die()` tras volcar a la salida (exports); esto
 *   reduce la testabilidad y se recomienda devolver un Response/Stream en su lugar.
 */
class FormacionController extends AbstractActionController {

    protected $_usuario;
    protected $_container;
    protected $_tipo;

    /*
     * @var Application\Service\ApiClientRest
     * */
    protected $apiClientRest;
    protected $sendMail;

    public function __construct(ApiClientRest $api_client_rest, SendMail $send_mail) {
        $auth = new AuthenticationService();
        $identity = $auth->getIdentity();
        if ($auth->hasIdentity()) {
            $usuario = new Usuario($identity->id_usu);
            $this->_usuario = $usuario;
            $this->_container = new Container('namespace');
        }
        $this->apiClientRest = $api_client_rest;
        $this->sendMail = $send_mail;
    }

    public function indexAction() {
        // indexAction: punto de entrada que redirige al listado de cursos
        // Redirigimos al action 'cursos' (v1 = 1 indica página 1)
        // Esto evita duplicar lógica en una vista vacía para index
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'cursos', 'v1' => 1));
    }

    public function cursosAction() {
        // cursosAction: listado paginado de cursos con filtros
        // 1) Título de layout y mensajes por códigos (v2)
        $this->layout()->title = 'Cursos';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Interpreta códigos de mensaje usados por otras acciones
        if ($idm == 525) {
            $msg_ok = 'El curso ha sido borrado correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar el curso porque tiene otros registros relacionadas.';
        }

        // 2) Preparar capa de datos y orden por defecto
        $db_cursos = new Cursos();
        $orderby = 'comienzo DESC';

        // 3) Manejo de filtros desde el formulario
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Recoger y limpiar cada parámetro recibido por POST
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['tipo'] = (int) $this->request->getPost('tipo');
            $data['estado'] = (int) $this->request->getPost('estado');
            $data['comienzoDesde'] = $filter->filter($this->request->getPost('comienzoDesde'));
            $data['comienzoHasta'] = $filter->filter($this->request->getPost('comienzoHasta'));
            $data['finDesde'] = $filter->filter($this->request->getPost('finDesde'));
            $data['finHasta'] = $filter->filter($this->request->getPost('finHasta'));
            $data['id_cat'] = (int) $this->request->getPost('id_cat');

            // Guardar los filtros en el contenedor de sesión y reiniciar paginación
            $this->_container->curs_buscador = $data;
            $this->_container->curs_page = 0;
        } else {
            // Si no es POST: comprobar si deben limpiarse los filtros (v2 == 114)
            if ($idm == 114) {
                if (isset($this->_container->curs_buscador)) {
                    unset($this->_container->curs_buscador);
                    $this->_container->curs_page = 0;
                }
            }
        }

        // 4) Paginación: leer la página desde la ruta o desde sesión
        $page = (int) $this->params()->fromRoute('v1', -1);
        if ($page == -1) {
            if (isset($this->_container->curs_page)) {
                $page = $this->_container->curs_page;
            } else {
                $page = 1;
                $this->_container->curs_page = $page;
            }
        } else {
            $this->_container->curs_page = $page;
        }
        if ($page == 0) {
            $page = 1;
        }
        $offset = 50 * ($page - 1); // 50 items por página

        // 5) Construcción del WHERE a partir de los filtros guardados en sesión
        if (isset($this->_container->curs_buscador)) {
            // Genera una cláusula SQL (string) desde el array de filtros
            $where = Utilidades::generaCondicion('cursos', $this->_container->curs_buscador);
            // Guardar la cláusula en sesión para debug/visualización
            $this->_container->curs_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->curs_buscador;
        } else {
            // valores por defecto para el buscador
            $buscador = array('id_cur' => 0, 'nombre' => null, 'tipo' => -1, 'estado' => -1, 'comienzoDesde' => null, 'comienzoHasta' => null, 'finDesde' => null, 'finHasta' => null, 'id_cat' => null);
            $where = null;
        }

        // 6) Lectura desde la capa de datos y conteo
        $cursos = $db_cursos->get($where, $orderby, 50, $offset);
        $num = $db_cursos->num($where);
        if ($num == 0) {
            if (isset($this->_container->curs_buscador)) {
                $msg_error = 'No hay ning&uacute;n curso con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ning&uacute;n curso guardado.';
            }
        }

        // 7) Preparar ViewModel con los datos para la plantilla
        $view = array(
            'usuario' => $this->_usuario,
            'buscador' => $buscador,
            'page' => $page,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'num' => $num,
            'cursos' => $cursos
        );
        return new ViewModel($view);
    }

    public function cursoAction() {
        // cursoAction: crear/editar un curso y mostrar su ficha con relaciones
        // 1) Preparar título y mensajes
        $this->layout()->title = 'Nuevo | Curso';
        $idm = (int) $this->params()->fromRoute('v3', 0); // códigos de acciones subsidiarias
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';

        // 2) Si el formulario fue enviado (POST) procesar la creación/edición
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Recoger campos del formulario y limpiarlos/castearlos cuando toca
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['publicacion'] = $filter->filter($this->request->getPost('publicacion'));
            $data['comienzo'] = $filter->filter($this->request->getPost('comienzo'));
            $data['fin'] = $filter->filter($this->request->getPost('fin'));
            $data['horario'] = $filter->filter($this->request->getPost('horario'));
            $data['ubicacion'] = $filter->filter($this->request->getPost('ubicacion'));
            $data['enlubi'] = $filter->filter($this->request->getPost('enlubi'));
            $data['id_cat'] = (int) $this->request->getPost('id_cat');
            $data['estado'] = (int) $this->request->getPost('estado');
            // Campos de texto largos (html) no pasados por StripTags intencionadamente
            $data['descripcion'] = $this->request->getPost('descripcion');
            $data['objetivos'] = $this->request->getPost('objetivos');
            $data['dirigido'] = $this->request->getPost('dirigido');
            $data['dinamica'] = $this->request->getPost('dinamica');
            $data['metodologia'] = $this->request->getPost('metodologia');
            $data['programa'] = $this->request->getPost('programa');
            $data['profesorado'] = $this->request->getPost('profesorado');
            $data['precios'] = $this->request->getPost('precios');
            $data['informacion'] = $this->request->getPost('informacion');
            $data['precio_col'] = (int) $this->request->getPost('precio_col');
            $data['precio_otr'] = (int) $this->request->getPost('precio_otr');
            $data['tipo'] = (int) $this->request->getPost('tipo');
            $data['colegiados'] = (int) $this->request->getPost('colegiados');
            $data['sincro'] = $filter->filter($this->request->getPost('sincro'));
            $data['post_id'] = (int) $this->request->getPost('post_id');
            $data['beca'] = (int) $this->request->getPost('beca');
            $data['informacion_certificados'] = $filter->filter($this->request->getPost('informacion_certificados'));
            $data['resumen'] = $filter->filter($this->request->getPost('resumen'));
            $data['no_sincronizar_web'] = (int) $this->request->getPost('no_sincronizar_web');

            // 3) Validación y persistencia delegada en entidad Curso
            $curso = new Curso(0);
            $algun_valor_vacio = $curso->set($data); // set() valida campos obligatorios
            if ($algun_valor_vacio > 0) {
                // Faltan campos obligatorios
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_cur'];
            } else {
                // Guardar e informar si fue creación o actualización
                $id = $curso->save();
                if ($data['id_cur'] == 0) {
                    $msg_ok = 'El curso ha sido creado correctamente.';
                } else {
                    $msg_ok = 'El curso ha sido actualizado correctamente.';
                }
            }
        } else {
            // 4) Si no es POST, cargar el curso indicado por la ruta (v1)
            $id = (int) $this->params()->fromRoute('v1', 0);
            $curso = new Curso($id);
        }

        // 5) Si existe el curso, cargar relaciones (inscripciones) y pestañas
        if ($id > 0) {
            // Título con el nombre del curso
            $this->layout()->title = $curso->get('nombre') . ' | Curso';
            // Paginación de inscripciones: v2 controla la página
            $pagi = (int) $this->params()->fromRoute('v2', 0);
            if ($pagi <= 0) {
                $pagi = 1;
            } else {
                $tab = 'inscripciones';
            }
            $offseti = 50 * ($pagi - 1);
            $db_ins = new Inscripciones();
            // Obtener inscritos con orden por nombre/apellidos
            $inscripciones = $db_ins->getInscritos('id_cur = ' . $id, ['usuariosNombre ASC', 'usuariosApellidos ASC'], 50, $offseti);
            $numi = $db_ins->num('id_cur = ' . $id);
            // Número de inscripciones incompletas (estado == 0)
            $inscripcionesIncompletas = $db_ins->num('id_cur = ' . $id . ' AND estado = 0');
        } else {
            // Valores por defecto para un curso nuevo
            $inscripciones = [];
            $numi = 0;
            $pagi = 0;
        }

        // 6) Interpretación de códigos v3 para notificaciones relacionadas (sincronización, uploads, etc.)
        if ($idm == 100) {
            $msg_ok = 'El curso ha sido sincronizado correctamente con wordpress.';
        } else if ($idm == 101) {
            $msg_error = 'El curso no se ha podido sincronizar por un error en el servidor de destino.';
            // Si la respuesta de sincronización está en sesión, anexarla al mensaje
            if (isset($this->_container->respuestaSincronizacionCurso) && is_array($this->_container->respuestaSincronizacionCurso)) {
                foreach ($this->_container->respuestaSincronizacionCurso as $ke1 => $va1) :
                    if (is_array($va1)) {
                        foreach ($va1 as $ke2 => $va2) :
                            $msg_error .= '<br/>- <strong>' . $ke2 . '</strong>: ' . $va2;
                        endforeach;
                    } else {
                        $msg_error .= '<br/>- <strong>' . $ke1 . '</strong>: ' . $va1;
                    }
                endforeach;
            }
        } else if ($idm == 500) {
            $msg_error = 'Debe seleccionar al menos a un inscrito.';
            $tab = 'inscripciones';
        } else if ($idm == 501) {
            $msg_ok = 'Los inscritos han sido actualizados correctamente.';
            $tab = 'inscripciones';
        } else if ($idm == 548) {
            $msg_ok = 'El certificado ha sido subido correctamente.';
            $tab = 'inscripciones';
        } else if ($idm == 549) {
            $msg_error = 'El certificado no tiene une extensión válida.';
            $tab = 'inscripciones';
        } else if ($idm == 550) {
            $msg_error = 'Ha ocurrido un error al subir el certificado.';
            $tab = 'inscripciones';
        } else if ($idm == 551) {
            $msg_ok = 'El certificado ha sido borrado correctamente.';
            $tab = 'inscripciones';
        } else if ($idm == 552) {
            $msg_ok = 'Se han generado/enviado el/los certificados.';
            $tab = 'inscripciones';
        } else if ($idm == 602) {
            $msg_ok = 'Se han enviado las notificaciones de inscripciones incompletas.';
            $tab = 'inscripciones';
        }

        // 7) Preparar ViewModel con todos los datos
        $view = array(
            'usuario' => $this->_usuario,
            'curso' => $curso,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'inscripciones' => $inscripciones,
            'numi' => $numi,
            'pagi' => $pagi,
            'tab_curso' => $tab,
            'inscripcionesIncompletas' => $inscripcionesIncompletas
        );
        return new ViewModel($view);
    }
    
    public function inscripcionesincompletasAction(){
        // inscripcionesincompletasAction: enviar notificaciones para inscripciones incompletas
        // 1) Obtener id del curso desde la ruta
        $id = (int) $this->params()->fromRoute('v1', 0);
        // 2) Recuperar inscripciones cuyo estado == 0 (incompletas)
        $db_ins = new Inscripciones();
        $inscripciones = $db_ins->get('id_cur = ' . $id . ' AND estado = 0');
        // 3) Enviar notificación individualmente delegando en Notificaciones::enviarInscripcionIncompleta
        foreach ($inscripciones as $inscripcion) :
            Notificaciones::enviarInscripcionIncompleta($inscripcion, $this->sendMail);
        endforeach;
        // 4) Redirigir de vuelta a la ficha del curso con código 602 (notificaciones enviadas)
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => (int) $id, 'v2' => 0, 'v3' => 602));
    }

    public function generacerfificadosAction(){
        // generacerfificadosAction: generar y/o enviar certificados por lote
        // Solo procesa POST
        if ($this->request->isPost()) {
            // id del curso para redirección final
            $id_cur = (int) $this->request->getPost('id_cur');

            // Listas con ids de UI (id inscritos individuales) a generar y a enviar
            $id_ui_generar = $this->request->getPost('id_ui_generar');
            if (empty($id_ui_generar)) {
                $id_ui_generar = [];
            }
            $id_ui_enviar = $this->request->getPost('id_ui_enviar');
            if (empty($id_ui_enviar)) {
                $id_ui_enviar = [];
            }

            // Generar certificados (PDF) para los ids marcados en id_ui_generar
            foreach ($id_ui_generar as $ui => $value) :
                $inscrito = new Inscrito($ui);
                // Imprimir::certificadoPdf devuelve array donde [1] es el nombre del fichero
                $pdf = Imprimir::certificadoPdf($inscrito, true);
                $inscrito->setDiploma($pdf[1]);
            endforeach;

            // Agrupar inscritos por id_ins (inscripción padre) para envío por inscripción
            $group_inscritos = [];
            foreach ($id_ui_enviar as $ui => $value) :
                $inscrito = new Inscrito($ui);
                if (!isset($group_inscritos[$inscrito->get('id_ins')])) {
                    $group_inscritos[$inscrito->get('id_ins')] = [];
                }
                $group_inscritos[$inscrito->get('id_ins')][] = $inscrito;
                // Si no existe el diploma en disco, generarlo
                if (empty($inscrito->get('diploma')) || !file_exists(\Application\Model\Entity\Inscrito::FILE_DIRECTORY_DIPLOMA . $inscrito->get('diploma'))) {
                    $pdf = Imprimir::certificadoPdf($inscrito, true);
                    $inscrito->setDiploma($pdf[1]);
                }
            endforeach;

            // Enviar certificados por cada inscripción agrupada
            foreach ($group_inscritos as $id_ins => $ids_ui) :
                $dev_mail = Notificaciones::enviarCertificaciones(new Inscripcion($id_ins), $this->sendMail, $ids_ui);
                $fecha_envio = date('d-m-Y H:i:s');
                foreach ($ids_ui as $inscrito) :
                    if ($dev_mail[$inscrito->get('id')]['status']) {
                        // Registrar fecha de envío en cada Inscrito
                        $inscrito->setAttribute('envio_diploma', $fecha_envio);
                        $inscrito->save();
                    }
                endforeach;
            endforeach;
            // Redirigir a la ficha del curso con código 552 (certificados generados/enviados)
            return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => (int) $id_cur, 'v2' => 0, 'v3' => 552));
        }
    }

    public function borrarcursoAction() {
        // borrarcursoAction: borrar un curso y sincronizar borrado con WordPress si procede
        $id = (int) $this->params()->fromRoute('v1', 0);
        $object = new Curso($id);
        // Si el curso tenía un post_id en WP, preparar mapping
        $post_id = $object->get('post_id');
        if ($post_id > 0) {
            $data = $object->getMappingWP('remove');
        }

        // Llamar a remove() de la entidad (puede devolver código de resultado)
        $ok = $object->remove();

        // Si se borró correctamente y tenía post en WP, enviar petición para eliminar en WP
        if ($post_id > 0 && $ok == 525) {
            $data = $object->getMappingWP('remove');
            $responseData = $this->apiClientRest->send($post_id, $data, 'cursos', 'create');
        }

        // Redirigir al listado de cursos indicando el resultado en v2
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'cursos', 'v1' => (int) $this->_container->curs_page, 'v2' => $ok));
    }

    public function sincronizacursoAction() {
        // sincronizacursoAction: sincronizar curso con WordPress vía API REST
        $id = (int) $this->params()->fromRoute('v1', 0);
        $curso = new Curso($id);

        // 1) Asegurar que la categoría asociada existe en WP; si no, crearla primero
        $categoria = $curso->get('categoria');
        if ($categoria->get('taxonomy_id') == 0) {
            $dataCategory = $categoria->getMappingWP('create');
            $responseDataCategory = $this->apiClientRest->send($categoria->get('taxonomy_id'), $dataCategory, 'cursos-categorias', 'create');
            if (isset($responseDataCategory['id']) || (isset($responseDataCategory['data']['term_id']) && $responseDataCategory['data']['term_id'])) {
                $updateDataCategory = [];
                if (isset($responseDataCategory['id'])) {
                    $updateDataCategory['taxonomy_id'] = $responseDataCategory['id'];
                } else {
                    $updateDataCategory['taxonomy_id'] = $responseDataCategory['data']['term_id'];
                }
                $db_categorias = new Categorias();
                $db_categorias->update($updateDataCategory, 'id_cat = ' . $categoria->get('id'));
            }
        }

        // 2) Preparar mapping del curso y enviarlo a WP
        $data = $curso->getMappingWP('create');
        $responseData = $this->apiClientRest->send($curso->get('post_id'), $data, 'cursos', 'create');
        // Guardar respuesta en sesión para mostrarla en la UI
        $this->_container->respuestaSincronizacionCurso = $responseData;
        $updateData = [];
        $updateData['sincro'] = date('Y-m-d H:i:s');

        // 3) Si WP devolvió id y el curso no tenía post_id, actualizarlo
        if (isset($responseData['id']) && (int) $curso->get('post_id') == 0) {
            $updateData['post_id'] = $responseData['id'];
            $updateData['publicacion'] = date('Y-m-d');
            $resultado = 100; // sincronización OK
        } else {
            $resultado = 101; // fallo/otro
        }

        // 4) Actualizar la fila del curso con los metadatos de sincronización
        $db_cursos = new Cursos();
        $db_cursos->update($updateData, 'id_cur = ' . $curso->get('id'));

        // 5) Redirigir a la ficha del curso con resultado en v3
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => (int) $curso->get('id'), 'v2' => -1, 'v3' => $resultado));
    }

    public function xlscursosAction() {
        // xlscursosAction: exportar los cursos filtrados a Excel
        $where = Utilidades::generaCondicion('cursos', $this->_container->curs_buscador);
        $db = new Cursos();
        $objects = $db->get($where, 'nombre');
        $objPHPExcel = Exportar::cursos($objects);

        // Envío de headers para forzar descarga
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        // Terminar ejecución después de volcar el Excel
        die('Excel generado');
        exit;
    }

    public function inscripcionesAction() {
        // inscripcionesAction: listar inscripciones con filtros y paginación
        $this->layout()->title = 'Inscripciones';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Mensajes desde otras acciones
        if ($idm == 525) {
            $msg_ok = 'La inscripción ha sido borrada correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar la inscripción porque tiene otras entidades relacionadas.';
        }

        // Preparar capa de datos
        $db_inscripciones = new Inscripciones();
        $orderby = 'id_ins DESC';

        // Filtros desde POST
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // recoger campos de búsqueda y limpiarlos
            $data['id_ins'] = (int) $this->request->getPost('id_ins');
            $data['id_usu'] = (int) $this->request->getPost('id_usu');
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['tipo'] = (int) $this->request->getPost('tipo');
            $data['estado'] = (int) $this->request->getPost('estado');
            $data['pago'] = (int) $this->request->getPost('pago');

            // Guardar en sesión y resetear paginación
            $this->_container->insc_buscador = $data;
            $this->_container->insc_page = 0;
        } else {
            // Si v2 == 114, limpiar filtros
            if ($idm == 114) {
                if (isset($this->_container->insc_buscador)) {
                    unset($this->_container->insc_buscador);
                    $this->_container->insc_page = 0;
                }
            }
        }

        // Paginación
        $page = (int) $this->params()->fromRoute('v1', -1);
        if ($page == -1) {
            if (isset($this->_container->insc_page)) {
                $page = $this->_container->insc_page;
            } else {
                $page = 1;
                $this->_container->insc_page = $page;
            }
        } else {
            $this->_container->insc_page = $page;
        }
        if ($page == 0) {
            $page = 1;
        }
        $offset = 50 * ($page - 1);

        // Construir WHERE desde los filtros en sesión
        if (isset($this->_container->insc_buscador)) {
            $where = Utilidades::generaCondicion('inscripciones', $this->_container->insc_buscador);
            $this->_container->insc_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->insc_buscador;
        } else {
            $buscador = array('id_ins' => 0, 'id_usu' => null, 'id_cur' => null, 'fechaDesde' => null, 'fechaHasta' => null, 'tipo' => -1, 'estado' => -1, 'pago' => -1);
            $where = null;
        }

        // Leer datos y contar
        $inscripciones = $db_inscripciones->getInscripciones($where, $orderby, 50, $offset);
        $num = $db_inscripciones->numInscripciones($where);
        if ($num == 0) {
            if (isset($this->_container->insc_buscador)) {
                $msg_error = 'No hay ninguna inscripci&oacute;n con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ninguna inscripci&oacute;n guardada.';
            }
        }

        // Preparar ViewModel
        $view = array(
            'usuario' => $this->_usuario,
            'buscador' => $buscador,
            'page' => $page,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'num' => $num,
            'inscripciones' => $inscripciones
        );
        return new ViewModel($view);
    }

    public function inscripcionAction() {
        // inscripcionAction: crear/editar una inscripción y gestionar sus relaciones
        $this->layout()->title = 'Nueva | Inscripción';
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';

        // 1) Si es POST, recoger campos y validar
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Campos principales
            $data['id_ins'] = (int) $this->request->getPost('id_ins');
            $data['fecha'] = $filter->filter($this->request->getPost('fecha'));
            $data['id_usu'] = (int) $this->request->getPost('id_usu');
            $data['id_emp'] = (int) $this->request->getPost('id_emp');
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['beca'] = (int) $this->request->getPost('beca');
            $data['importe'] = $filter->filter($this->request->getPost('importe'));
            $data['beca_importe'] = $filter->filter($this->request->getPost('beca_importe'));
            $data['observaciones'] = $filter->filter($this->request->getPost('observaciones'));
            $data['estado'] = (int) $this->request->getPost('estado');
            $data['pago'] = (int) $this->request->getPost('pago');

            // Datos del usuario/proveedor de la inscripción
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
            $data['justificante_pago'] = $filter->filter($this->request->getPost('justificante_pago'));

            // Validación y guardado delegados a la entidad Inscripcion
            $inscripcion = new Inscripcion(0);
            $algun_valor_vacio = $inscripcion->set($data);
            if ($algun_valor_vacio > 0) {
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_ins'];
            } else {
                $id = $inscripcion->save();
                if ($data['id_ins'] == 0) {
                    $msg_ok = 'La inscripción ha sido creada correctamente.';
                } else {
                    $msg_ok = 'La inscripción ha sido actualizada correctamente.';
                }
            }
        } else {
            // Si no es POST, cargar la inscripción indicada por ruta
            $id = (int) $this->params()->fromRoute('v1', 0);
            $inscripcion = new Inscripcion($id);
        }

        // 2) Interpretar códigos adicionales v3 (acciones rápidas por rol admin)
        $idm = (int) $this->params()->fromRoute('v3', 0);
        if ($idm == 2 && $this->_usuario->get('rol') == '4dmin') {
            // Marcar como cobrado y enviar notificación de cobro
            $inscripcion->setEstado(2);
            $dev_mail = Notificaciones::enviarCobroInscripcion($inscripcion, $this->sendMail);
            if ($dev_mail['status']) {
                $msg_ok = 'La notificación del pago ha sido enviada correctamente.';
            } else {
                $msg_error = 'La notificación del pago no ha podido ser enviada. Por favor, compruebe el e-mail del usuario';
            }
        } else if ($idm == 3 && $this->_usuario->get('rol') == '4dmin') {
            // Aceptar inscripción y notificar
            $msg_ok = 'La inscripción ha sido aceptada.';
            $inscripcion->setEstado(3);
            $dev_mail = Notificaciones::enviarConfirmacionInscripcion($inscripcion, $this->sendMail);
            if ($dev_mail['status']) {
                $msg_ok .= ' Se ha notificado al usuario.';
            } else {
                $msg_error = 'La notificación de confirmación de la inscripción no ha podido ser enviada. Por favor, compruebe el e-mail del usuario';
            }
        } else if ($idm == 4 && $this->_usuario->get('rol') == '4dmin') {
            // Rechazar inscripción
            $msg_ok = 'La inscripción ha sido rechazada.';
            $inscripcion->setEstado(4);
        } else if ($idm == 5 && $this->_usuario->get('rol') == '4dmin') {
            // Enviar aviso de inscripción incompleta
            $dev_mail = Notificaciones::enviarInscripcionIncompleta($inscripcion, $this->sendMail);
            if ($dev_mail['status']) {
                $msg_ok = 'Se ha notificado al usuario que la inscripción está incompleta.';
            } else {
                $msg_error = 'La notificación de incripción incompleta no ha podido ser enviada. Por favor, compruebe el e-mail del usuario';
            }
        } else if ($idm == 400) {
            $msg_error = 'Debe seleccionar al menos un participante para poder añadirlo.';
            $tab = 'inscritos';
        } else if ($idm == 401) {
            $msg_ok = 'Se han añadido los participante seleccionados.';
            $tab = 'inscritos';
        } else if ($idm == 500) {
            $msg_error = 'Debe seleccionar al menos un participante para poder añadirlo.';
            $tab = 'participantes';
        } else if ($idm == 501) {
            $msg_ok = 'Se han añadido los participantes seleccionados.';
            $tab = 'participantes';
        } else if ($idm == 548) {
            $msg_ok = 'El justificante de pago ha sido subido correctamente.';
            $tab = 'default';
        } else if ($idm == 549) {
            $msg_error = 'El justificante de pago no tiene une extensión válida.';
            $tab = 'default';
        } else if ($idm == 550) {
            $msg_error = 'Ha ocurrido un error al subir el justificante de pago.';
            $tab = 'default';
        } else if ($idm == 551) {
            $msg_ok = 'El justificante de pago ha sido borrado correctamente.';
            $tab = 'default';
        }

        // 3) Si la inscripción existe, cargar inscrits/participantes/menores/trabajadores relacionados
        if ($id > 0) {
            $this->layout()->title = 'Inscripción ' . str_pad($inscripcion->get('id'), 5, '0', STR_PAD_LEFT);
            // Inscritos (paginación v2)
            $pagi = (int) $this->params()->fromRoute('v2', -1);
            if ($pagi == -1) {
                if (isset($this->_container->cins_page)) {
                    $pagi = $this->_container->cins_page;
                } else {
                    $pagi = 0;
                    $this->_container->cins_page = $pagi;
                }
            } else {
                $this->_container->cins_page = $pagi;
                $tab = 'inscritos';
            }
            if ($pagi == 0) {
                $pagi = 1;
            }
            $offseti = 50 * ($pagi - 1);
            $db_insc = new Inscripciones();
            $inscritos = $db_insc->getInscritos('id_ins = ' . $id, 'id_ins DESC', 50, $offseti);
            $numi = $db_insc->numInscritos('id_ins = ' . $id);
            $curso = $inscripcion->get('curso');
            $creador = $inscripcion->get('creador');
            $empresa = $inscripcion->get('empresa');

            // Participantes (paginación v4)
            $pagm = (int) $this->params()->fromRoute('v4', -1);
            if ($pagm == -1) {
                if (isset($this->_container->cmen_page)) {
                    $pagm = $this->_container->cmen_page;
                } else {
                    $pagm = 0;
                    $this->_container->cmen_page = $pagm;
                }
            } else {
                $this->_container->cmen_page = $pagm;
                $tab = 'participantes';
            }
            if ($pagm == 0) {
                $pagm = 1;
            }
            $offsetm = 50 * ($pagm - 1);
            $db_men = new Inscripciones();
            $participantes = $db_men->getParticipantes('id_ins = ' . $id, 'id_ins DESC', 50, $offsetm);
            $numm = $db_men->numParticipantes('id_ins = ' . $id);

            // Evitar duplicados: excluir participantes e inscritos ya presentes
            $where = 'id_usu = ' . $inscripcion->get('id_usu');
            foreach ($participantes as $participante) :
                $where .= ' AND id_men != ' . $participante['id_men'];
            endforeach;

            $db_menores = new Menores();
            $menores = $db_menores->get($where, ['nombre asc', 'apellidos asc']);

            $where = 'id_emp = ' . $empresa->get('id');
            foreach ($inscritos as $inscrito) :
                $where .= ' AND id_usu != ' . $inscrito['id_usu'];
            endforeach;
            $db_trabajadores = new Usuarios();
            $trabajadores = $db_trabajadores->get($where, ['nombre asc', 'apellidos asc']);
        } else {
            // Valores por defecto si no existe la inscripción
            $inscritos = [];
            $numi = 0;
            $pagi = 0;
            $curso = null;
            $creador = null;
            $empresa = null;
            $participantes = [];
            $numm = 0;
            $pagm = 0;
            $menores = [];
            $trabajadores = [];
        }

        // Preparar ViewModel
        $view = array(
            'usuario' => $this->_usuario,
            'inscripcion' => $inscripcion,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'inscritos' => $inscritos,
            'curso' => $curso,
            'creador' => $creador,
            'empresa' => $empresa,
            'numi' => $numi,
            'pagi' => $pagi,
            'participantes' => $participantes,
            'menores' => $menores,
            'trabajadores' => $trabajadores,
            'numm' => $numm,
            'pagm' => $pagm,
            'tab_inscripcion' => $tab,
        );
        return new ViewModel($view);
    }

    public function borrarinscripcionAction() {
        // borrarinscripcionAction: eliminar una inscripción y redirigir según contexto
        // Entrada (ruta): v1 = id_ins (int), v2 = urlRedirect (int, opcional)
        // Salida: redirección a la lista de inscripciones o a la ficha de usuario según v2
        // Efectos: llama a Inscripcion::remove() que debe encargarse de cascadas o comprobaciones

        // 1) Leer parámetros de ruta
        $id = (int) $this->params()->fromRoute('v1', 0);
        $urlRedirect = (int) $this->params()->fromRoute('v2', 0);

        // 2) Cargar la entidad y delegar el borrado a su método remove()
        $object = new Inscripcion($id);
        // remove() devuelve un código de resultado (p. ej. 525 = OK, 536 = dependencias)
        $ok = $object->remove();

        // 3) Redirecciones condicionales:
        // - Si v2 (urlRedirect) > 0: volver a la ficha del usuario asociado
        // - Si no: volver al listado de inscripciones (página guardada en sesión)
        if ($urlRedirect > 0) {
            // Redirige a la ficha del usuario que creó la inscripción
            return $this->redirect()->toRoute('backend/default', array(
                'controller' => 'usuarios',
                'action' => 'ficha',
                'v1' => (int) $object->get('id_usu'),
                'v2' => 0,
                'v3' => 0,
                'v4' => 0,
                'v5' => 0,
                'v6' => $ok
            ));
        } else {
            // Redirige al listado de inscripciones mostrando el resultado (ok)
            return $this->redirect()->toRoute('backend/default', array(
                'controller' => 'formacion',
                'action' => 'inscripciones',
                'v1' => (int) $this->_container->insc_page,
                'v2' => $ok
            ));
        }
    }

    public function inscripcionjustificanteAction(){
        // inscripcionjustificanteAction: subir o borrar justificante de pago de una inscripción
        // Flujo:
        // - Si request POST: validar y guardar fichero en disco, actualizar entidad Inscripcion
        // - Si request GET: borrar justificante existente
        // Respuesta: redirección a la ficha de inscripción con código de resultado (v3)

        // Inicializar código por defecto (550 = error subida)
        $msg = 550;

        if ($this->request->isPost()) {
            // 1) Recoger id de inscripción desde POST y preparar entidad
            $id_ins = (int) $this->request->getPost('id_ins');
            $inscripcion = new Inscripcion($id_ins);
            // Directorio donde se guardan los justificantes (constante en la entidad)
            $nombreDirectorio = $inscripcion::FILE_DIRECTORY_JUSTIFICANTE;

            // 2) Preparar adaptador/validadores de fichero
            // - Tamaño máximo: 10MB
            // - Extensiones permitidas: pdf, doc, docx, jpg, png, jpeg
            $httpadapter = new \Zend\File\Transfer\Adapter\Http();
            $filesize = new \Zend\Validator\File\Size(array('min' => '0kB', 'max' => '10MB'));
            $extension = new \Zend\Validator\File\Extension(array('extension' => array('pdf', 'doc', 'docx', 'jpg', 'png', 'jpeg')));
            $httpadapter->setValidators(array($filesize, $extension));

            // 3) Obtener información del fichero subido
            $files = $httpadapter->getFileInfo('justificante_pago');

            // 4) Validar y mover el fichero si es correcto
            if ($httpadapter->isValid()) {
                // Crear directorio si no existe (permisos 0750)
                if (!file_exists($nombreDirectorio)) {
                    mkdir($nombreDirectorio, 0750);
                }

                // Construir nombre único usando timestamp y mantener extensión
                $fichero = $files['justificante_pago']['name'];
                $ext = pathinfo($fichero, PATHINFO_EXTENSION);
                $fichero = time() . "." . $ext;

                // Añadir filtro para renombrar y establecer destino
                $httpadapter->addFilter('filerename', ['target' => $nombreDirectorio . $fichero]);
                $httpadapter->setDestination($nombreDirectorio);

                // Recibir (mover) el fichero al destino
                if ($httpadapter->receive($files['justificante_pago']['name'])) {
                    // Actualizar la entidad Inscripcion para referenciar el justificante
                    $inscripcion->setJustificante($fichero);
                    $msg = 548; // subida correcta
                }
            } else {
                // Validación fallida (extensión inválida o tamaño excedido)
                $msg = 549;
            }
        } else {
            // Si no es POST, se interpreta como petición de borrado del justificante
            $id_ins = (int) $this->params()->fromRoute('v1', 0);
            $inscripcion = new Inscripcion($id_ins);
            // removeJustificante() debe encargarse de eliminar el fichero y limpiar la referencia
            $inscripcion->removeJustificante();
            $msg = 551; // borrado correcto
        }

        // Redirigir de vuelta a la ficha de inscripción con el código de resultado
        return $this->redirect()->toRoute('backend/default', array(
            'controller' => 'formacion',
            'action' => 'inscripcion',
            'v1' => $id_ins,
            'v2' => -1,
            'v3' => $msg
        ));
    }

    public function xlsinscripcionesAction() {
        // xlsinscripcionesAction: exportar inscripciones filtradas a Excel
        // Flujo:
        // 1) Generar cláusula WHERE usando los filtros guardados en sesión
        // 2) Recuperar objetos desde la capa de datos
        // 3) Delegar la creación del objeto PHPExcel a Exportar::inscripciones()
        // 4) Enviar headers y volcar el Excel a la salida
        // Nota de seguridad/operación: esta acción finaliza la respuesta con die()/exit
        // lo que interrumpe el flujo MVC normal. Sería más recomendable devolver un
        // StreamResponse para permitir pruebas y evitar side-effects.

        // 1) Construir WHERE según filtros de sesión
        $where = Utilidades::generaCondicion('inscripciones', $this->_container->insc_buscador);

        // 2) Leer datos desde la capa de datos
        $db = new Inscripciones();
        // getInscripciones devuelve un array de resultados ya formateados para la exportación
        $objects = $db->getInscripciones($where, ['usuariosNombre', 'usuariosApellidos']);

        // 3) Generar el objeto PHPExcel delegando a la utilidad de exportación
        $objPHPExcel = Exportar::inscripciones($objects);

        // 4) Preparar y enviar headers HTTP para forzar la descarga
        // - Content-type: tipo Excel compatible con Excel5
        // - Content-Disposition: sugiere un nombre de fichero con la fecha actual
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        // 5) Crear writer y volcar al output
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');

        // 6) Terminar ejecución: comportamiento actual del proyecto
        // Esto garantiza que no se renderice ninguna vista adicional.
        die('Excel generado');
        exit;
    }

    public function xlsinscritosAction() {
        // xlsinscritosAction: exportar inscritos (de un curso o según filtros) a Excel
        // Soporta dos modos:
        // - Si se pasa v1 = id_cur, exporta los inscritos de ese curso
        // - Si no, usa los filtros guardados en sesión para construir WHERE
        // Pasos:
        // 1) Determinar cláusula WHERE
        // 2) Recuperar inscritos
        // 3) Delegar la generación del Excel y volcarlo

        // 1) Leer id_cur desde la ruta
        $id_cur = (int) $this->params()->fromRoute('v1', 0);
        if ($id_cur) {
            // Exportar inscritos de un curso concreto
            $where = 'id_cur = ' . $id_cur;
        } else {
            // Exportar según filtros guardados en sesión
            $where = Utilidades::generaCondicion('inscritos', $this->_container->insc_buscador);
        }

        // 2) Leer desde la capa de datos
        $db = new Inscripciones();
        $objects = $db->getInscritos($where, ['usuariosNombre', 'usuariosApellidos']);

        // 3) Generar PHPExcel y volcarlo a la salida
        $objPHPExcel = Exportar::inscritos($objects);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        // Nota: se mantiene die()/exit para preservar el comportamiento actual
        die('Excel generado');
        exit;
    }

    public function xlsparticipantesAction() {
        // xlsparticipantesAction: exportar participantes filtrados a Excel
        // Pasos:
        // 1) Construir WHERE a partir de los filtros guardados en sesión
        // 2) Recuperar participantes desde la capa de datos
        // 3) Delegar la creación del PHPExcel a Exportar::participantes()
        // 4) Enviar headers y volcar el Excel al output
        // Nota: el método termina con die()/exit para forzar la descarga; se recomienda
        // reemplazar esto por un StreamResponse para mejorar testabilidad y evitar side-effects.

        // 1) WHERE según filtros
        $where = Utilidades::generaCondicion('participantes', $this->_container->par_buscador);

        // 2) Leer desde la capa de datos
        $db = new Inscripciones();
        $objects = $db->getParticipantes($where, ['usuariosNombre', 'usuariosApellidos']);

        // 3) Generar objeto PHPExcel
        $objPHPExcel = Exportar::participantes($objects);

        // 4) Enviar headers y escribir el fichero a la salida
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');

        // Mantener comportamiento actual del proyecto: terminar ejecución
        die('Excel generado');
        exit;
    }

    public function categoriasAction() {
        // categoriasAction: gestionar listado y edición masiva/individual de categorías
        // Responsabilidades:
        // - manejar búsquedas y filtros (botón 'buscar')
        // - editar varias categorías a la vez ('guardar-todos')
        // - editar una categoría específica (botón con índice)
        // - paginación y preparación del ViewModel

        // 1) Preparar título y códigos de mensajes (v2)
        $this->layout()->title = 'Categorias';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Mensajes desde otras acciones (ej. borrado)
        if ($idm == 525) {
            $msg_ok = 'La categoría ha sido borrada correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar la categoría porque tiene cursos relacionados.';
        }

        // 2) Capa de datos y orden por defecto
        $db = new Categorias();
        $orderby = 'nombre ASC';

        // 3) Manejo del POST: puede ser búsqueda, guardar-todos o guardar uno a uno
        if ($this->request->isPost()) {
            $data = [];
            $boton = $this->request->getPost('boton');

            if ($boton == 'buscar') {
                // Solo aplicar filtro de id_cat y guardarlo en sesión
                $data['id_cat'] = (int) $this->request->getPost('id_cat');
                $this->_container->cate_buscador = $data;
            } else if ($boton == 'guardar-todos') {
                // Guardado masivo: leer arrays de campos y recorrerlos
                $id_cats = $this->request->getPost('id_cat');
                $nombres = $this->request->getPost('nombre');
                $taxonomy_id = $this->request->getPost('taxonomy_id');
                $formacion = $this->request->getPost('formacion');
                $empleo = $this->request->getPost('empleo');

                // Recorrer cada fila y actualizar solo si el nombre no está vacío
                foreach ($id_cats as $i => $id_cat) :
                    if (!empty($nombres[$i])) {
                        $data = [
                            'id_cat' => $id_cat,
                            'nombre' => $nombres[$i],
                            'taxonomy_id' => (int) $taxonomy_id[$i],
                            'formacion' => (int) $formacion[$i],
                            'empleo' => (int) $empleo[$i]
                        ];
                        // Delegar la persistencia a la entidad Categoria
                        $object = new Categoria(0);
                        $object->set($data);
                        $object->save();
                    }
                endforeach;
                $msg_ok = 'Las categorías han sido actualizadas correctamente.';
            } else {
                // Guardado individual: el botón contiene el índice de la fila
                $i = $boton;
                $id_cats = $this->request->getPost('id_cat');
                $nombres = $this->request->getPost('nombre');
                $taxonomy_id = $this->request->getPost('taxonomy_id');
                $formacion = $this->request->getPost('formacion');
                $empleo = $this->request->getPost('empleo');
                if (!empty($nombres[$i])) {
                    $data = [
                        'id_cat' => $id_cats[$i],
                        'nombre' => $nombres[$i],
                        'taxonomy_id' => (int) $taxonomy_id[$i],
                        'formacion' => (int) $formacion[$i],
                        'empleo' => (int) $empleo[$i]
                    ];
                    $object = new Categoria(0);
                    $object->set($data);
                    $object->save();
                }
                $msg_ok = 'La categoría ha sido actualizada correctamente.';
            }
        } else {
            // Si no es POST, comprobar si se pidió limpiar filtros (v2 == 114)
            if ($idm == 114) {
                if (isset($this->_container->cate_buscador)) {
                    unset($this->_container->cate_buscador);
                    $this->_container->cate_page = 0;
                }
            }
        }

        // 4) Paginación: leer página desde ruta o sesión
        $page = (int) $this->params()->fromRoute('v1', -1);
        if ($page == -1) {
            if (isset($this->_container->cate_page)) {
                $page = $this->_container->cate_page;
            } else {
                $page = 1;
                $this->_container->cate_page = $page;
            }
        } else {
            $this->_container->cate_page = $page;
        }
        if ($page == 0) {
            $page = 1;
        }
        $offset = 50 * ($page - 1);

        // 5) Construcción del WHERE desde filtros en sesión
        if (isset($this->_container->cate_buscador)) {
            // Utilidades::generaCondicion devuelve una cláusula SQL como string.
            // Atención: si la función realiza concatenaciones directas, existe riesgo de inyección
            // si los filtros no se han saneado correctamente dentro de la función.
            $where = Utilidades::generaCondicion('categorias', $this->_container->cate_buscador);
            $this->_container->cate_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->cate_buscador;
        } else {
            $buscador = array('id_cat' => 0);
            $where = null;
        }

        // 6) Leer de base de datos
        $objects = $db->get($where, $orderby, 50, $offset);
        $num = $db->num($where);
        if ($num == 0) {
            if (isset($this->_container->cate_buscador)) {
                $msg_ko = 'No hay ninguna categoría con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_ko = 'No hay ninguna categoría guardada.';
            }
        }

        // 7) Preparar ViewModel y devolver
        $view = array(
            'usuario' => $this->_usuario,
            'buscador' => $buscador,
            'page' => $page,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'num' => $num,
            'categorias' => $objects
        );
        return new ViewModel($view);
    }

    public function borrarcategoriaAction() {
        // borrarcategoriaAction: elimina una categoría y redirige al listado
        // Entrada (ruta): v1 = id_cat (int)
        // Salida: redirección al listado de categorías con código de resultado en v2
        // Efectos: delega el borrado en Categoria::remove() que debe controlar integridad referencial

        // 1) Leer id desde la ruta
        $id = (int) $this->params()->fromRoute('v1', 0);

        // 2) Cargar la entidad y pedir el borrado
        // Nota: la entidad puede devolver códigos distintos según el resultado (p. ej. 525 OK, 536 dependencias)
        $object = new Categoria($id);
        $ok = $object->remove();

        // 3) Redirigir al listado de categorías indicando resultado
        return $this->redirect()->toRoute('backend/default', array(
            'controller' => 'formacion',
            'action' => 'categorias',
            'v1' => (int) $this->_container->cat_page,
            'v2' => $ok
        ));
    }

    public function inscritosAction() {
        // inscritosAction: listar inscritos y (para usuarios no-admin) sus propios registros
        // Descripción rápida:
        // - Admin (`rol` == '4dmin'): ve todos los inscritos
        // - Usuario normal: ve solo sus inscripciones o las que creó
        // - Se soportan filtros guardados en sesión y paginación

        // 1) Título según rol
        if ($this->_usuario->get('rol') == '4dmin') {
            $this->layout()->title = 'Inscritos';
        } else {
            $this->layout()->title = 'Mis inscripciones';
        }

        // 2) Mensajes (v2) para resultados de otras operaciones
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        if ($idm == 525) {
            $msg_ok = 'El registro ha sido borrado correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar el registro porque tiene otras entidades relacionadas.';
        }

        // 3) Preparar capa de datos y orden por defecto
        $db_inscritos = new Inscripciones();
        $orderby = 'usuariosNombre ASC';

        // 4) Manejo de filtros desde POST
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Recoger y limpiar parámetros de búsqueda
            $data['id_ui'] = (int) $this->request->getPost('id_ui');
            $data['id_usu'] = (int) $this->request->getPost('id_usu');
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['tipo'] = (int) $this->request->getPost('tipo');
            $data['estado'] = (int) $this->request->getPost('estado');

            // Guardar filtros en sesión y resetear paginación
            $this->_container->inscr_buscador = $data;
            $this->_container->inscr_page = 0;
        } else {
            // Si se pide limpieza de filtros (v2 == 114), eliminar del contenedor
            if ($idm == 114) {
                if (isset($this->_container->inscr_buscador)) {
                    unset($this->_container->inscr_buscador);
                    $this->_container->inscr_page = 0;
                }
            }
        }

        // 5) Paginación: leer página desde ruta o sesión
        $pagin = (int) $this->params()->fromRoute('v1', -1);
        if ($pagin == -1) {
            if (isset($this->_container->inscr_page)) {
                $pagin = $this->_container->inscr_page;
            } else {
                $pagin = 1;
                $this->_container->inscr_page = $pagin;
            }
        } else {
            $this->_container->inscr_page = $pagin;
        }
        if ($pagin == 0) {
            $pagin = 1;
        }
        $offset = 50 * ($pagin - 1);

        // 6) Construcción del WHERE a partir de los filtros guardados
        if (isset($this->_container->inscr_buscador)) {
            $where = Utilidades::generaCondicion('inscritos', $this->_container->inscr_buscador);
            $this->_container->inscr_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->inscr_buscador;
        } else {
            $buscador = array('id_ui' => 0, 'id_usu' => null, 'id_cur' => null, 'fechaDesde' => null, 'fechaHasta' => null, 'tipo' => -1, 'estado' => -1);
            $where = null;
        }

        // 7) Restringir visibilidad para usuarios no-admin: solo sus registros o los que creó
        if ($this->_usuario->get('rol') != '4dmin') {
            // Construcción segura: aquí se concatena el id en la condición tal como hace el proyecto
            // Recomendación: parametrizar para evitar riesgos si los ids se manipulan externamente.
            (isset($where)) ? ($where .= ' AND (id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ')') : ($where = '(id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ')');
        }

        // 8) Lectura desde la capa de datos
        $inscritos = $db_inscritos->getInscritos($where, $orderby, 50, $offset);
        $numin = $db_inscritos->numInscritos($where);

        // 9) Para usuarios no-admin también añadir participantes y sumar totales
        if ($this->_usuario->get('rol') != '4dmin') {
            $participantes = $db_inscritos->getParticipantes($where, $orderby, 50, $offset);
            $nump = $db_inscritos->numParticipantes($where);
            // Sumar totales y fusionar arrays para la vista
            $numin += $nump;
            $inscritos = array_merge($inscritos, $participantes);
        }

        // 10) Mensaje si no hay resultados
        if ($numin == 0) {
            if (isset($this->_container->inscr_buscador)) {
                $msg_error = 'No hay ningun registro con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ningún registro guardado.';
            }
        }

        // 11) Preparar ViewModel y devolver
        $view = array(
            'usuario' => $this->_usuario,
            'buscador' => $buscador,
            'pagin' => $pagin,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'numin' => $numin,
            'inscritos' => $inscritos,
        );
        return new ViewModel($view);
    }

    public function participantesAction() {
        // participantesAction: listado paginado de participantes
        // Propósito: mostrar participantes (menores) inscritos en cursos, con filtros y paginación
        // Entradas relevantes:
        // - v1: página (paginación)
        // - v2: códigos de resultado (mensajes)
        // POST: acepta filtros que se guardan en sesión para persistir la búsqueda

        // 1) Preparar título y mensajes según códigos v2
        $this->layout()->title = 'Participantes';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        if ($idm == 525) {
            $msg_ok = 'El registro ha sido borrado correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar el registro porque tiene otras entidades relacionadas.';
        }

        // 2) Preparar capa de datos y orden por defecto
        $db_participantes = new Inscripciones();
        $orderby = 'usuariosNombre ASC';

        // 3) Manejo de filtros enviados por POST
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Recoger parámetros y sanitizarlos cuando sea necesario
            $data['id_par'] = (int) $this->request->getPost('id_par');
            $data['id_usu'] = (int) $this->request->getPost('id_usu');
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['menorNombre'] = $filter->filter($this->request->getPost('menorNombre'));
            $data['estado'] = (int) $this->request->getPost('estado');

            // Guardar filtros en sesión y resetear paginación
            $this->_container->par_buscador = $data;
            $this->_container->par_page = 0;
        } else {
            // Si se pide limpieza de filtros (v2 == 114), eliminarlos
            if ($idm == 114) {
                if (isset($this->_container->par_buscador)) {
                    unset($this->_container->par_buscador);
                    $this->_container->par_page = 0;
                }
            }
        }

        // 4) Paginación: leer página desde ruta o sesión
        $pagp = (int) $this->params()->fromRoute('v1', -1);
        if ($pagp == -1) {
            if (isset($this->_container->par_page)) {
                $pagp = $this->_container->par_page;
            } else {
                $pagp = 1;
                $this->_container->par_page = $pagp;
            }
        } else {
            $this->_container->par_page = $pagp;
        }
        if ($pagp <= 0) {
            $pagp = 1;
        }
        $offset = 50 * ($pagp - 1);

        // 5) Construcción de la cláusula WHERE desde filtros en sesión
        if (isset($this->_container->par_buscador)) {
            $where = Utilidades::generaCondicion('participantes', $this->_container->par_buscador);
            $this->_container->par_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->par_buscador;
        } else {
            // Valores por defecto del buscador
            $buscador = array('id_par' => 0, 'id_usu' => null, 'id_cur' => null, 'fechaDesde' => null, 'fechaHasta' => null, 'menorNombre' => null, 'estado' => -1);
            $where = null;
        }

        // 6) Leer participantes y contar
        $participantes = $db_participantes->getParticipantes($where, $orderby, 50, $offset);
        $nump = $db_participantes->numParticipantes($where);
        if ($nump == 0) {
            if (isset($this->_container->par_buscador)) {
                $msg_error = 'No hay ningun registro con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ningún registro guardado.';
            }
        }

        // 7) Preparar ViewModel para la plantilla
        $view = array(
            'usuario' => $this->_usuario,
            'buscador' => $buscador,
            'pagp' => $pagp,
            'ok' => $msg_ok,
            'ko' => $msg_error,
            'nump' => $nump,
            'participantes' => $participantes
        );
        return new ViewModel($view);
    }

    // public function asistentesAction() {
        
    // }

    public function borrarinscritoAction() {
        // borrarinscritoAction: eliminar un registro de inscrito y redirigir al listado
        // Entrada (ruta): v1 = id_ui (id del inscrito)
        // Salida: redirección a la lista de inscritos con código de resultado en v2

        // 1) Leer id desde la ruta y cargar la entidad
        $id = (int) $this->params()->fromRoute('v1', 0);
        $object = new Inscrito($id);

        // 2) Delegar el borrado a la entidad (remove devuelve código de resultado)
        $ok = $object->remove();

        // 3) Redirigir al listado de inscritos mostrando el resultado
        return $this->redirect()->toRoute('backend/default', array(
            'controller' => 'formacion',
            'action' => 'inscritos',
            'v1' => (int) $this->_container->inscr_page,
            'v2' => $ok
        ));
    }

    public function borrarparticipanteAction() {
        // borrarparticipanteAction: eliminar un participante (menor) y redirigir
        // Entrada: v1 = id_par (int)
        // Salida: redirección a la lista de participantes con código de resultado en v2
        // Efectos: delega la eliminación a Participante::remove(), que debería gestionar integridad

        // 1) Leer id de la ruta y cargar la entidad
        $id = (int) $this->params()->fromRoute('v1', 0);
        $object = new Participante($id);

        // 2) Ejecutar remove() en la entidad; puede devolver códigos (ej. 525 ok, 536 dependencias)
        $ok = $object->remove();

        // 3) Redirigir al listado de participantes indicando el resultado
        return $this->redirect()->toRoute('backend/default', array(
            'controller' => 'formacion',
            'action' => 'participantes',
            'v1' => (int) $this->_container->par_page,
            'v2' => $ok
        ));
    }

    public function enviaremailpagoAction() {
        // enviaremailpagoAction: enviar un correo con enlace de pago para una inscripción
        // Flujo:
        // 1) Validar que la inscripción, usuario y curso existen
        // 2) Construir la URL base del sitio para el enlace de pago
        // 3) Cargar plantilla de correo y sustituir marcadores
        // 4) Enviar el correo mediante el servicio SendMail
        // 5) Redirigir a la ficha de inscripción con un código de resultado

        // 1) Obtener id de la ruta y cargar entidades relacionadas
        $id = (int) $this->params()->fromRoute('v1', 0);
        $inscripcion = new Inscripcion($id);
        $usuario = $inscripcion->get('creador');
        $curso = $inscripcion->get('curso');

        // 2) Validaciones básicas: existencia de inscripción/usuario/curso
        if ($inscripcion->get('id') == 0) {
            $msg = 400; // inscripción inexistente
        } else if ($usuario->get('id') == 0) {
            $msg = 401; // usuario inexistente
        } else if ($curso->get('id') == 0) {
            $msg = 402; // curso inexistente
        } else if ($inscripcion->get('importe') > 0) {
            // 3) Construir baseUrl para el enlace de pago
            $basePath = $this->getRequest()->getBasePath();
            $uri = new \Zend\Uri\Uri($this->getRequest()->getUri());
            $uri->setPath($basePath);
            $uri->setQuery(array());
            $uri->setFragment('');
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost() . '' . $uri->getPath();

            // 4) Obtener plantilla de mensaje (utilidad del proyecto)
            $message = Utilidades::getMessageEmailPayment();

            // 5) Resolver marcadores como [tipo-curso] y [nombre-curso]
            $tipoCurso = Utilidades::systemOptions('cursos', 'tipo', 1)[(int) $curso->get('tipo')];

            // Reemplazar marcadores en el subject
            $message['subject'] = str_replace([
                '[tipo-curso]',
                '[nombre-curso]',
                    ], [
                $tipoCurso,
                $curso->get('nombre'),
                    ]
                    , $message['subject']
            );

            // Reemplazar marcadores en el cuerpo del mensaje, incluyendo el enlace de pago
            $message['message'] = str_replace([
                '[user-name]',
                '[tipo-curso]',
                '[nombre-curso]',
                '[course-payment-link]',
                    ], [
                $usuario->get('nombre'),
                $tipoCurso,
                $curso->get('nombre'),
                '<a href="' . $baseUrl . '/pagar-curso/' . Utilidades::encriptaIdCurso($inscripcion->get('id'), 'enc') . '">Realizar pago</a>'
                    ]
                    , $message['message']
            );

            // 6) Preparar destinatarios: por ahora solo al creador de la inscripción
            $mails = [];
            $mails[] = [
                'mail' => $usuario->get('email'),
                'name' => $usuario->get('nombre') . ' ' . $usuario->get('apellidos'),
                'to_type' => 'default'
            ];

            // 7) Enviar el correo usando el servicio SendMail inyectado en el constructor
            $dev_mail = $this->sendMail->sendMail($mails, $message['subject'], $message['message']);

            // 8) Interpretar resultado del envío
            if ($dev_mail['status']) {
                $msg = 200; // envío correcto
            } else {
                $msg = 404; // fallo envío
            }
        } else {
            // Si el importe es 0 o nulo, no tiene sentido enviar enlace de pago
            $msg = 403; // prohibido / condición no satisfecha
        }

        // 9) Redirigir de vuelta a la ficha de inscripción con el código de resultado
        return $this->redirect()->toRoute('backend/default', array(
            'controller' => 'formacion',
            'action' => 'inscripcion',
            'v1' => (int) $inscripcion->get('id'),
            'v2' => -1,
            'v3' => $msg
        ));
    }

    public function agregaparticipantesAction() {
        // agregaparticipantesAction: añade trabajadores (usuarios) seleccionados como inscritos
        // a una inscripción existente.
        // Entradas (POST):
        // - id_ins: id de la inscripción padre (int)
        // - id_usu: array asociativo donde las claves son los ids de usuario marcados
        // Salida: redirección a la ficha de inscripción con v3 = código de resultado
        // Códigos de resultado (convención en este controlador):
        // - 400: no se seleccionaron trabajadores
        // - 401: se añadieron los trabajadores correctamente
        // Efectos secundarios: crea instancias de Inscrito y actualiza el importe de la Inscripcion
        // Notas de seguridad: se castea y valida id_ins y los ids de usuario; no confíe en datos del cliente

        if ($this->request->isPost()) {
            $filter = new StripTags();

            // 1) Leer id de la inscripción y cargar la entidad
            $id = (int) $this->request->getPost('id_ins');
            $inscripcion = new Inscripcion($id);

            // 2) Recoger la lista de usuarios seleccionados.
            // En el formulario se envía un array donde la clave es el id del usuario.
            $id_usu = $this->request->getPost('id_usu');
            $trabajadores = [];
            if (is_array($id_usu)) {
                // Convertir las claves a una lista de ids (asegurando que sean ints)
                foreach ($id_usu as $index => $value):
                    $trabajadores[] = (int) $index;
                endforeach;
            }

            // 3) Validación: debe existir al menos un trabajador marcado
            if (count($trabajadores) == 0) {
                $msg = 400; // falta selección
            } else {
                // 4) Para cada trabajador, construir los datos del Inscrito y persistir
                $curso = $inscripcion->get('curso');
                foreach ($trabajadores as $id_tra):
                    // Cargar usuario para calcular tarifas y metadatos
                    $usuario = new Usuario($id_tra);
                    $importe = 0;
                    // Si está en situación colegiado (sitcol == 0) usar precio_col, si no precio_otr
                    if ($usuario->get('sitcol') == 0) {
                        $importe = $curso->get('precio_col');
                    } else {
                        $importe = $curso->get('precio_otr');
                    }

                    // 5) Preparar arreglo con los campos que pide la entidad Inscrito
                    $data_inscrito = [];
                    $data_inscrito['id_ui'] = 0;
                    $data_inscrito['id_ins'] = $inscripcion->get('id');
                    $data_inscrito['id_usu'] = $id_tra;
                    $data_inscrito['sitcol'] = $usuario->get('sitcol');
                    $data_inscrito['sitlab'] = $usuario->get('sitlab');
                    $data_inscrito['importe'] = $importe;
                    $data_inscrito['diploma'] = null;
                    $data_inscrito['asistencia'] = 0;

                    $data_inscrito['fecha'] = date('d-m-Y');
                    // 6) Delegar validación y guardado en la entidad Inscrito
                    $inscrito = new Inscrito(0);
                    $algun_valor_vacio = $inscrito->set($data_inscrito);
                    if ($algun_valor_vacio == 0) {
                        $id_ui = $inscrito->save();
                    }
                endforeach;

                // 7) Actualizar el importe total de la inscripción (lógica en entidad)
                $inscripcion->revisaImporte();

                $msg = 401; // añadidos correctamente
            }
        }

        // 8) Redirección: volver a la ficha de inscripción indicando el resultado
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscripcion', 'v1' => $inscripcion->get('id'), 'v2' => -1, 'v3' => $msg));
    }

    public function agregamenoresAction() {
        // agregamenoresAction: añade menores seleccionados como participantes a una inscripción
        // Entradas (POST):
        // - id_ins: id de la inscripción (int)
        // - id_men: array asociativo con claves = id_men seleccionados
        // Salida: redirección a la ficha de inscripción con v3 = código de resultado
        // Códigos:
        // - 500: no se seleccionaron menores
        // - 501: menores añadidos correctamente
        // Efectos: crea entidades Participante y actualiza el importe de la inscripción
        // Notas de seguridad: validar ids, permisos, y evitar confiar en la estructura del POST

        if ($this->request->isPost()) {
            $filter = new StripTags();

            // 1) Leer id de inscripción y cargar entidad
            $id = (int) $this->request->getPost('id_ins');
            $inscripcion = new Inscripcion($id);

            // 2) Recoger la lista de menores seleccionados (claves del array POST)
            $id_men = $this->request->getPost('id_men');
            $menores = [];
            if (is_array($id_men)) {
                foreach ($id_men as $index => $value):
                    $menores[] = (int) $index;
                endforeach;
            }

            // 3) Validación: al menos un menor seleccionado
            if (count($menores) == 0) {
                $msg = 500;
            } else {
                // 4) Para cada menor, crear la entidad Participante y guardarla
                $curso = $inscripcion->get('curso');
                foreach ($menores as $id_men):
                    // Cargar menor (solo para posibles comprobaciones adicionales)
                    $menor = new Menor($id_men);

                    // Importes de menores usan normalmente la tarifa no colegiado
                    $importe = $curso->get('precio_otr');

                    // 5) Preparar datos para la entidad Participante
                    $data_participante = [];
                    $data_participante['id_par'] = 0;
                    $data_participante['id_ins'] = $inscripcion->get('id');
                    $data_participante['id_men'] = $id_men;
                    $data_participante['fecha'] = date('d-m-Y');
                    $data_participante['importe'] = $importe;

                    // 6) Delegar validación y guardado en la entidad Participante
                    $participante = new Participante(0);
                    $algun_valor_vacio = $participante->set($data_participante);
                    if ($algun_valor_vacio == 0) {
                        $id_par = $participante->save();
                    }
                endforeach;

                // 7) Actualizar importe total en la inscripción
                $inscripcion->revisaImporte();

                $msg = 501; // menores añadidos correctamente
            }
        }

        // 8) Redirección: volver a la ficha de inscripción con resultado
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscripcion', 'v1' => $inscripcion->get('id'), 'v2' => -1, 'v3' => $msg));
    }

    public function asistenciaAction() {
        // asistenciaAction: marcar asistencia de inscritos seleccionados
        // Entradas (POST):
        // - id_cur: id del curso (usado solo para redirección)
        // - asistencia: array donde las claves son ids de Inscrito marcados
        // Salida: redirección a la ficha de curso con código de resultado en v3
        // Códigos: 500 = ningún inscrito seleccionado, 501 = asistentes marcados correctamente
        // Efectos: llama a Inscrito::setAsistencia(1) para cada id; se asume que el método
        // persiste el cambio (o lo hace internamente). Si no, habría que llamar a save().
        // Notas de seguridad y concurrencia:
        // - Validar y castear IDs a int antes de crear entidades.
        // - Considerar bloqueo/optimista para evitar condiciones de carrera si varios usuarios
        //   actualizan asistencia simultáneamente.

        if ($this->request->isPost()) {
            $filter = new StripTags();

            // 1) Leer id del curso para la redirección final
            $id = (int) $this->request->getPost('id_cur');

            // 2) Recoger el array 'asistencia' donde la clave es el id del inscrito
            $asistencia = $this->request->getPost('asistencia');
            $inscritos = [];
            if (is_array($asistencia)) {
                foreach ($asistencia as $index => $value):
                    $inscritos[] = (int) $index;
                endforeach;
            }

            // 3) Validación: al menos un inscrito seleccionado
            if (count($inscritos) == 0) {
                $msg = 500; // no hay seleccionados
            } else {
                // 4) Marcar asistencia para cada inscrito (delegado a la entidad)
                foreach ($inscritos as $id_ins):
                    $inscrito = new Inscrito($id_ins);
                    // setAsistencia debe encargarse de validar y persistir el cambio
                    $inscrito->setAsistencia(1);
                endforeach;

                $msg = 501; // operación OK
            }
        }

        // 5) Redirección: siempre volvemos a la ficha del curso (id leído arriba)
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => $id, 'v2' => -1, 'v3' => $msg));
    }

    public function certificadoAction() {
        // certificadoAction: subir o borrar certificado/diploma para un Inscrito
        // Flujo y responsabilidades:
        // - Si es POST: validar fichero (tamaño y extensión), renombrarlo de forma segura,
        //   moverlo al directorio de diplomas y actualizar la entidad Inscrito con el nombre
        // - Si no es POST: interpretar como petición de borrado del certificado
        // Códigos devolvidos en la redirección (v3):
        // - 548: subida correcta
        // - 549: extensión inválida o validación de fichero fallida
        // - 551: certificado borrado correctamente
        // - 550: error genérico de subida
        // Consideraciones de seguridad:
        // - Validar MIME-type además de la extensión.
        // - Evitar que el nombre original del fichero se use sin saneamiento (aquí se genera un nombre con timestamp).
        // - Establecer permisos correctos al fichero/directorio y evitar path traversal.

        $msg = 550; // valor por defecto: error subida
        if ($this->request->isPost()) {
            // 1) Leer ids necesarios desde POST
            $id_cur = (int) $this->request->getPost('id_cur');
            $id_ins = (int) $this->request->getPost('id_ins');
            $inscrito = new Inscrito($id_ins);
            $nombreDirectorio = $inscrito::FILE_DIRECTORY_DIPLOMA;

            $filter = new StripTags();

            // 2) Preparar adaptador y validadores de fichero
            $httpadapter = new \Zend\File\Transfer\Adapter\Http();
            // Límite: 10MB
            $filesize = new \Zend\Validator\File\Size(array('min' => '0kB', 'max' => '10MB'));
            // Extensiones permitidas: pdf, doc, docx
            $extension = new \Zend\Validator\File\Extension(array('extension' => array('pdf', 'doc', 'docx')));
            $httpadapter->setValidators(array($filesize, $extension));

            // 3) Obtener info del fichero subido
            $files = $httpadapter->getFileInfo('certificado');

            // 4) Validar y mover el fichero
            if ($httpadapter->isValid()) {
                // Construir un nombre único y seguro (timestamp + extensión)
                $fichero = $files['certificado']['name'];
                $ext = pathinfo($fichero, PATHINFO_EXTENSION);
                $fichero = time() . "." . $ext;

                // Asegurarse de que el directorio existe y tiene permisos restrictivos
                if (!file_exists($nombreDirectorio)) {
                    mkdir($nombreDirectorio, 0750, true);
                }

                // Renombrar y establecer destino
                $httpadapter->addFilter('filerename', ['target' => $nombreDirectorio . $fichero]);
                $httpadapter->setDestination($nombreDirectorio);

                // Mover el fichero subido
                if ($httpadapter->receive($files['certificado']['name'])) {
                    // Actualizar la entidad Inscrito con el nombre del fichero
                    $inscrito->setDiploma($fichero);
                    $msg = 548; // subida OK
                }
            } else {
                // Validación fallida (tamaño/extensión)
                $msg = 549;
            }
        } else {
            // 5) Si no es POST, se interpreta como borrado del certificado
            $id_cur = (int) $this->params()->fromRoute('v1', 0);
            $id_ins = (int) $this->params()->fromRoute('v2', 0);
            $inscrito = new Inscrito($id_ins);
            // removeDiploma() debe borrar el fichero del disco y limpiar la referencia en la entidad
            $inscrito->removeDiploma();
            $msg = 551; // borrado OK
        }

        // 6) Redirección final a la ficha del curso con el resultado
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => $id_cur, 'v2' => -1, 'v3' => $msg));
    }

    public function xaAction() {
        // xaAction: endpoint AJAX polivalente usado por select2/autocomplete
        // Entrada (ruta): v1 = ajax (int) que indica el tipo de búsqueda
        // Entrada (query): q = término de búsqueda o id (según tipo)
        // Salida: JSON con array de objetos {id, text}
        // Casos soportados (ajax):
        // 1: buscar cursos por nombre (q = término). Devuelve lista de cursos.
        // 2: buscar curso por id (q = id). Devuelve el curso si existe.
        // 3: buscar inscripciones por nombre/apellidos (q = término). Devuelve id_ins y texto.
        // 4: buscar inscripcion por id (q = id). Devuelve id_ins y texto.
        // 5: buscar categorías por nombre y filtrar por formacion/empleo (v2 indica filtro)
        // 6: buscar categoria por id (q = id)

        // NOTA DE SEGURIDAD IMPORTANTE:
        // - Esta acción usa accesos por cadena (p. ej. 'nombre LIKE "%term%"') lo cual
        //   es vulnerable a inyección si no se controla correctamente dentro de las capas
        //   de datos. Es recomendable usar consultas parametrizadas o al menos escapar/limpiar
        //   el término de búsqueda antes de concatenarlo.
        // - Actualmente se lee directamente $_GET['q'] en varios puntos; sería más limpio y
        //   consistente usar $this->params()->fromQuery('q').
        // - Cuidado con XSS: el texto devuelto se inyecta en la UI del cliente; asegúrate de
        //   escapar el contenido en la plantilla JavaScript al renderizar.

        $ajax = (int) $this->params()->fromRoute('v1', 0);
        $answer = [];

        if ($ajax == 1) {
            // 1) Buscar cursos por nombre: q = término (string)
            $term = $this->params()->fromQuery('q', '');
            $db = new Cursos();
            // ATENCIÓN: Utilidades::generaCondicion o la capa DB debería parametrizar esto
            $objects = $db->get('nombre LIKE "%' . $term . '%"', 'nombre');
            if (count($objects) > 0) {
                foreach ($objects as $object):
                    $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
                endforeach;
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 2) {
            // 2) Obtener curso por id: q = id (int)
            $term = (int) $this->params()->fromQuery('q', 0);
            $object = new Curso($term);
            if ($object->get('id') > 0) {
                $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
            } else {
                $answer[] = ["id" => "0", "text" => ""];
            }
        } else if ($ajax == 3) {
            // 3) Buscar inscripciones por nombre o apellidos: q = término
            $term = $this->params()->fromQuery('q', '');
            $db = new Inscripciones();
            $objects = $db->getInscripciones('nombre LIKE "%' . $term . '%" OR apellidos LIKE "%' . $term . '%"', ['nombre', 'apellidos']);
            if (count($objects) > 0) {
                foreach ($objects as $object):
                    // Nota: el código original concatena 'nombre' dos veces; aquí mantenemos
                    // el texto esperado como 'nombre apellidos'. Si existe un bug en la entidad
                    // o en los índices, deben corregirse ahí.
                    $answer[] = ["id" => $object['id_ins'], "text" => $object['nombre'] . ' ' . $object['apellidos']];
                endforeach;
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 4) {
            // 4) Obtener inscripción por id: q = id
            $term = (int) $this->params()->fromQuery('q', 0);
            $db = new Inscripcion();
            $objects = $db->getInscripciones('id_ins = ' . $term);
            if (count($objects) > 0) {
                $object = current($objects);
                $answer[] = ["id" => $object['id_ins'], "text" => $object['nombre'] . ' ' . $object['apellidos']];
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 5) {
            // 5) Buscar categorías por nombre, opcionalmente filtrando por formacion/empleo (v2)
            $term = $this->params()->fromQuery('q', '');
            $db = new Categorias();
            $formacion_empleo = (int) $this->params()->fromRoute('v2', 0);
            $where = 'nombre LIKE "%' . $term . '%"';
            if ($formacion_empleo == 1) {
                $where .= ' AND formacion = 1';
            } else if ($formacion_empleo == 2) {
                $where .= ' AND empleo = 1';
            }
            $objects = $db->get($where, 'nombre');
            if (count($objects) > 0) {
                foreach ($objects as $object):
                    $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
                endforeach;
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 6) {
            // 6) Obtener categoría por id
            $term = (int) $this->params()->fromQuery('q', 0);
            $object = new Categoria($term);
            if ($object->get('id') > 0) {
                $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
            } else {
                $answer[] = ["id" => "0", "text" => ""];
            }
        }

        // Devolver JSON
        return $this->getResponse()->setContent(Json::encode($answer));
    }

}
