<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Auth;

use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\ModuleManager;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Auth\Plugin\Autorizacion;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as DbTableAuthAdapter;
use Zend\Session\Config\StandardConfig;
use Zend\Session\SessionManager;
use Zend\Session\Container;

class Module implements AutoloaderProviderInterface
{
	
    public function init(ModuleManager $manager)
    {
    }

    public function onBootstrap(MvcEvent $e)
    {
        $this->bootstrapSession($e);
    }
	    
    public function bootstrapSession($e)
    {
    	$controller = $e->getTarget();
        $session = $e->getApplication()
                     ->getServiceManager()
                     ->get('Zend\Session\SessionManager');
        try {
            $session->start();
        }catch(\Exception $ex){
            $session->destroy();
            //return $controller->plugin('redirect')->toRoute('auth/default',array('controller' => 'index', 'action' => 'logout'));
        }
        $container =  $e->getApplication()->getServiceManager()->get('container_session');

        if (!isset($container->init)) {
            $serviceManager = $e->getApplication()->getServiceManager();
            $request        = $serviceManager->get('Request');
            $session->regenerateId(true);
            $container->init          = 1;
            if(get_class($request) != 'Zend\Console\Request'){
                $container->remoteAddr    = $request->getServer()->get('REMOTE_ADDR');
                $container->httpUserAgent = $request->getServer()->get('HTTP_USER_AGENT');
            }
            $config = $serviceManager->get('Config');
            if (!isset($config['session'])) {
                return;
            }
            $sessionConfig = $config['session'];
            if (isset($sessionConfig['validators'])) {
                $chain   = $session->getValidatorChain();
                foreach ($sessionConfig['validators'] as $validator) {
                    switch ($validator) {
                        case 'Zend\Session\Validator\HttpUserAgent':
                            $validator = new $validator($container->httpUserAgent);
                            $chain->attach('session.validate', array($validator, 'isValid'));
                            break;
                        case 'Zend\Session\Validator\RemoteAddr':
                            $validator  = new $validator($container->remoteAddr);
                            $chain->attach('session.validate', array($validator, 'isValid'));
                            break;
                        default:

                        	$auth = $e->getApplication()->getServiceManager()->get('auth_service');

                                if ($auth->hasIdentity()) {
                                        //$controller->plugin('redirect')->toRoute('backend');
                                }else{
                                        //$controller->plugin('redirect')->toRoute('auth');
                                }
                                //$controller = $e->getTarget();
                                //$controller->plugin('redirect')->toRoute('auth');
                                //$validator = new Validator();
                                /*$router = $e->getRouter();
                                $url    = $router->assemble(array(), array('name' => 'auth'));
                                header('location: /');*/
                                break;
                    }
                }
            }
        }
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'Zend\Session\SessionManager' => function ($sm) {
                    $config = $sm->get('config');
                    if (isset($config['session'])) {
                        $session = $config['session'];

                        $sessionConfig = null;
                        if (isset($session['config'])) {
                            $class = isset($session['config']['class'])  ? $session['config']['class'] : 'Zend\Session\Config\SessionConfig';
                            $options = isset($session['config']['options']) ? $session['config']['options'] : array();
                            $sessionConfig = new $class();
                            $sessionConfig->setOptions($options);
                        }

                        $sessionStorage = null;
                        if (isset($session['storage'])) {
                            $class = $session['storage'];
                            $sessionStorage = new $class();
                        }

                        $sessionSaveHandler = null;
                        if (isset($session['save_handler'])) {
                            // class should be fetched from service manager since it will require constructor arguments
                            $sessionSaveHandler = $sm->get($session['save_handler']);
                        }

                        $sessionManager = new SessionManager($sessionConfig, $sessionStorage, $sessionSaveHandler);
                    } else {
                        $sessionManager = new SessionManager();
                    }
                    Container::setDefaultManager($sessionManager);
                    return $sessionManager;
                },
            ),
        );
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    public function getControllerConfig(){
        return [
            'factories' => [
                'Auth\Controller\Index' => function($serviceManager){
                    $controller = new Controller\IndexController($serviceManager->get('SendMail'));
                    return $controller;
                }
            ]
        ];
    }
}
