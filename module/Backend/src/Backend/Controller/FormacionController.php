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
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'cursos', 'v1' => 1));
    }

    public function cursosAction() {
        $this->layout()->title = 'Cursos';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if ($idm == 525) {
            $msg_ok = 'El curso ha sido borrado correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar el curso porque tiene otros registros relacionadas.';
        }

        $db_cursos = new Cursos();
        $orderby = 'comienzo DESC';
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['nombre'] = $filter->filter($this->request->getPost('nombre'));
            $data['tipo'] = (int) $this->request->getPost('tipo');
            $data['estado'] = (int) $this->request->getPost('estado');
            $data['comienzoDesde'] = $filter->filter($this->request->getPost('comienzoDesde'));
            $data['comienzoHasta'] = $filter->filter($this->request->getPost('comienzoHasta'));
            $data['finDesde'] = $filter->filter($this->request->getPost('finDesde'));
            $data['finHasta'] = $filter->filter($this->request->getPost('finHasta'));
            $data['id_cat'] = (int) $this->request->getPost('id_cat');

            $this->_container->curs_buscador = $data;
            $this->_container->curs_page = 0;
        } else {
            // Eliminar filtros de búsqueda
            if ($idm == 114) {
                if (isset($this->_container->curs_buscador)) {
                    unset($this->_container->curs_buscador);
                    $this->_container->curs_page = 0;
                }
            }
        }
        // Paginación
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
        $offset = 50 * ($page - 1);
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if (isset($this->_container->curs_buscador)) {
            $where = Utilidades::generaCondicion('cursos', $this->_container->curs_buscador);
            $this->_container->curs_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->curs_buscador;
        } else {
            $buscador = array('id_cur' => 0, 'nombre' => null, 'tipo' => -1, 'estado' => -1, 'comienzoDesde' => null, 'comienzoHasta' => null, 'finDesde' => null, 'finHasta' => null, 'id_cat' => null);
            $where = null;
        }
        // Leer de base de datos
        $cursos = $db_cursos->get($where, $orderby, 50, $offset);
        $num = $db_cursos->num($where);
        if ($num == 0) {
            if (isset($this->_container->curs_buscador)) {
                $msg_error = 'No hay ning&uacute;n curso con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ning&uacute;n curso guardado.';
            }
        }
        // Preparar datos para la vista
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
        $this->layout()->title = 'Nuevo | Curso';
        $idm = (int) $this->params()->fromRoute('v3', 0);
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';

        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
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

            $curso = new Curso(0);
            $algun_valor_vacio = $curso->set($data);
            if ($algun_valor_vacio > 0) {
                $msg_error = 'No puede dejar ning&uacute;n valor obligatorio vac&iacute;o.';
                $id = $data['id_cur'];
            } else {
                $id = $curso->save();
                if ($data['id_cur'] == 0) {
                    $msg_ok = 'El curso ha sido creado correctamente.';
                } else {
                    $msg_ok = 'El curso ha sido actualizado correctamente.';
                }
            }
        } else {
            $id = (int) $this->params()->fromRoute('v1', 0);
            $curso = new Curso($id);
        }

        if ($id > 0) {
            $this->layout()->title = $curso->get('nombre') . ' | Curso';
            // Inscripciones relacionadas
            $pagi = (int) $this->params()->fromRoute('v2', 0);
            /*if ($pagi == -1) {
                if (isset($this->_container->cins_page)) {
                    $pagi = $this->_container->cins_page;
                } else {
                    $pagi = 0;
                    $this->_container->cins_page = $pagi;
                }
            } else {
                $this->_container->cins_page = $pagi;
                $tab = 'inscripciones';
            }*/
            if ($pagi <= 0) {
                $pagi = 1;
            }else{
                $tab = 'inscripciones';
            }
            $offseti = 50 * ($pagi - 1);
            $db_ins = new Inscripciones();
            $inscripciones = $db_ins->getInscritos('id_cur = ' . $id, ['usuariosNombre ASC', 'usuariosApellidos ASC'], 50, $offseti);
            $numi = $db_ins->num('id_cur = ' . $id);
            $inscripcionesIncompletas = $db_ins->num('id_cur = ' . $id.' AND estado = 0');
        } else {
            $inscripciones = [];
            $numi = 0;
            $pagi = 0;
        }

        if ($idm == 100) {
            $msg_ok = 'El curso ha sido sincronizado correctamente con wordpress.';
        } else if ($idm == 101) {
            $msg_error = 'El curso no se ha podido sincronizar por un error en el servidor de destino.';
            if(isset($this->_container->respuestaSincronizacionCurso) && is_array($this->_container->respuestaSincronizacionCurso)){
                foreach($this->_container->respuestaSincronizacionCurso as $ke1 => $va1):
                    if(is_array($va1)){
                        foreach($va1 as $ke2 => $va2):
                            $msg_error .= '<br/>- <strong>'.$ke2.'</strong>: '.$va2;
                        endforeach;
                    }else{
                        $msg_error .= '<br/>- <strong>'.$ke1.'</strong>: '.$va1;
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
        $id = (int) $this->params()->fromRoute('v1', 0);
        $db_ins = new Inscripciones();
        $inscripciones = $db_ins->get('id_cur = ' . $id.' AND estado = 0');
        foreach($inscripciones as $inscripcion):
            Notificaciones::enviarInscripcionIncompleta($inscripcion, $this->sendMail);
        endforeach;
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => (int)$id, 'v2' => 0,'v3' => 602));
    }

    public function generacerfificadosAction(){
        if($this->request->isPost()) {
            $id_cur = (int)$this->request->getPost('id_cur');

            $id_ui_generar = $this->request->getPost('id_ui_generar');
            if(empty($id_ui_generar)){
                $id_ui_generar = [];
            }
            $id_ui_enviar = $this->request->getPost('id_ui_enviar');
            if(empty($id_ui_enviar)){
                $id_ui_enviar = [];
            }
            foreach($id_ui_generar as $ui => $value):
                $inscrito = new Inscrito($ui);
                $pdf = Imprimir::certificadoPdf($inscrito, true);
                $inscrito->setDiploma($pdf[1]);
            endforeach;

            $group_inscritos = [];
            foreach($id_ui_enviar as $ui => $value):
                $inscrito = new Inscrito($ui);
                if(!isset($group_inscritos[$inscrito->get('id_ins')])){
                    $group_inscritos[$inscrito->get('id_ins')] = [];
                }
                $group_inscritos[$inscrito->get('id_ins')][] = $inscrito;
                if(empty($inscrito->get('diploma')) || !file_exists(\Application\Model\Entity\Inscrito::FILE_DIRECTORY_DIPLOMA . $inscrito->get('diploma'))) {
                    $pdf = Imprimir::certificadoPdf($inscrito, true);
                    $inscrito->setDiploma($pdf[1]);
                    // echo $ui, '<br />';
                }
            endforeach;
            
            foreach($group_inscritos as $id_ins => $ids_ui):
                $dev_mail = Notificaciones::enviarCertificaciones(new Inscripcion($id_ins), $this->sendMail, $ids_ui);
                $fecha_envio = date('d-m-Y H:i:s');
                foreach($ids_ui as $inscrito):
                    if($dev_mail[$inscrito->get('id')]['status']){
                        $inscrito->setAttribute('envio_diploma', $fecha_envio);
                        $inscrito->save();
                    }
                endforeach;
            endforeach;
            return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => (int)$id_cur, 'v2' => 0, 'v3' => 552));
        }
    }

    public function borrarcursoAction() {
        $id = (int) $this->params()->fromRoute('v1', 0);
        $object = new Curso($id);
        $post_id = $object->get('post_id');
        if ($post_id > 0) {
            $data = $object->getMappingWP('remove');
        }

        $ok = $object->remove();

        if ($post_id > 0 && $ok == 525) {
            $data = $object->getMappingWP('remove');
            $responseData = $this->apiClientRest->send($post_id, $data, 'cursos', 'create');
        }

        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'cursos', 'v1' => (int) $this->_container->curs_page, 'v2' => $ok));
    }

    public function sincronizacursoAction() {
        $id = (int) $this->params()->fromRoute('v1', 0);
        $curso = new Curso($id);

        $categoria = $curso->get('categoria');

        if ($categoria->get('taxonomy_id') == 0) {
            $dataCategory = $categoria->getMappingWP('create');
            $responseDataCategory = $this->apiClientRest->send($categoria->get('taxonomy_id'), $dataCategory, 'cursos-categorias', 'create');
            if (isset($responseDataCategory['id']) || $responseDataCategory['data']['term_id']) {
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

        $data = $curso->getMappingWP('create');
        $responseData = $this->apiClientRest->send($curso->get('post_id'), $data, 'cursos', 'create');
        $this->_container->respuestaSincronizacionCurso = $responseData;
        $updateData = [];
        $updateData['sincro'] = date('Y-m-d H:i:s');

        if (isset($responseData['id']) && (int) $curso->get('post_id') == 0) {
            $updateData['post_id'] = $responseData['id'];
            $updateData['publicacion'] = date('Y-m-d');
            $resultado = 100;
        } else {
            $resultado = 101;
        }

        $db_cursos = new Cursos();
        $db_cursos->update($updateData, 'id_cur = ' . $curso->get('id'));

        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => (int) $curso->get('id'), 'v2' => -1, 'v3' => $resultado));
    }

    public function xlscursosAction() {
        $where = Utilidades::generaCondicion('cursos', $this->_container->curs_buscador);
        $db = new Cursos();
        $objects = $db->get($where, 'nombre');
        $objPHPExcel = Exportar::cursos($objects);

        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }

    public function inscripcionesAction() {
        $this->layout()->title = 'Inscripciones';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if ($idm == 525) {
            $msg_ok = 'La inscripción ha sido borrada correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar la inscripción porque tiene otras entidades relacionadas.';
        }

        $db_inscripciones = new Inscripciones();
        $orderby = 'id_ins DESC';
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_ins'] = (int) $this->request->getPost('id_ins');
            $data['id_usu'] = (int) $this->request->getPost('id_usu');
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['tipo'] = (int) $this->request->getPost('tipo');
            $data['estado'] = (int) $this->request->getPost('estado');
            $data['pago'] = (int) $this->request->getPost('pago');

            $this->_container->insc_buscador = $data;
            $this->_container->insc_page = 0;
        } else {
            // Eliminar filtros de búsqueda
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
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if (isset($this->_container->insc_buscador)) {
            $where = Utilidades::generaCondicion('inscripciones', $this->_container->insc_buscador);
            $this->_container->insc_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->insc_buscador;
        } else {
            $buscador = array('id_ins' => 0, 'id_usu' => null, 'id_cur' => null, 'fechaDesde' => null, 'fechaHasta' => null, 'tipo' => -1, 'estado' => -1, 'pago' => -1);
            $where = null;
        }

        // Leer de base de datos
        $inscripciones = $db_inscripciones->getInscripciones($where, $orderby, 50, $offset);
        $num = $db_inscripciones->numInscripciones($where);
        if ($num == 0) {
            if (isset($this->_container->insc_buscador)) {
                $msg_error = 'No hay ninguna inscripci&oacute;n con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ninguna inscripci&oacute;n guardada.';
            }
        }
        // Preparar datos para la vista
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
        $this->layout()->title = 'Nueva | Inscripción';
        $msg_ok = null;
        $msg_error = null;
        $tab = 'default';

        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
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
            $id = (int) $this->params()->fromRoute('v1', 0);
            $inscripcion = new Inscripcion($id);
        }
        $idm = (int) $this->params()->fromRoute('v3', 0);
        if ($idm == 2 && $this->_usuario->get('rol') == '4dmin') {
            $inscripcion->setEstado(2);
            $dev_mail = Notificaciones::enviarCobroInscripcion($inscripcion, $this->sendMail);
            if ($dev_mail['status']) {
                $msg_ok = 'La notificación del pago ha sido enviada correctamente.';
            } else {
                $msg_error = 'La notificación del pago no ha podido ser enviada. Por favor, compruebe el e-mail del usuario';
            }
        } else if ($idm == 3 && $this->_usuario->get('rol') == '4dmin') {
            $msg_ok = 'La inscripción ha sido aceptada.';
            $inscripcion->setEstado(3);
            $dev_mail = Notificaciones::enviarConfirmacionInscripcion($inscripcion, $this->sendMail);
            if ($dev_mail['status']) {
                $msg_ok .= ' Se ha notificado al usuario.';
            } else {
                $msg_error = 'La notificación de confirmación de la inscripción no ha podido ser enviada. Por favor, compruebe el e-mail del usuario';
            }
        } else if ($idm == 4 && $this->_usuario->get('rol') == '4dmin') {
            $msg_ok = 'La inscripción ha sido rechazada.';
            $inscripcion->setEstado(4);
        } else if ($idm == 5 && $this->_usuario->get('rol') == '4dmin') {
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
        }else if($idm == 548){
            $msg_ok = 'El justificante de pago ha sido subido correctamente.';
            $tab = 'default';
        }else if($idm == 549){
            $msg_error = 'El justificante de pago no tiene une extensión válida.';
            $tab = 'default';
        }else if($idm == 550){
            $msg_error = 'Ha ocurrido un error al subir el justificante de pago.';
            $tab = 'default';
        }else if($idm == 551){
            $msg_ok = 'El justificante de pago ha sido borrado correctamente.';
            $tab = 'default';
        }

        if ($id > 0) {
            $this->layout()->title = 'Inscripción ' . str_pad($inscripcion->get('id'), 5, '0', STR_PAD_LEFT);
            // Inscritos relacionadas
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

            // Participantes relacionadas
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

            /*
             * Quitamos los participantes en el curso para no crear otra inscipción con los mismos menores y el mismo curso
             * */
            $where = 'id_usu = ' . $inscripcion->get('id_usu');
            foreach ($participantes as $participante):
                $where .= ' AND id_men != ' . $participante['id_men'];
            endforeach;


            $db_menores = new Menores();
            $menores = $db_menores->get($where, ['nombre asc', 'apellidos asc']);


            /*
             * Quitamos los inscritos en el curso para no crear otra inscipción con los mismos usuarios y el mismo curso
             * */
            $where = 'id_emp = ' . $empresa->get('id');
            foreach ($inscritos as $inscrito):
                $where .= ' AND id_usu != ' . $inscrito['id_usu'];
            endforeach;
            $db_trabajadores = new Usuarios();
            $trabajadores = $db_trabajadores->get($where, ['nombre asc', 'apellidos asc']);
        }else {
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
        $id = (int) $this->params()->fromRoute('v1', 0);
        $urlRedirect = (int) $this->params()->fromRoute('v2', 0);
        $object = new Inscripcion($id);
        $ok = $object->remove();
        if ($urlRedirect > 0) {
            return $this->redirect()->toRoute('backend/default', array('controller' => 'usuarios', 'action' => 'ficha', 'v1' => (int) $object->get('id_usu'), 'v2' => 0, 'v3' => 0, 'v4' => 0, 'v5' => 0, 'v6' => $ok));
        } else {
            return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscripciones', 'v1' => (int) $this->_container->insc_page, 'v2' => $ok));
        }
    }

    public function inscripcionjustificanteAction(){
        $msg = 550;
        if($this->request->isPost()) {
            $id_ins = (int)$this->request->getPost('id_ins');
            $inscripcion = new Inscripcion($id_ins);
            $nombreDirectorio = $inscripcion::FILE_DIRECTORY_JUSTIFICANTE;

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
                    $msg = 548;
                }
            }else{
                $msg = 549;
            }
        }else{
            $id_ins = (int)$this->params()->fromRoute('v1',0);
            $inscripcion = new Inscripcion($id_ins);
            $inscripcion->removeJustificante();
            $msg = 551;

        }
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscripcion','v1' => $id_ins,'v2' => -1,'v3' => $msg));
    }

    public function xlsinscripcionesAction() {
        $where = Utilidades::generaCondicion('inscripciones', $this->_container->insc_buscador);
        $db = new Inscripciones();
        $objects = $db->getInscripciones($where, ['usuariosNombre', 'usuariosApellidos']);
        $objPHPExcel = Exportar::inscripciones($objects);

        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }

    public function xlsinscritosAction() {
        $id_cur = (int) $this->params()->fromRoute('v1', 0);
        if ($id_cur) {
            $where = 'id_cur = ' . $id_cur;
        } else {
            $where = Utilidades::generaCondicion('inscritos', $this->_container->insc_buscador);
        }
        $db = new Inscripciones();
        $objects = $db->getInscritos($where, ['usuariosNombre', 'usuariosApellidos']);
        $objPHPExcel = Exportar::inscritos($objects);

        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }

    public function xlsparticipantesAction() {
        $where = Utilidades::generaCondicion('participantes', $this->_container->par_buscador);
        $db = new Inscripciones();
        $objects = $db->getParticipantes($where, ['usuariosNombre', 'usuariosApellidos']);
        $objPHPExcel = Exportar::participantes($objects);

        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=exportacion_" . date('d-m-Y') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        die('Excel generado');
        exit;
    }

    public function categoriasAction() {
        $this->layout()->title = 'Categorias';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if ($idm == 525) {
            $msg_ok = 'La categoría ha sido borrada correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar la categoría porque tiene cursos relacionados.';
        }

        $db = new Categorias();
        $orderby = 'nombre ASC';
        if ($this->request->isPost()) {
            $data = [];
            $boton = $this->request->getPost('boton');
            if ($boton == 'buscar') {
                $data['id_cat'] = (int) $this->request->getPost('id_cat');
                $this->_container->cate_buscador = $data;
            } else if ($boton == 'guardar-todos') {
                $id_cats = $this->request->getPost('id_cat');
                $nombres = $this->request->getPost('nombre');
                $taxonomy_id = $this->request->getPost('taxonomy_id');
                $formacion = $this->request->getPost('formacion');
                $empleo = $this->request->getPost('empleo');
                foreach ($id_cats as $i => $id_cat):
                    if (!empty($nombres[$i])) {
                        $data = [
                            'id_cat' => $id_cat,
                            'nombre' => $nombres[$i],
                            'taxonomy_id' => (int) $taxonomy_id[$i],
                            'formacion' => (int) $formacion[$i],
                            'empleo' => (int) $empleo[$i]
                        ];
                        $object = new Categoria(0);
                        $object->set($data);
                        $object->save();
                    }
                endforeach;
                $msg_ok = 'Las categorías han sido actualizadas correctamente.';
            } else {
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
            // Eliminar filtros de búsqueda
            if ($idm == 114) {
                if (isset($this->_container->cate_buscador)) {
                    unset($this->_container->cate_buscador);
                    $this->_container->cate_page = 0;
                }
            }
        }
        // Paginación
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
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if (isset($this->_container->cate_buscador)) {
            $where = Utilidades::generaCondicion('categorias', $this->_container->cate_buscador);
            $this->_container->cate_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->cate_buscador;
        } else {
            $buscador = array('id_cat' => 0);
            $where = null;
        }
        // Leer de base de datos
        $objects = $db->get($where, $orderby, 50, $offset);
        $num = $db->num($where);
        if ($num == 0) {
            if (isset($this->_container->cate_buscador)) {
                $msg_ko = 'No hay ninguna categoría con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_ko = 'No hay ninguna categoría guardada.';
            }
        }
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
        $id = (int) $this->params()->fromRoute('v1', 0);
        $object = new Categoria($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'categorias', 'v1' => (int) $this->_container->cat_page, 'v2' => $ok));
    }

    public function inscritosAction() {
        if ($this->_usuario->get('rol') == '4dmin') {
            $this->layout()->title = 'Inscritos';
        } else {
            $this->layout()->title = 'Mis inscripciones';
        }

        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if ($idm == 525) {
            $msg_ok = 'El registro ha sido borrado correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar el registro porque tiene otras entidades relacionadas.';
        }

        $db_inscritos = new Inscripciones();
        $orderby = 'usuariosNombre ASC';
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_ui'] = (int) $this->request->getPost('id_ui');
            $data['id_usu'] = (int) $this->request->getPost('id_usu');
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['tipo'] = (int) $this->request->getPost('tipo');
            $data['estado'] = (int) $this->request->getPost('estado');

            $this->_container->inscr_buscador = $data;
            $this->_container->inscr_page = 0;
        } else {
            // Eliminar filtros de búsqueda
            if ($idm == 114) {
                if (isset($this->_container->inscr_buscador)) {
                    unset($this->_container->inscr_buscador);
                    $this->_container->inscr_page = 0;
                }
            }
        }
        // Paginación
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
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if (isset($this->_container->inscr_buscador)) {
            $where = Utilidades::generaCondicion('inscritos', $this->_container->inscr_buscador);
            $this->_container->inscr_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->inscr_buscador;
        } else {
            $buscador = array('id_ui' => 0, 'id_usu' => null, 'id_cur' => null, 'fechaDesde' => null, 'fechaHasta' => null, 'tipo' => -1, 'estado' => -1);
            $where = null;
        }


        if ($this->_usuario->get('rol') != '4dmin') {
            (isset($where)) ? ($where .= ' AND (id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ')') : ($where = '(id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ')');
        }

        // Leer de base de datos
        $inscritos = $db_inscritos->getInscritos($where, $orderby, 50, $offset);
        $numin = $db_inscritos->numInscritos($where);
        if ($this->_usuario->get('rol') != '4dmin') {
            $participantes = $db_inscritos->getParticipantes($where, $orderby, 50, $offset);
            $nump = $db_inscritos->numParticipantes($where);
            $numin += $nump;
            $inscritos = array_merge($inscritos, $participantes);
        }
        if ($numin == 0) {
            if (isset($this->_container->inscr_buscador)) {
                $msg_error = 'No hay ningun registro con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ningún registro guardado.';
            }
        }
        // Preparar datos para la vista
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
        $this->layout()->title = 'Participantes';
        $idm = (int) $this->params()->fromRoute('v2', 0);
        $msg_ok = null;
        $msg_error = null;
        // Códigos de mensajes de otros actions
        if ($idm == 525) {
            $msg_ok = 'El registro ha sido borrado correctamente.';
        } else if ($idm == 536) {
            $msg_error = 'No se ha podido borrar el registro porque tiene otras entidades relacionadas.';
        }

        $db_participantes = new Inscripciones();
        $orderby = 'usuariosNombre ASC';
        if ($this->request->isPost()) {
            $data = [];
            $filter = new StripTags();
            // Parámetros de búsqueda
            $data['id_par'] = (int) $this->request->getPost('id_par');
            $data['id_usu'] = (int) $this->request->getPost('id_usu');
            $data['id_cur'] = (int) $this->request->getPost('id_cur');
            $data['fechaDesde'] = $filter->filter($this->request->getPost('fechaDesde'));
            $data['fechaHasta'] = $filter->filter($this->request->getPost('fechaHasta'));
            $data['menorNombre'] = $filter->filter($this->request->getPost('menorNombre'));
            $data['estado'] = (int) $this->request->getPost('estado');

            $this->_container->par_buscador = $data;
            $this->_container->par_page = 0;
        } else {
            // Eliminar filtros de búsqueda
            if ($idm == 114) {
                if (isset($this->_container->par_buscador)) {
                    unset($this->_container->par_buscador);
                    $this->_container->par_page = 0;
                }
            }
        }
        // Paginación
        $pagp = (int) $this->params()->fromRoute('v1', -1);
        if ($pagp == -1) {
            if (isset($this->_container->par_page)) {
                $pagin = $this->_container->par_page;
            } else {
                $pagin = 1;
                $this->_container->par_page = $pagp;
            }
        } else {
            $this->_container->par_page = $pagp;
        }
        if ($pagp <= 0) {
            $pagp = 1;
        }
        $offset = 50 * ($pagp - 1);
        // Construcción de la condición ($where) según los parámetros de búsqueda
        if (isset($this->_container->par_buscador)) {
            $where = Utilidades::generaCondicion('participantes', $this->_container->par_buscador);
            $this->_container->par_buscador['where'] = $where . ' ///////// ' . $orderby;
            $buscador = $this->_container->par_buscador;
        } else {
            $buscador = array('id_par' => 0, 'id_usu' => null, 'id_cur' => null, 'fechaDesde' => null, 'fechaHasta' => null, 'menorNombre' => null, 'estado' => -1);
            $where = null;
        }
        // Leer de base de datos
        $participantes = $db_participantes->getParticipantes($where, $orderby, 50, $offset);
        $nump = $db_participantes->numParticipantes($where);
        if ($nump == 0) {
            if (isset($this->_container->par_buscador)) {
                $msg_error = 'No hay ningun registro con las caracter&iacute;sticas seleccionadas.';
            } else {
                $msg_error = 'No hay ningún registro guardado.';
            }
        }
        // Preparar datos para la vista
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

    public function asistentesAction() {
        
    }

    public function borrarinscritoAction() {
        $id = (int) $this->params()->fromRoute('v1', 0);
        $object = new Inscrito($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscritos', 'v1' => (int) $this->_container->inscr_page, 'v2' => $ok));
    }

    public function borrarparticipanteAction() {
        $id = (int) $this->params()->fromRoute('v1', 0);
        $object = new Participante($id);
        $ok = $object->remove();
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'participantes', 'v1' => (int) $this->_container->par_page, 'v2' => $ok));
    }

    public function enviaremailpagoAction() {
        $id = (int) $this->params()->fromRoute('v1', 0);
        $inscripcion = new Inscripcion($id);
        $usuario = $inscripcion->get('creador');
        $curso = $inscripcion->get('curso');
        if ($inscripcion->get('id') == 0) {
            $msg = 400;
        } else if ($usuario->get('id') == 0) {
            $msg = 401;
        } else if ($curso->get('id') == 0) {
            $msg = 402;
        } else if ($inscripcion->get('importe') > 0) {
            $basePath = $this->getRequest()->getBasePath();
            $uri = new \Zend\Uri\Uri($this->getRequest()->getUri());
            $uri->setPath($basePath);
            $uri->setQuery(array());
            $uri->setFragment('');
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost() . '' . $uri->getPath();

            $message = Utilidades::getMessageEmailPayment();

            $tipoCurso = Utilidades::systemOptions('cursos', 'tipo', 1)[(int) $curso->get('tipo')];

            $message['subject'] = str_replace([
                '[tipo-curso]',
                '[nombre-curso]',
                    ], [
                $tipoCurso,
                $curso->get('nombre'),
                    ]
                    , $message['subject']
            );

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

            $mails = [];
            $mails[] = [
                'mail' => $usuario->get('email'),
                'name' => $usuario->get('nombre') . ' ' . $usuario->get('apellidos'),
                'to_type' => 'default'
            ];

            $dev_mail = $this->sendMail->sendMail($mails, $message['subject'], $message['message']);

            if ($dev_mail['status']) {
                $msg = 200;
            } else {
                $msg = 404;
            }
        } else {
            $msg = 403;
        }

        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscripcion', 'v1' => (int) $inscripcion->get('id'), 'v2' => -1, 'v3' => $msg));
    }

    public function agregaparticipantesAction() {
        if ($this->request->isPost()) {
            $filter = new StripTags();

            $id = (int) $this->request->getPost('id_ins');
            $inscripcion = new Inscripcion($id);

            $id_usu = $this->request->getPost('id_usu');
            $trabajadores = [];
            if (is_array($id_usu)) {
                foreach ($id_usu as $index => $value):
                    $trabajadores[] = $index;
                endforeach;
            }
            if (count($trabajadores) == 0) {
                $msg = 400;
            } else {
                $curso = $inscripcion->get('curso');
                foreach ($trabajadores as $id_tra):
                    $usuario = new Usuario($id_tra);
                    $importe = 0;
                    if ($usuario->get('sitcol') == 0) {
                        $importe = $curso->get('precio_col');
                    } else {
                        $importe = $curso->get('precio_otr');
                    }

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
                    $inscrito = new Inscrito(0);
                    $algun_valor_vacio = $inscrito->set($data_inscrito);
                    if ($algun_valor_vacio == 0) {
                        $id_ui = $inscrito->save();
                    }
                endforeach;

                $inscripcion->revisaImporte();

                $msg = 401;
            }
        }

        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscripcion', 'v1' => $inscripcion->get('id'), 'v2' => -1, 'v3' => $msg));
    }

    public function agregamenoresAction() {
        if ($this->request->isPost()) {
            $filter = new StripTags();

            $id = (int) $this->request->getPost('id_ins');
            $inscripcion = new Inscripcion($id);

            $id_men = $this->request->getPost('id_men');
            $trabajadores = [];
            $menores = [];
            if (is_array($id_men)) {
                foreach ($id_men as $index => $value):
                    $menores[] = $index;
                endforeach;
            }
            if (count($menores) == 0) {
                $msg = 500;
            } else {
                $curso = $inscripcion->get('curso');
                foreach ($menores as $id_men):
                    $menor = new Menor($id_men);

                    $importe = $curso->get('precio_otr');

                    $data_participante = [];
                    $data_participante['id_par'] = 0;
                    $data_participante['id_ins'] = $inscripcion->get('id');
                    $data_participante['id_men'] = $id_men;
                    $data_participante['fecha'] = date('d-m-Y');
                    $data_participante['importe'] = $importe;

                    $participante = new Participante(0);
                    $algun_valor_vacio = $participante->set($data_participante);
                    if ($algun_valor_vacio == 0) {
                        $id_par = $participante->save();
                    }
                endforeach;

                $inscripcion->revisaImporte();

                $msg = 501;
            }
        }

        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'inscripcion', 'v1' => $inscripcion->get('id'), 'v2' => -1, 'v3' => $msg));
    }

    public function asistenciaAction() {
        if ($this->request->isPost()) {
            $filter = new StripTags();

            $id = (int) $this->request->getPost('id_cur');

            $asistencia = $this->request->getPost('asistencia');
            $inscritos = [];
            if (is_array($asistencia)) {
                foreach ($asistencia as $index => $value):
                    $inscritos[] = $index;
                endforeach;
            }
            if (count($inscritos) == 0) {
                $msg = 500;
            } else {
                foreach ($inscritos as $id_ins):
                    $inscrito = new Inscrito($id_ins);
                    $inscrito->setAsistencia(1);
                endforeach;

                $msg = 501;
            }
        }

        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => $id, 'v2' => -1, 'v3' => $msg));
    }

    public function certificadoAction() {
        $msg = 550;
        if ($this->request->isPost()) {
            $id_cur = (int) $this->request->getPost('id_cur');
            $id_ins = (int) $this->request->getPost('id_ins');
            $inscrito = new Inscrito($id_ins);
            $nombreDirectorio = $inscrito::FILE_DIRECTORY_DIPLOMA;

            $data = [];
            $filter = new StripTags();

            $httpadapter = new \Zend\File\Transfer\Adapter\Http();
            $filesize = new \Zend\Validator\File\Size(array('min' => '0kB', 'max' => '10MB')); //1KB
            $extension = new \Zend\Validator\File\Extension(array('extension' => array('pdf', 'doc', 'docx')));
            $httpadapter->setValidators(array($filesize, $extension));

            $files = $httpadapter->getFileInfo('certificado');
            if ($httpadapter->isValid()) {
                $fichero = $files['certificado']['name'];
                $ext = pathinfo($fichero, PATHINFO_EXTENSION);
                $fichero = time() . "." . $ext;
                $httpadapter->addFilter('filerename', ['target' => $nombreDirectorio . $fichero]);
                $httpadapter->setDestination($nombreDirectorio);

                if ($httpadapter->receive($files['certificado']['name'])) {
                    $inscrito->setDiploma($fichero);
                    $msg = 548;
                }
            } else {
                $msg = 549;
            }
        } else {
            $id_cur = (int) $this->params()->fromRoute('v1', 0);
            $id_ins = (int) $this->params()->fromRoute('v2', 0);
            $inscrito = new Inscrito($id_ins);
            $inscrito->removeDiploma();
            $msg = 551;
        }
        return $this->redirect()->toRoute('backend/default', array('controller' => 'formacion', 'action' => 'curso', 'v1' => $id_cur, 'v2' => -1, 'v3' => $msg));
    }

    public function xaAction() {
        $ajax = (int) $this->params()->fromRoute('v1', 0);
        $answer = [];
        if ($ajax == 1) {
            $term = $_GET['q'];
            $db = new Cursos();
            $objects = $db->get('nombre LIKE "%' . $term . '%"', 'nombre');
            if (count($objects) > 0) {
                foreach ($objects as $object):
                    $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
                endforeach;
            }else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 2) {
            $term = (int) $_GET['q'];
            $object = new Curso($term);
            if ($object->get('id') > 0) {
                $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
            } else {
                $answer[] = ["id" => "0", "text" => ""];
            }
        } else if ($ajax == 3) {
            $term = $_GET['q'];
            $db = new Inscripciones();
            $objects = $db->getInscripciones('nombre LIKE "%' . $term . '%" OR apellidos LIKE "%' . $term . '%"', ['nombre', 'apellidos']);
            if (count($objects) > 0) {
                foreach ($objects as $object):
                    $answer[] = ["id" => $object['id_ins'], "text" => $object['nombre'] . ' ' . $object['nombre']];
                endforeach;
            }else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 4) {
            $term = (int) $_GET['q'];
            $db = new Inscripcion();
            $objects = $db->getInscripciones('id_ins = ' . $term);
            if (count($objects) > 0) {
                $object = current($objects);
                $answer[] = ["id" => $object['id_ins'], "text" => $object['nombre'] . ' ' . $object['nombre']];
            } else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 5) {
            $term = $_GET['q'];
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
            }else {
                $answer[] = ["id" => "0", "text" => "No existen resultados."];
            }
        } else if ($ajax == 6) {
            $term = (int) $_GET['q'];
            $object = new Categoria($term);
            if ($object->get('id') > 0) {
                $answer[] = ["id" => $object->get('id'), "text" => $object->get('nombre')];
            } else {
                $answer[] = ["id" => "0", "text" => ""];
            }
        }
        return $this->getResponse()->setContent(Json::encode($answer));
    }

}
