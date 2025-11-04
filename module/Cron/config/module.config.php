<?php

return array(
    'controllers' => array(
        'invokables' => array(
            'Cron\Controller\IndexController' => 'Cron\Controller\IndexController'
        ),
    ),
    'console' => array(
        'router' => array(
            'routes' => array(
                //CRON RESULTS SCRAPER
                'my-first-route' => array(
                    'type'    => 'simple',
                    'options' => array(
                        'route'    => 'cron1',
                        'defaults' => array(
                            'controller' => 'Cron\Controller\IndexController',
                            'action'     => 'cron1'
                        )
                    )
                )
            )
        )
    )
);
