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
	
    protected $_usuario;
    protected $_container;
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
	
    public function indexAction(){
        if($this->_usuario->get('rol') == '4dmin'){
            Utilidades::ejecutaCronJobs();
        }
        $this->layout()->title = 'Inicio';
        $num_autorizados_por_confirmar = 0;
        if($this->_usuario->get('rol') == '4dmin'){
            $where_inscripciones = 'estado = 1 OR estado = 2 OR estado = 6';
            $where_cursos = 'estado = 1';
            $where_inscritos = null;
            $where_empresas = 'estado = 0';
            $where_ofertas = 'estado = 0';
            $fecha_menos_7_dias = strtotime ( '-7 day' , strtotime ( date('Y-m-d H:i:s') ) ) ;
            $fecha_menos_7_dias = date ( 'Y-m-d  H:i:s' , $fecha_menos_7_dias );
            $where_candidaturas = 'candidaturasFecha >= "' .  $fecha_menos_7_dias . '"';

            $titulo_inscripciones = 'Inscripciones';
            $titulo_candidaturas = 'Últimas candidaturas';
            $titulo_cursos = 'Cursos abiertos';
            $titulo_ofertas = 'Ofertas por aprobar';
            $titulo_empresas = 'Empresas por aprobar';
            $titulo_inscritos = '';
            
            $db_usu = new \Application\Model\Entity\Usuarios();
            $num_autorizados_por_confirmar = $db_usu->num('autorizado = 2');

        }else if($this->_usuario->get('rol') == 'c4legiado'){
            $where_inscripciones = null;
            $where_cursos = 'estado = 1';
            $where_inscritos = '(id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ') AND cursoEstado != 3';
            $where_empresas = null;
            $where_ofertas = 'estado = 1';
            if($this->_usuario->get('autorizado') == 1){
                $where_candidaturas = '(id_usu = ' . $this->_usuario->get('id') . ' OR id_emp = ' . $this->_usuario->get('id_emp') . ') AND ofertasEstado != 4';
            }else{
                $where_candidaturas = 'id_usu = ' . $this->_usuario->get('id') . ' AND ofertasEstado != 4';
            }

            $titulo_inscripciones = '';
            $titulo_candidaturas = 'Candidaturas no descartados';
            $titulo_cursos = 'Cursos abiertos';
            $titulo_ofertas = 'Ofertas abiertas';
            $titulo_empresas = '';
            $titulo_inscritos = 'Inscripciones a cursos no terminados';
        }else if($this->_usuario->get('rol') == 'us4ario'){
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
         * Inscripciones
         * */

        if(isset($where_inscripciones)){
            $db_inscripciones = new Inscripciones();
            $orderby_inscripciones = 'id_ins DESC';
            // Leer de base de datos
            $inscripciones = $db_inscripciones->getInscripciones($where_inscripciones,$orderby_inscripciones,200);
            $num_inscripciones = $db_inscripciones->numInscripciones($where_inscripciones);
        }else{
            $inscripciones = [];
            $num_inscripciones = 0;
        }


        /*
         * Candidaturas
         * */
        if(isset($where_candidaturas)){
            $db_candidaturas = new Candidaturas();
            $orderby_candidaturas = 'usuariosNombre ASC';
            // Leer de base de datos
            $candidaturas = $db_candidaturas->getCandidaturas($where_candidaturas,$orderby_candidaturas);
            $num_candidaturas = $db_candidaturas->numCandidaturas($where_candidaturas);
        }else{
            $candidaturas = [];
            $num_candidaturas = 0;
        }

        /*
         * Cursos
         * */
        if(isset($where_cursos)){
            $db_cursos = new Cursos();
            $orderby_cursos = 'nombre ASC';

            // Leer de base de datos
            $cursos = $db_cursos->get($where_cursos,$orderby_cursos);
            $num_cursos = $db_cursos->num($where_cursos);
        }else{
            $cursos = [];
            $num_cursos = 0;
        }


        /*
         * Ofertas de empleo
         * */
        if(isset($where_ofertas)) {
            $db_ofertas = new Ofertas();
            $orderby_ofertas = 'titulo ASC';

            // Leer de base de datos
            $ofertas = $db_ofertas->getOfertas($where_ofertas, $orderby_ofertas);
            $num_ofertas = $db_ofertas->num($where_ofertas);
        }else{
            $ofertas = [];
            $num_ofertas = 0;
        }


        /*
         * Empresas
         * */
        if(isset($where_empresas)){
            $db_empresas = new Empresas();
            $orderby_empresas = 'empresasNombre ASC';

            // Leer de base de datos
            $empresas = $db_empresas->getEmpresas($where_empresas,$orderby_empresas);
            $num_empresas = $db_empresas->num($where_empresas);
        }else{
            $empresas = [];
            $num_empresas = 0;
        }


        /*
         * Inscripciones a cursos no terminados
         * */
        if(isset($where_inscritos)){
            $db_inscritos = new Inscripciones();
            $orderby_inscritos = 'usuariosNombre ASC';

            // Leer de base de datos
            $inscritos = $db_inscritos->getInscritos($where_inscritos,$orderby_inscritos);
            $num_inscritos = $db_inscritos->numInscritos($where_inscritos);
        }else{
            $inscritos = [];
            $num_inscritos = 0;
        }

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
        return new ViewModel($view);
    }

    public function documentosAction(){
        $this->layout()->title = 'Gestor documental';

        $msg_ok = null;
        $msg_error = null;

        $db_carpetas = new Carpetas();
        $permisos = $db_carpetas->getPermisosUsuarios('id_usu IS NULL OR id_usu = ' . $this->_usuario->get('id'), 'nombrea ASC');

        $carpetas = [];
        foreach($permisos as $per):
            if(isset($per['permiso']) && $per['permiso'] > 0){
                if($per['permiso'] == 1){
                    $arr_per = [
                        'pattern' => '/^\/$/',
                        'read'    => true,
                        'write'   => false,
                        'locked'  => true,
                        'hidden'  => false,
                    ];
                }else{
                    $arr_per = [
                        'read'    => true,
                        'write'   => true,
                        'locked'  => true,
                        'hidden'  => false,
                    ];
                }

                $carpeta = new Carpeta($per['id_car']);

                $carpetas[] = [
                    'id'       => $carpeta->getHashFolder(),
                    'nombre'   => $per['nombre'],
                    'permisos'  => $arr_per
                ];
            }
        endforeach;

        if(count($carpetas) > 0){
            $this->layout()->carpetas_permisos = urlencode(serialize($carpetas));
        }else{
            $msg_error = 'No tiene permisos para este apartado.';
        }

        $view = array(
            'usuario'   =>  $this->_usuario,
            'ko'        =>  $msg_error,
        );
        return new ViewModel($view);
    }


    public function errorAction(){
        $this->layout()->title = 'Error de ejecución';
        $view = array(
            'usuario'   =>  $this->_usuario,
        );
        return new ViewModel($view);
    }
	
    public function permisoAction(){
        $this->layout()->title = 'Error de permisos';
        $view = array(
            'usuario'   =>  $this->_usuario,
        );
        return new ViewModel($view);
    }
    
    public function devAction(){
        $tarea = (int) $this->params()->fromRoute('v1', 0);
        if($tarea == 1){
            $db_usu = new \Application\Model\Entity\Usuarios();
            // $db_usu->rellenaNumColegiado();
        }else if($tarea == 2){
            $db = new Empresas();
            // $db->eliminarDuplicados();
        }
        if($tarea){
            $msg = 'Script finalizado';
        }else{
            $msg = 'Debe indicar una tarea';
        }
        die($msg);
    }
}
