<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Backend;

use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\ModuleManager;
use Zend\Authentication\AuthenticationService;

use Application\Model\Entity\Usuario;
use Backend\Plugin\Autorizacion;
use Zend\Session\Container;

class Module
{
	
	public function init(ModuleManager $manager)
	{
  
	}
	
	public function onBootstrap(MvcEvent $e) 
    {

        $eventManager        = $e->getApplication()->getEventManager();
        $eventManager->attach('dispatch', array($this, 'loadConfiguration' ));

         $eventManager->getSharedManager()->attach(__NAMESPACE__, MvcEvent::EVENT_DISPATCH, function($e) {
         	
            $auth =  $e->getApplication()->getServiceManager()->get('auth_service');
            $controller = $e->getTarget();
            if ($auth->hasIdentity()) {
                $identity = $auth->getIdentity();
                $rol = $identity->rol;

                $autorizacion = new Autorizacion($rol);

                if(!$autorizacion->doAuthorization($e)){
                    return $controller->plugin('redirect')->toRoute('backend/default',array('controller' => 'index', 'action' => 'permiso'));
                }

                $usuario = new Usuario($identity->id_usu);
                $controller->layout()->usuario_session = $usuario;
            }else{
                return $controller->plugin('redirect')->toRoute('auth/default',array('controller' => 'index', 'action' => 'logout'));
            }
        }, 100);
        
        //Comportamiento postDispatch
        $eventManager->getSharedManager()->attach(__NAMESPACE__, MvcEvent::EVENT_DISPATCH, function($e) {
            $controller = $e->getTarget();
            $container =  $e->getApplication()->getServiceManager()->get('container_session');

            if(isset($container->via_cliente_proveedor)){
                    $via_cliente_proveedor = $container->via_cliente_proveedor;
            }else{
                    $via_cliente_proveedor = 0;
            }
            $controller->layout()->via_cliente_proveedor = $via_cliente_proveedor;
            if(isset($container->via_encargo)){
                    $via_encargo = $container->via_encargo;
            }else{
                    $via_encargo = 0;
            }
            $controller->layout()->via_encargo = $via_encargo;
            if(isset($container->title_app)){
                    $title_app = $container->title_app;
            }else{
                    $title_app = null;
            }
            $controller->layout()->title_app = $title_app;

        }, -100);
	    
    }
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
	    
	public function loadConfiguration(MvcEvent $e)
    {
    	//Para pasar a todas las vistas del modelo, una variable determinada
        $auth =  $e->getApplication()->getServiceManager()->get('auth_service');
		$identity = $auth->getIdentity();
		if ($auth->hasIdentity()) {
			if(isset($identity->id_usu)){
				$usuario = new Usuario($identity->id_usu);
				
				$controller = $e->getTarget();
        		$controller->layout()->usuario_session = $usuario;
			}else{
				$controller = $e->getTarget();
				$controller->plugin('redirect')->toRoute('auth');
			}
		}
		
    }
	
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getControllerConfig(){
        return [
            'factories' => [
                'Backend\Controller\Formacion' => function($serviceManager){
                    $controller = new Controller\FormacionController($serviceManager->get('ApiClientRest'), $serviceManager->get('SendMail'));
                    return $controller;
                },
                'Backend\Controller\Inscripciones' => function($serviceManager){
                    $controller = new Controller\InscripcionesController($serviceManager->get('SendMail'));
                    return $controller;
                }
            ]
        ];
    }
}
