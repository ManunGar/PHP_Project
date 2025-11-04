<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Application;
use Zend\Authentication\AuthenticationService;
use Zend\Session\Container;

class Module
{
    const VERSION = '3.0.3-dev';

    public function onBootstrap(MvcEvent $e){
        $eventManager        = $e->getApplication()->getEventManager();

        $eventManager->getSharedManager()->attach('Zend\Mvc\Controller\AbstractActionController', 'dispatch', function($e) {
            $controller      = $e->getTarget();
            $controllerClass = get_class($controller);
            $moduleNamespace = substr($controllerClass, 0, strpos($controllerClass, '\\'));
            $config          = $e->getApplication()->getServiceManager()->get('config');
            if (isset($config['module_layouts'][$moduleNamespace])) {
                $controller->layout($config['module_layouts'][$moduleNamespace]);
            }

            $phpSettings = $config['phpSettings'];
            $phpSettings = null;
            if($phpSettings) {
                foreach($phpSettings as $key => $value) {
                    ini_set($key, $value);
                }
            }

            $route = $controller->getEvent()->getRouteMatch();
            $controller->getEvent()->getViewModel()->setVariables(array(
                'controller' => $route->getParam('controller'),
                'action' => $route->getParam('action'),
            ));
        }, 100);

        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $sm = $e->getApplication()->getServiceManager();

        $adapter = $sm->get(\Zend\Db\Adapter\Adapter::class);

        \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::setStaticAdapter($adapter);

        $eventManager->getSharedManager()->attach(__NAMESPACE__, MvcEvent::EVENT_DISPATCH, function($e) {
            /*
             * TODO Pre dispatch
             * */
        }, 100);

        /*Control de errores*/
        $sharedManager = $e->getApplication()->getEventManager()->getSharedManager();
        $sm = $e->getApplication()->getServiceManager();
        $sharedManager->attach('Zend\Mvc\Application', 'dispatch.error',
            function($e) use ($sm) {
                if ($e->getParam('exception')){

                    $ex = $e->getParam('exception');
                    do {
                        $sm->get('Zend\Log\Logger')->crit($e->getParam('exception'));
                        $sm->get('Zend\Log\Logger')->crit(sprintf(
                            "%s:%d %s (%d) [%s]\n",
                            $ex->getFile(),
                            $ex->getLine(),
                            $ex->getMessage(),
                            $ex->getCode(),
                            get_class($ex)
                        ));
                    }
                    while($ex = $ex->getPrevious());
                }
            },-100
        );

        $sharedManager->attach('Zend\Mvc\Controller\AbstractActionController', 'dispatch', array($this, 'handleControllerCannotDispatchRequest' ), 101);
        $eventManager->attach('dispatch.error', array($this, 'handleControllerNotFoundAndControllerInvalidAndRouteNotFound'), 100);
        /*FIN CONTROL DE ERRORES*/
    }

    public function handleControllerNotFoundAndControllerInvalidAndRouteNotFound(MvcEvent $e)
    {
        $error  = $e->getError();
        $logText = null;
        if ($error == Application::ERROR_CONTROLLER_NOT_FOUND) {
            $logText =  'The requested controller '
                .$e->getRouteMatch()->getParam('controller'). '  could not be mapped to an existing controller class.';
        }

        if ($error == Application::ERROR_CONTROLLER_INVALID) {
            $logText =  'The requested controller '
                .$e->getRouteMatch()->getParam('controller'). ' is not dispatchable';
        }

        if ($error == Application::ERROR_ROUTER_NO_MATCH) {
            // $logText =  'The requested URL could not be matched by routing.';
        }
        if(isset($logText)){
            $sm = $e->getApplication()->getServiceManager();
            $sm->get('Zend\Log\Logger')->crit($error .': ' . $logText);
        }

        $this->redirectError($e);
    }

    public function handleControllerCannotDispatchRequest(MvcEvent $e)
    {
        $action = $e->getRouteMatch()->getParam('action');
        $controller = get_class($e->getTarget());
        // error-controller-cannot-dispatch
        $logText = null;
        if (! method_exists($e->getTarget(), $action.'Action')) {
            $logText = 'The requested controller '.
                $controller.' was unable to dispatch the request : '.$action.'Action';
            //you can do logging, redirect, etc here..
            $sm = $e->getApplication()->getServiceManager();
            $sm->get('Zend\Log\Logger')->crit($logText);

            $this->redirectError($e);
        }

    }

    public function redirectError(MvcEvent $e){

        $container = new Container('namespace');
        //$container->getManager()->destroy();

        foreach($container as $index => $cont):
            unset($container[$index]);
        endforeach;

        $config = $e->getApplication()->getServiceManager()->get('config');
        $phpSettings = $config['phpSettings'];

        if(!$phpSettings['display_errors']) {
            // Error log
            $sm = $e->getApplication()->getServiceManager();
            if ($e->getParam('exception')) {
                $ex = $e->getParam('exception');
                do {
                    $sm->get('Zend\Log\Logger')->crit($e->getParam('exception'));
                    $sm->get('Zend\Log\Logger')->crit(sprintf(
                                    "%s:%d %s (%d) [%s]\n", $ex->getFile(), $ex->getLine(), $ex->getMessage(), $ex->getCode(), get_class($ex)
                    ));
                } while ($ex = $ex->getPrevious());
            }
            // Mensaje de error
            $response = $e->getResponse();
            $response->setStatusCode(302);
            $url = $e->getRouter()->assemble(array('action' => 'error'), array('name' => 'backend'));
            $response->getHeaders()->addHeaderLine('Location', $url .'/index/error');
            $response->send();
            $e->stopPropagation();
        }
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
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

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'SendMail' => function($serviceManager){
                    $config = $serviceManager->get('config');
                    return new \Application\Service\SendMail($config['smpt_options']);
                },
                'ApiClientRest' => function($serviceManager){
                    $config = $serviceManager->get('config');
                    return new \Application\Service\ApiClientRest($config['client_api_info']);
                }
            ),
        );
    }
    
    public function getControllerConfig(){
        return [
            'factories' => [
                'Application\Controller\Index' => function($serviceManager){
                    $controller = new Controller\IndexController($serviceManager->get('SendMail'));
                    return $controller;
                }
            ]
        ];
    }
}
