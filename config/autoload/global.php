<?php
/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

use Zend\Db\Adapter;
use Zend\Log\Logger;
use Zend\Session\Storage\SessionArrayStorage;
use Zend\Session\Validator\RemoteAddr;
use Zend\Session\Validator\HttpUserAgent;

return [
    'module_layouts' => [
        'Application'   => 'layout/layout.phtml',
        'Backend'       => 'layout/backend.phtml',
        'Auth'          => 'layout/auth.phtml',
    ],
    'service_manager' => [
        'abstract_factories' => [
            Adapter\AdapterAbstractServiceFactory::class,
        ],
        'factories' => [
            Adapter\AdapterInterface::class => Adapter\AdapterServiceFactory::class,
            Zend\Log\Logger::class => function($sm){
                $logger = new Zend\Log\Logger();
                $writer = new Zend\Log\Writer\Stream('./data/log/'.date('Y-m-d').'-error.log');
                $logger->addWriter($writer);
                return $logger;
            },
        ],
        'aliases' => [
            Adapter\Adapter::class => Adapter\AdapterInterface::class,
        ],
    ],
    'db' => [
        'driver' => 'Pdo',
        //'dsn' => 'mysql:dbname=impulsoft_ingenieros_v1;host=localhost',
        'dsn' => 'mysql:dbname=coiiaoc_app;host=localhost',
        'driver_options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
        ],
//       Others databases
//        'adapters' => [
//            'other_db' => [
//                'driver'         => 'Pdo',
//                'driver_options'  => [
//                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
//                ],
//            ],
//        ],
    ],
    'phpSettings'   => [
        'display_startup_errors'        => true,
        'display_errors'                => true,
        /*'max_execution_time'            => 60,*/
        'date.timezone'                 => 'Europe/Madrid',
        /*'mbstring.internal_encoding'    => 'UTF-8',*/
    ],

    // Session configuration.
    'session_config' => [
        'name'                => 'coiiaocweb',
        'save_path'           => __DIR__ . '/../../data/session',
        'use_cookies'         => true,
        'cookie_lifetime'     => 1209600,
        'cookie_httponly'     => true,
        'cookie_secure'       => false,
        'remember_me_seconds' => 1209600,
        'gc_maxlifetime' 	  => 1209600
    ],
    // Session manager configuration.
    'session_manager' => [
        // Session validators (used for security).
        'validators' => [
            RemoteAddr::class,
            HttpUserAgent::class,
        ]
    ],
    // Session storage configuration.
    'session_storage' => [
        'type' => SessionArrayStorage::class
    ],

    'smpt_options' =>[
        'from'  => [
            'name' => 'Notificaciones | COIIAOC',
            'mail' => 'no-reply@coiiaoc.es'
        ],
        'smtp_config' => [
            'name'              => 'localhost',
            'host'              => 'mail.coiiaoc.com',
            'port'              => 25,
            'connection_class'  => 'login',
            'connection_config' => [
                'username'          => 'no-reply@coiiaoc.com',
                'password'          => '_Lw7oc488',
                //'ssl'               => 'tls'
            ]
        ]
    ],
    'client_api_info' => [
        'domain' => 'https://coiiaoc.com/',
        'authorization' => 'Basic ' . base64_encode('idev:9F$qb4O6AM'), // WP user:password
        'endpoints' => [
          'cursos'  => [
              'create' => 'wp-json/wp/v2/cursos/',
          ],
          'categorias'  => [
              'create' => 'wp-json/wp/v2/cursos-categorias/',
          ]
        ],
    ],
    'active_acl' => true
];
