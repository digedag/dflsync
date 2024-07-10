<?php

if (!(defined('TYPO3') || defined('TYPO3_MODE'))) {
    exit('Access denied.');
}

$_EXTKEY = 'dflsync';

// Scheduler für Sync
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\System25\T3sports\DflSync\Scheduler\SyncTask::class] = [
        'extension' => $_EXTKEY,
        'title' => '[DFL] Daten aktualisieren',
        'description' => 'Aktualisiert die Spieldaten der DFL',
        'additionalFields' => \System25\T3sports\DflSync\Scheduler\SyncTaskAddFieldProvider::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\System25\T3sports\DflSync\Scheduler\ProfileTask::class] = [
        'extension' => $_EXTKEY,
        'title' => '[DFL] Spieler und Trainer importieren',
        'description' => 'Importiert die Spieler und Trainer der DFL',
        'additionalFields' => \System25\T3sports\DflSync\Scheduler\ProfileTaskAddFieldProvider::class,
];
