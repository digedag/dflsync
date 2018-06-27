<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

// Scheduler für Sync
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Tx_Dflsync_Scheduler_SyncTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => '[DFL] Daten aktualisieren',
		'description'      => 'Aktualisiert die Spieldaten der DFL',
		'additionalFields' => 'Tx_Dflsync_Scheduler_SyncTaskAddFieldProvider'
);
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Tx_Dflsync_Scheduler_ProfileTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => '[DFL] Spieler und Trainer importieren',
		'description'      => 'Importiert die Spieler und Trainer der DFL',
		'additionalFields' => 'Tx_Dflsync_Scheduler_ProfileTaskAddFieldProvider'
);

