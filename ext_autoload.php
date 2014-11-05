<?php
/*
 * Register necessary class names with autoloader
 *
 */
return array(
	'tx_dflsync_scheduler_synctask'					=> t3lib_extMgm::extPath('dflsync', 'Classes/Scheduler/SyncTask.php'),
	'tx_dflsync_scheduler_synctaskaddfieldprovider'	=> t3lib_extMgm::extPath('dflsync', 'Classes/Scheduler/SyncTaskAddFieldProvider.php'),
);
