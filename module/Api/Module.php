<?php
/*
 * @link      http://easyleapp.es
 * @author    http://impulsoft.net
 */

namespace Api;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;

class Module{

    public function onBootstrap(MvcEvent $e){
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'onDispatchError'], 0);
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'onRenderError'], 0);

    }
    public function onDispatchError($e){
        return $this->getJsonModelError($e);
    }

    public function onRenderError($e){
        return $this->getJsonModelError($e);
    }

    public function getJsonModelError($e){
//        $error = $e->getError();
//        if (!$error) {
//            return;
//        }
//        $response = $e->getResponse();
//        $exception = $e->getParam('exception');
//        $exceptionJson = array();
//        if ($exception) {
//            $exceptionJson = array(
//                'class' => get_class($exception),
//                'file' => $exception->getFile(),
//                'line' => $exception->getLine(),
//                'message' => $exception->getMessage(),
//                'stacktrace' => $exception->getTraceAsString()
//            );
//        }
//        $errorJson = array(
//            'message'   => 'An error occurred during execution; please try again later.',
//            'error'     => $error,
//            'exception' => $exceptionJson,
//        );
//        if ($error == 'error-router-no-match') {
//            $errorJson['message'] = 'Resource not found.';
//        }
//        $model = new JsonModel(['errors' => [$errorJson]]);
//        $e->setResult($model);
//        return $model;
    }
    public function getConfig(){
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig(){
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
                'Api\Controller\Usuarios' => function($serviceManager){
                    $config = $serviceManager->get('config');
                    $controller = new Controller\UsuariosController($config['api']);
                    return $controller;
                },
                'Api\Controller\Cursos' => function($serviceManager){
                    $config = $serviceManager->get('config');
                    $controller = new Controller\CursosController($config['api']);
                    return $controller;
                }
            ]
        ];
    }
}
