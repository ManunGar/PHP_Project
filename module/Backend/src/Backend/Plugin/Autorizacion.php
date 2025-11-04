<?php

namespace Backend\Plugin;
 
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Session\Container as SessionContainer;
use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Role\GenericRole as Role;
use Zend\Permissions\Acl\Resource\GenericResource as Resource;
    
class Autorizacion extends AbstractPlugin
{
    protected $sesscontainer ;

    public function __construct($rol = 'anonymous'){
    	if (!$this->sesscontainer) {
            $this->sesscontainer = new SessionContainer('autorizacion');
        }
        $this->sesscontainer->role = $rol;
    }

    private function getSessContainer()
    {
        if (!$this->sesscontainer) {
            $this->sesscontainer = new SessionContainer('autorizacion');
        }
        return $this->sesscontainer;
    }
    
    public function doAuthorization($e)
    {
        //setting ACL...
        $acl = new Acl();
        //add role ..
        $acl->addRole(new Role('anonymous'));
        $acl->addRole(new Role('4dmin'));
        $acl->addRole(new Role('c4legiado'));
        $acl->addRole(new Role('us4ario'));
        
        $acl->addResource(new Resource('Application'));
        $acl->addResource(new Resource('Backend'));
        $acl->addResource(new Resource('Auth'));
		
        $acl->deny('anonymous', 'Backend', 'index:index');
        /* ADMIN */
        $acl->allow('4dmin', 'Backend', 'index:index');
        $acl->allow('4dmin', 'Backend', 'index:error');
        $acl->allow('4dmin', 'Backend', 'index:permiso');
        $acl->allow('4dmin', 'Backend', 'index:documentos');
        $acl->allow('4dmin', 'Backend', 'index:dev');
        
        $acl->allow('4dmin', 'Backend', 'usuarios:index');
        $acl->allow('4dmin', 'Backend', 'usuarios:ficha');
        $acl->allow('4dmin', 'Backend', 'usuarios:xls');
        $acl->allow('4dmin', 'Backend', 'usuarios:borrar');
        $acl->allow('4dmin', 'Backend', 'usuarios:menores');
        $acl->allow('4dmin', 'Backend', 'usuarios:borrarmenor');
        $acl->allow('4dmin', 'Backend', 'usuarios:foto');
        $acl->allow('4dmin', 'Backend', 'usuarios:importarcolegiados');
        $acl->allow('4dmin', 'Backend', 'usuarios:actualizacol');
        $acl->allow('4dmin', 'Backend', 'usuarios:carpetas');
        $acl->allow('4dmin', 'Backend', 'usuarios:borrarcarpeta');
        $acl->allow('4dmin', 'Backend', 'usuarios:permisos');
        $acl->allow('4dmin', 'Backend', 'usuarios:cv');
        $acl->allow('4dmin', 'Backend', 'usuarios:xa');
        
        $acl->allow('4dmin', 'Backend', 'empresas:index');
        $acl->allow('4dmin', 'Backend', 'empresas:ficha');
        $acl->allow('4dmin', 'Backend', 'empresas:xls');
        $acl->allow('4dmin', 'Backend', 'empresas:borrar');
        $acl->allow('4dmin', 'Backend', 'empresas:xa');
        $acl->allow('4dmin', 'Backend', 'empresas:importar');
        $acl->allow('4dmin', 'Backend', 'empresas:usuarios');
        
        $acl->allow('4dmin', 'Backend', 'formacion:index');
        $acl->allow('4dmin', 'Backend', 'formacion:cursos');
        $acl->allow('4dmin', 'Backend', 'formacion:curso');
        $acl->allow('4dmin', 'Backend', 'formacion:xlscursos');
        $acl->allow('4dmin', 'Backend', 'formacion:borrarcurso');
        $acl->allow('4dmin', 'Backend', 'formacion:generacerfificados');
        $acl->allow('4dmin', 'Backend', 'formacion:inscripciones');
        $acl->allow('4dmin', 'Backend', 'formacion:inscripcion');
        $acl->allow('4dmin', 'Backend', 'formacion:inscripcionjustificante');
        $acl->allow('4dmin', 'Backend', 'formacion:agregaparticipantes');
        $acl->allow('4dmin', 'Backend', 'formacion:agregamenores');
        $acl->allow('4dmin', 'Backend', 'formacion:xlsinscripciones');
        $acl->allow('4dmin', 'Backend', 'formacion:xlsinscritos');
        $acl->allow('4dmin', 'Backend', 'formacion:xlsparticipantes');
        $acl->allow('4dmin', 'Backend', 'formacion:borrarinscripcion');
        $acl->allow('4dmin', 'Backend', 'formacion:inscritos');
        $acl->allow('4dmin', 'Backend', 'formacion:participantes');
        $acl->allow('4dmin', 'Backend', 'formacion:asistentes');
        $acl->allow('4dmin', 'Backend', 'formacion:borrarinscrito');
        $acl->allow('4dmin', 'Backend', 'formacion:borrarparticipante');
        $acl->allow('4dmin', 'Backend', 'formacion:categorias');
        $acl->allow('4dmin', 'Backend', 'formacion:borrarcategoria');
        $acl->allow('4dmin', 'Backend', 'formacion:sincronizacurso');
        $acl->allow('4dmin', 'Backend', 'formacion:asistencia');
        $acl->allow('4dmin', 'Backend', 'formacion:certificado');
        $acl->allow('4dmin', 'Backend', 'formacion:inscripcionesincompletas');
        $acl->allow('4dmin', 'Backend', 'formacion:xa');

        $acl->allow('4dmin', 'Backend', 'inscripciones:index');
        $acl->allow('4dmin', 'Backend', 'inscripciones:inscripcion');
        $acl->allow('4dmin', 'Backend', 'inscripciones:eventoinfantil');
        $acl->allow('4dmin', 'Backend', 'inscripciones:individual');
        $acl->allow('4dmin', 'Backend', 'inscripciones:empresa');
        $acl->allow('4dmin', 'Backend', 'inscripciones:participantes');
        $acl->allow('4dmin', 'Backend', 'inscripciones:borrarinscrito');
        $acl->allow('4dmin', 'Backend', 'inscripciones:borrarparticipante');
        $acl->allow('4dmin', 'Backend', 'inscripciones:resumeninscripcion');
        $acl->allow('4dmin', 'Backend', 'inscripciones:confirmacion');
        $acl->allow('4dmin', 'Backend', 'inscripciones:cancelar');
        $acl->allow('4dmin', 'Backend', 'inscripciones:xa');
        
        $acl->allow('4dmin', 'Backend', 'empleo:index');
        $acl->allow('4dmin', 'Backend', 'empleo:ofertas');
        $acl->allow('4dmin', 'Backend', 'empleo:oferta');
        $acl->allow('4dmin', 'Backend', 'empleo:ofertasempleo');
        $acl->allow('4dmin', 'Backend', 'empleo:xlsofertas');
        $acl->allow('4dmin', 'Backend', 'empleo:borraroferta');
        $acl->allow('4dmin', 'Backend', 'empleo:candidaturas');
        $acl->allow('4dmin', 'Backend', 'empleo:candidatura');
        $acl->allow('4dmin', 'Backend', 'empleo:xlscandidaturas');
        $acl->allow('4dmin', 'Backend', 'empleo:borrarcandidatura');
        $acl->allow('4dmin', 'Backend', 'empleo:sectores');
        $acl->allow('4dmin', 'Backend', 'empleo:borrarsector');
        $acl->allow('4dmin', 'Backend', 'empleo:xa');
        
        /* COLEGIADO */
        $acl->allow('c4legiado', 'Backend', 'index:index');
        $acl->allow('c4legiado', 'Backend', 'index:error');
        $acl->allow('c4legiado', 'Backend', 'index:permiso');
        $acl->allow('c4legiado', 'Backend', 'index:documentos');
        
        $acl->allow('c4legiado', 'Backend', 'usuarios:ficha');
        $acl->allow('c4legiado', 'Backend', 'usuarios:cv');
        $acl->allow('c4legiado', 'Backend', 'usuarios:xa');
        
        $acl->allow('c4legiado', 'Backend', 'formacion:inscritos');
        $acl->allow('c4legiado', 'Backend', 'formacion:inscripcion');
        $acl->allow('c4legiado', 'Backend', 'formacion:inscripcionjustificante');
        $acl->allow('c4legiado', 'Backend', 'formacion:agregaparticipantes');
        $acl->allow('c4legiado', 'Backend', 'formacion:agregamenores');
        $acl->allow('c4legiado', 'Backend', 'formacion:xa');

        $acl->allow('c4legiado', 'Backend', 'inscripciones:index');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:inscripcion');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:eventoinfantil');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:individual');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:empresa');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:participantes');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:borrarinscrito');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:borrarparticipante');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:resumeninscripcion');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:confirmacion');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:cancelar');
        $acl->allow('c4legiado', 'Backend', 'inscripciones:xa');

        $acl->allow('c4legiado', 'Backend', 'empleo:ofertasempleo');
        $acl->allow('c4legiado', 'Backend', 'empleo:presentarcandidatura');
        $acl->allow('c4legiado', 'Backend', 'empleo:oferta');
        $acl->allow('c4legiado', 'Backend', 'empleo:candidatura');
        $acl->allow('c4legiado', 'Backend', 'empleo:candidaturas');
        $acl->allow('c4legiado', 'Backend', 'empleo:xa');
        
        $acl->allow('c4legiado', 'Backend', 'empresas:ficha');
        $acl->allow('c4legiado', 'Backend', 'empresas:usuarios');

        /* USUARIO REGISTRADO */
        $acl->allow('us4ario', 'Backend', 'index:index');
        $acl->allow('us4ario', 'Backend', 'index:error');
        $acl->allow('us4ario', 'Backend', 'index:permiso');
        $acl->allow('us4ario', 'Backend', 'index:documentos');
        
        $acl->allow('us4ario', 'Backend', 'usuarios:ficha');
        $acl->allow('us4ario', 'Backend', 'usuarios:cv');
        $acl->allow('us4ario', 'Backend', 'usuarios:xa');
        
        $acl->allow('us4ario', 'Backend', 'formacion:inscritos');
        $acl->allow('us4ario', 'Backend', 'formacion:inscripcion');
        $acl->allow('us4ario', 'Backend', 'formacion:inscripcionjustificante');
        $acl->allow('us4ario', 'Backend', 'formacion:agregaparticipantes');
        $acl->allow('us4ario', 'Backend', 'formacion:agregamenores');
        $acl->allow('us4ario', 'Backend', 'formacion:xa');

        $acl->allow('us4ario', 'Backend', 'inscripciones:index');
        $acl->allow('us4ario', 'Backend', 'inscripciones:inscripcion');
        $acl->allow('us4ario', 'Backend', 'inscripciones:eventoinfantil');
        $acl->allow('us4ario', 'Backend', 'inscripciones:individual');
        $acl->allow('us4ario', 'Backend', 'inscripciones:empresa');
        $acl->allow('us4ario', 'Backend', 'inscripciones:participantes');
        $acl->allow('us4ario', 'Backend', 'inscripciones:borrarinscrito');
        $acl->allow('us4ario', 'Backend', 'inscripciones:borrarparticipante');
        $acl->allow('us4ario', 'Backend', 'inscripciones:resumeninscripcion');
        $acl->allow('us4ario', 'Backend', 'inscripciones:confirmacion');
        $acl->allow('us4ario', 'Backend', 'inscripciones:cancelar');
        $acl->allow('us4ario', 'Backend', 'inscripciones:xa');

        $acl->allow('us4ario', 'Backend', 'empleo:ofertasempleo');
        $acl->allow('us4ario', 'Backend', 'empleo:ofertas');
        $acl->allow('us4ario', 'Backend', 'empleo:oferta');
        $acl->allow('us4ario', 'Backend', 'empleo:candidatura');
        $acl->allow('us4ario', 'Backend', 'empleo:xa');

        $acl->allow('us4ario', 'Backend', 'empresas:ficha');
        $acl->allow('us4ario', 'Backend', 'empresas:usuarios');
        
        $controller = $e->getTarget();
        $controllerClass = get_class($controller);
       
        $moduleName = substr($controllerClass, 0, strpos($controllerClass, '\\'));
        $routeMatch = $e->getRouteMatch();
        $controllerName1 = $routeMatch->getParam('controller', 'not-found');
        $actionName = strtolower($routeMatch->getParam('action', 'not-found'));
        $ooo = explode('\\', $controllerName1);
        $controllerName = strtolower(array_pop($ooo));
        
        $role = (! $this->getSessContainer()->role ) ? 'anonymous' : $this->getSessContainer()->role;

        $config = $e->getApplication()->getServiceManager()->get('config');
        $access = true;
        if (!$acl->isAllowed($role, $moduleName, $controllerName.':'.$actionName) && $config['active_acl']){
            $access = false;
        }
        return $access;
    }
}
