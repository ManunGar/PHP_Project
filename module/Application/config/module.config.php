<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\Authentication\AuthenticationService;
use Zend\ServiceManager\Factory\InvokableFactory;
use Zend\Session\Container;

return [
//    'controllers' => [
//        'invokables' => [
//            'Application\Controller\Index' 				=> 'Application\Controller\IndexController',
//        ],
//    ],
    'router' => [
        'routes' => [
            'redirect' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/sistema-gestion[/:v1]',
                    'constraints' => [
                        'v1' => '[0-9]+'
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'     => 'redirect',
                    ],
                ],
            ],


            'redirect' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/empleo',
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'     => 'redirect',
                        'v1'         => 1
                    ],
                ],
            ],


            'inscription' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/inscripcion-curso[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+'
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'     => 'inscripcion',
                    ],
                ],
            ],
            'pagarinscription' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/pagar-curso[/:hash]',
                    'constraints' => [

                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'     => 'pagarinscripcion',
                    ],
                ],
            ],
            'justificantepago' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/justificante-pago[/:hash][/:msg]',
                    'constraints' => [

                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'     => 'justificantepago',
                    ],
                ],
            ],
            'recepcionaredsys' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/confirmacion-pago[/:v1]',
                    'constraints' => [

                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'     => 'recepcionaredsys',
                    ],
                ],
            ],
            'choose-lang' => [
                'type' => Segment::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ],
                ],
            ],
            'home1' => [
                'type' => Segment::class,
                'options' => [
                    'route'    => '/:locale',
                    'constraints' => [
                        'locale' => '[a-z]{2}(-[A-Z]{2}){0,1}'
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'     => 'index',
                    ],
                ],
            ],

            'application' => [
                'type'    => Segment::class,
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/application',
                    'defaults' => [
                        // Change this value to reflect the namespace in which
                        // the controllers for your module are found
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'index',
                        'action'        => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    // This route is a sane default when developing a module;
                    // as you solidify the routes for your module, however,
                    // you may want to remove it and replace it with more
                    // specific routes.
                    'default' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/[:controller[/:action[/:v1][/:v2][/:v3][/:v4][/:v5][/:v6]]][/]',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],


    'service_manager' => [
        'abstract_factories' => [
            //'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ],
        'aliases' => [
            'translator' => MvcTranslator::class,
            'auth_service' => AuthenticationService::class,
            'container_session' => Container::class
        ],
        'factories' => [
            AuthenticationService::class => InvokableFactory::class,
            Container::class => InvokableFactory::class,
        ],
    ],
    'translator' => [
        'locale' => 'es_ES',
        'translation_file_patterns' => [
            [
                'type'     => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.mo',
                'text_domain' => __NAMESPACE__,
            ],
            [
                'type'     => 'phparray',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.php',
                'text_domain' => 'ff'
            ],
        ],
    ],
    'view_manager' => [
        'display_not_found_reason' => false,
        'display_exceptions'       => false,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => [
            'error/404'                         => __DIR__ . '/../view/error/404.phtml',
            'error/index'                       => __DIR__ . '/../view/error/index.phtml',
            'application/view/layout/layout'    => __DIR__ . '/../view/layout/layout.phtml',
            'header'                            => __DIR__ . '/../view/layout/header.phtml',
            'footer'                            => __DIR__ . '/../view/layout/footer.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    // Placeholder for console routes
    'console' => [
        'router' => [
            'routes' => [
            ],
        ],
    ],
];
