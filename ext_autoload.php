<?php
/*
 * Register necessary class names with autoloader
 *
 */
$extensionPath = PATH_typo3conf . 'ext/dflsync/';
return array(
    'tx_dflsync_scheduler_synctask'                 => $extensionPath. 'Classes/Scheduler/SyncTask.php',
    'tx_dflsync_scheduler_synctaskaddfieldprovider' => $extensionPath. 'Classes/Scheduler/SyncTaskAddFieldProvider.php',
);
