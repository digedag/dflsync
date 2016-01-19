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

$EM_CONF[$_EXTKEY] = array (
	'title' => 'DFL Sync',
	'description' => 'Sync t3sports competitions with DFL data',
	'category' => 'backend',
	'version' => '0.2.0',
	'state' => 'beta',
	'uploadfolder' => false,
	'createDirs' => '',
	'clearcacheonload' => true,
	'author' => 'René Nitzsche',
	'author_email' => 'rene@system25.de',
	'author_company' => 'System 25',
	'constraints' =>
	array (
		'depends' =>
		array (
			'typo3' => '4.5.0-6.2.99',
			'rn_base' => '0.14.1-0.0.0',
			'cfc_league' => '1.0.0-0.0.0',
			'scheduler' => '6.2.0-0.0.0',
		),
		'conflicts' =>
		array (
		),
		'suggests' =>
		array (
		),
	),
);

