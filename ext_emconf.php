<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Event calendar',
    'description' => 'A simple calendar for upcoming events',
    'category' => 'plugin',
    'author' => 'Sebastian Stein',
    'author_email' => 'sebastian.stein@in2code.de',
    'author_company' => 'In2code GmbH',
    'state' => 'stable',
    'version' => '11.0.1',
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-8.9.99',
            'typo3' => '11.5.0-11.5.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'In2code\\GbEvents\\' => 'Classes',
        ],
    ]
];
