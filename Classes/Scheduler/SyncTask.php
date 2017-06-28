<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2014-2017 René Nitzsche <rene@system25.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 *
 */
class Tx_Dflsync_Scheduler_SyncTask extends tx_scheduler_Task {

	/**
	 * Amount of items to be indexed at one run
	 *
	 * @var	int
	 */
	private $competition;
	private $fileSaison;
	private $fileClub;
	private $pathMatchStats;
	private $pathMatchInfo;

	/**
	 * Function executed from the Scheduler.
	 * Sends an email
	 *
	 * @return	void
	 */
	public function execute() {
		$success = true;

		try {
			$sync = tx_rnbase::makeInstance('Tx_Dflsync_Service_Sync');
			$sync->doSync($this->competition, $this->fileSaison, $this->fileClub, $this->pathMatchStats, $this->pathMatchInfo);
		} catch (Exception $e) {
			tx_rnbase_util_Logger::fatal('Task failed!', 'dflsync', array('Exception' => $e->getMessage()));
			//Da die Exception gefangen wird, würden die Entwickler keine Mail bekommen
			//also machen wir das manuell
			if($addr = tx_rnbase_configurations::getExtensionCfgValue('rn_base', 'sendEmailOnException')) {
				tx_rnbase::load('tx_rnbase_util_Misc');
				tx_rnbase_util_Misc::sendErrorMail($addr, 'Tx_Dflsync_Scheduler_SyncTask', $e);
			}
			$success = false;
		}

		return $success;
	}

	/**
	 *
	 * @return int
	 */
	public function getCompetition() {
		return $this->competition;
	}

	/**
	 * Set amount of items
	 *
	 * @param int	$val
	 * @return void
	 */
	public function setCompetition($val) {
		if (!intval($val))
			throw new Exception('tx_dflsync_scheduler_SyncTask->setCompetition(): Invalid Competition given!');
		// else
		$this->competition = intval($val);
	}
	public function getFileClub() {
		return $this->fileClub;
	}
	public function setFileClub($val) {
		$this->fileClub = $val;
	}
	public function getFileSaison() {
		return $this->fileSaison;
	}
	public function setFileSaison($val) {
		$this->fileSaison = $val;
	}
	/**
	 * Das Verzeichnis mit den Dateien für die Spielinformationen
	 * DFL-MAT-0002MV.xml
	 */
	public function getPathMatchInfo() {
		return $this->pathMatchInfo;
	}
	public function setPathMatchInfo($val) {
		$this->pathMatchInfo = $val;
	}
	/**
	 * Das Verzeichnis mit den Dateien für die Spielstatistik
	 * DFL-MAT-0002MV.xml
	 */
	public function getPathMatchStats() {
		return $this->pathMatchStats;
	}
	public function setPathMatchStats($val) {
		$this->pathMatchStats = $val;
	}

	/**
	 * This method returns the destination mail address as additional information
	 *
	 * @return	string	Information to display
	 */
	public function getAdditionalInformation() {
		$compName = '';

		if($compUid = $this->getCompetition()) {
			$competition = tx_rnbase::makeInstance('tx_cfcleague_models_Competition', $compUid);
			$compName = $competition->isValid() ? $competition->getName() : '[invalid!]';
		}

		return sprintf('Aktualisierung DFL-Daten für Wettbewerb >%s<', $compName);
// 		return sprintf(	$GLOBALS['LANG']->sL('LLL:EXT:mksearch/locallang_db.xml:scheduler_indexTask_taskinfo'),
// 			$this->getTargetPath(), $this->getItemsInQueue());
	}
}

