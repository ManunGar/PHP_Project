<?php
/*
 * @link      http://easyleapp.es
 * @author    http://impulsoft.net
 */

return [
    'router' => [
        'routes' => [
            'cursos' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/api/cursos[/:id]',
                    'constraints' => [
                        'id'     => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => 'Api\Controller\Cursos',
                    ],
                ],
            ],
            'usuarios' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/api/usuarios[/:id]',
                    'constraints' => [
                        'id'     => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => 'Api\Controller\Usuarios',
                    ],
                ],
            ],
        ],
    ],

    'controller_plugins' => [
        'factories' => [
            Api\Controller\Plugin\AccessApiPlugin::class => InvokableFactory::class,
        ],
        'invokables' => [
            'accessApi' => Api\Controller\Plugin\AccessApiPlugin::class,
        ]
    ],

//    'controller_plugins' => [
//        'factories' => [
//            'ServiceLocatorPlugin' => function($pluginManager) {
//                /** @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator */
//                $serviceLocator = $pluginManager->getServiceLocator();
//                return new ServiceLocatorPlugin($serviceLocator);
//            }
//        )
//    ],
    'view_manager' => [
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
