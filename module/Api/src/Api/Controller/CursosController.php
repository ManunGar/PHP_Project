<?php

/*
 * Servicio API para Comanies
 * */

namespace Api\Controller;

use Api\Controller\AbstractRestfulJsonController;
use Zend\View\Model\JsonModel;
use Application\Model\Entity\Cursos;
use Application\Model\Utility\Utilidades;

class CursosController extends AbstractRestfulJsonController
{

    private $configApi;

    public function __construct($config_api)
    {
        $this->configApi = $config_api;
    }

    /*
     * Action usado por método GET recupera un listado sin poner el identificador en la ruta
     * */
    public function getList(){
        if(!$this->accessApi()->authorizationHeader($this->getRequest(), $this->configApi)){
            return new JsonModel(['data' => ['code' => '403', 'msg' => 'El usuario no tiene permisos.']]);
        }

        $db_cursos = new Cursos();
        $where = null;
        if($this->params()->fromQuery('activo') == 1){
            $where = 'estado = 1 OR estado = 2 OR estado = 4 OR (estado = 3 AND YEAR(fin) LIKE '.date('Y').' AND no_sincronizar_web = 0)';
        }
        $cursos = $db_cursos->get($where,'estado',100);

        $response['code'] = '200';
        foreach($cursos as $curso):
            $categoria = $curso->get('categoria');
            if($this->params()->fromQuery('completo') == 1){
                $response['data'][] = $curso->getFieldsWP(true);
            }else{
                $response['data'][] = [
                    'nombre' => $curso->get('nombre'),
                    'comienzo' => $curso->get('comienzo'),
                    'categoria' => $categoria->get('nombre'),
                    'estado' => Utilidades::systemOptions('cursos', 'estado')[(int)$curso->get('estado')],
                    'descripcion' => $curso->get('descripcion')
                ];
            }
        endforeach;
        $db_cursos->update(['no_sincronizar_web' => 1],'estado = 3 AND no_sincronizar_web = 0');

        return new JsonModel(
            $response
        );
    }

    /*
     * Action usado por método GET con el identificador pasado por url
     * */
    public function get($id){
        if(!$this->accessApi()->authorizationHeader($this->getRequest(), $this->configApi)){
            return new JsonModel(['data' => ['code' => '403', 'msg' => 'El usuario no tiene permisos.']]);
        }

        $db_cursos = new Cursos();
        $cursos = $db_cursos->get('id_cur = '  . (int)$id);
        $response['code'] = '200';
        if(count($cursos) > 0){
            $curso = reset($cursos);

            $response = [];
            $categoria = $curso->get('categoria');
            $response[] = [
                'nombre' => $curso->get('nombre'),
                'publicacion' => $curso->get('publicacion'),
                'comienzo' => $curso->get('comienzo'),
                'fin' => $curso->get('fin'),
                'horario' => $curso->get('horario'),
                'ubicacion' => $curso->get('ubicacion'),
                'enlubi' => $curso->get('enlubi'),
                'categoria' => $categoria->get('nombre'),
                'estado' => Utilidades::systemOptions('cursos', 'estado')[(int)$curso->get('estado')],
                'descripcion' => $curso->get('descripcion'),
                'objetivos' => $curso->get('objetivos'),
                'dirigido' => $curso->get('dirigido'),
                'dinamica' => $curso->get('dinamica'),
                'metodologia' => $curso->get('metodologia'),
                'programa' => $curso->get('programa'),
                'profesorado' => $curso->get('profesorado'),
                'precios' => $curso->get('precios'),
                'informacion' => $curso->get('informacion'),
                'precio_col' => $curso->get('precio_col'),
                'precio_otr' => $curso->get('precio_otr'),
                'tipo' => Utilidades::systemOptions('cursos', 'tipo')[(int)$curso->get('tipo')],
                'colegiados' => Utilidades::systemOptions('cursos', 'colegiados')[(int)$curso->get('colegiados')],
            ];
        }else{
            $response['msg'] = 'No existen cursos con los parámetros enviados.';
        }

        return new JsonModel(
            $response
        );
    }

}