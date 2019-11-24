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
	'version' => '0.2.2',
	'state' => 'beta',
	'uploadfolder' => false,
	'createDirs' => '',
	'clearcacheonload' => true,
	'author' => 'René Nitzsche',
	'author_email' => 'rene@system25.de',
	'author_company' => 'System 25',
	'constraints' =>
	[
		'depends' =>
		[
			'typo3' => '4.5.0-8.7.99',
			'rn_base' => '1.10.1-0.0.0',
			'cfc_league' => '1.3.0-0.0.0'
		],
		'conflicts' => [],
		'suggests' => [],
	],
];

