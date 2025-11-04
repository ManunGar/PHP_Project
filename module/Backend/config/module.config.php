<?php

use Zend\Router\Http\Segment;

return [
    'controllers' => [
        'invokables' => [
            'Backend\Controller\Index' 				=> 'Backend\Controller\IndexController',
            'Backend\Controller\Empleo' 			=> 'Backend\Controller\EmpleoController',
            'Backend\Controller\Usuarios' 			=> 'Backend\Controller\UsuariosController',
            'Backend\Controller\Empresas' 			=> 'Backend\Controller\EmpresasController',
            'Backend\Controller\Estadisticas'		=> 'Backend\Controller\EstadisticasController',
        ],
    ],
     'router' => [
        'routes' => [
            'backend' => [
                'type'    => Segment::class,
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/backend',
                    'defaults' => [
                        // Change this value to reflect the namespace in which
                        // the controllers for your module are found
                        '__NAMESPACE__' => 'Backend\Controller',
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
                            'route'    => '/[:controller[/:action][/:v1][/:v2][/:v3][/:v4][/:v5][/:v6][/:v7][/:v8][/:v9][/:v10][/:v11][/:v12][/:v13][/:v14][/:v15][/:v16][/:v17][/:v18][/:v19][/:v20][/:v21][/:v22][/:v23]][/]', 
                    		//Listados $page/$order/$mensaje
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
    
    'view_manager' => [
       	'display_not_found_reason' => false,
        'display_exceptions'       => false,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => [
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            'backend' => __DIR__ . '/../view',
        ],
        'strategies' => [
           'ViewJsonStrategy',
        ],
    ],

    'controller_plugins' => array(
        //This is also not working
        //'invokables' => array(
        //    'userPlugin' => 'User\Controller\Plugin\UserPlugin',
        //),
        'factories' => [
            Controller\Plugin\Autorizacion::class => InvokableFactory::class,
        ],
        'aliases' => [
            'autorization' => Controller\Plugin\Autorizacion::class,
        ]
    ),

    // Placeholder for console routes
    'console' => [
        'router' => [
            'routes' => [
            ],
        ],
    ],
];
