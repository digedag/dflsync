<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "bsdist".
 *
 * Auto generated 29-10-2014 10:39
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'DFL Sync',
    'description' => 'Sync t3sports competitions with DFL data',
    'category' => 'backend',
    'version' => '0.3.0',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearcacheonload' => true,
    'author' => 'René Nitzsche',
    'author_email' => 'rene@system25.de',
    'author_company' => 'System 25',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-12.4.99',
            'rn_base' => '1.18.0-0.0.0',
            'cfc_league' => '1.11.0-0.0.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
