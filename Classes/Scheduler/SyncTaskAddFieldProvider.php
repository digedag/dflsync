<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2014 René Nitzsche <rene@system25.de>
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

require_once t3lib_extMgm::extPath('rn_base') . 'class.tx_rnbase.php';

define('FIELD_FILE_CLUB', 'fileClub');
define('FIELD_FILE_SAISON', 'fileSaison');
define('FIELD_COMPETITION', 'competition');
define('FIELD_PATH_MATCH_STATS', 'pathMatchStats');
define('FIELD_PATH_MATCH_INFO', 'pathMatchInfo');

/**
 *
 */
class Tx_Dflsync_Scheduler_SyncTaskAddFieldProvider implements tx_scheduler_AdditionalFieldProvider {

	/**
	 * This method is used to define new fields for adding or editing a task
	 * In this case, it adds an email field
	 *
	 * @param	array					$taskInfo: reference to the array containing the info used in the add/edit form
	 * @param	Tx_Dflsync_Scheduler_SyncTask		$task: when editing, reference to the current task object. Null when adding.
	 * @param	tx_scheduler_Module		$parentObject: reference to the calling object (Scheduler's BE module)
	 * @return	array					Array containg all the information pertaining to the additional fields
	 *									The array is multidimensional, keyed to the task class name and each field's id
	 *									For each field it provides an associative sub-array with the following:
	 *										['code']		=> The HTML code for the field
	 *										['label']		=> The label of the field (possibly localized)
	 *										['cshKey']		=> The CSH key for the field
	 *										['cshLabel']	=> The code of the CSH label
	 */
	public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $parentObject) {


		// Initialize extra field value
		if (!array_key_exists(FIELD_PATH, $taskInfo) || empty($taskInfo[FIELD_PATH])) {
			if ($parentObject->CMD == 'add') {
				// New task
				$taskInfo[FIELD_COMPETITION] = '';
				$taskInfo[FIELD_FILE_CLUB] = '';
				$taskInfo[FIELD_FILE_SAISON] = '';
				$taskInfo[FIELD_PATH_MATCH_INFO] = '';
				$taskInfo[FIELD_PATH_MATCH_STATS] = '';
			} elseif ($parentObject->CMD == 'edit') {
				// Editing a task, set to internal value if data was not submitted already
				$taskInfo[FIELD_COMPETITION] = $task->getCompetition();
				$taskInfo[FIELD_FILE_CLUB] = $task->getFileClub();
				$taskInfo[FIELD_FILE_SAISON] = $task->getFileSaison();
				$taskInfo[FIELD_PATH_MATCH_INFO] = $task->getPathMatchInfo();
				$taskInfo[FIELD_PATH_MATCH_STATS] = $task->getPathMatchStats();
			} else {
				// Otherwise set an empty value, as it will not be used anyway
				$taskInfo[FIELD_COMPETITION] = '';
				$taskInfo[FIELD_FILE_CLUB] = '';
				$taskInfo[FIELD_FILE_SAISON] = '';
				$taskInfo[FIELD_PATH_MATCH_INFO] = '';
				$taskInfo[FIELD_PATH_MATCH_STATS] = '';
			}
		}

		$additionalFields = array();
		$this->makeField($additionalFields, FIELD_COMPETITION, $taskInfo, 10);
		$this->makeField($additionalFields, FIELD_FILE_CLUB, $taskInfo);
		$this->makeField($additionalFields, FIELD_FILE_SAISON, $taskInfo);
		$this->makeField($additionalFields, FIELD_PATH_MATCH_INFO, $taskInfo);
		$this->makeField($additionalFields, FIELD_PATH_MATCH_STATS, $taskInfo);
		return $additionalFields;

	}
	private function makeField(&$additionalFields, $fieldName, $taskInfo, $size=40) {
		// Write the code for the field
		$fieldID = 'field_'.$fieldName;
		// Note: Name qualifier MUST be "tx_scheduler" as the tx_scheduler's BE module is used!
		$fieldCode = '<input type="text" name="tx_scheduler['.$fieldName.']" id="' . $fieldID .
		'" value="' . $taskInfo[$fieldName] . '" size="'.$size.'" />';
		$additionalFields[$fieldID] = array(
				'code'     => $fieldCode,
				'label'    => 'LLL:EXT:dflsync/Resources/Private/Language/locallang_db.xml:scheduler_syncTask_field_'.$fieldName,
				'cshKey'   => '_MOD_web_txschedulerM1',
				//			'cshLabel' => $fieldID
		);
	}

	/**
	 * This method checks any additional data that is relevant to the specific task
	 * If the task class is not relevant, the method is expected to return true
	 *
	 * @param	array					$submittedData: reference to the array containing the data submitted by the user
	 * @param	tx_scheduler_Module		$parentObject: reference to the calling object (Scheduler's BE module)
	 * @return	boolean					True if validation was ok (or selected class is not relevant), false otherwise
	 */
	public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $parentObject) {

		return true;

		$submittedData[FIELD_PATH] = trim($submittedData[FIELD_PATH]);
		if (empty($submittedData[FIELD_PATH])) {
			$parentObject->addMessage($GLOBALS['LANG']->sL('LLL:EXT:scheduler/mod1/locallang.xml:msg.noEmail'), t3lib_FlashMessage::ERROR);
			return false;
		}

		return true;
	}

	/**
	 * This method is used to save any additional input into the current task object
	 * if the task class matches
	 *
	 * @param	array				$submittedData: array containing the data submitted by the user
	 * @param	Tx_Dflsync_Scheduler_SyncTask	$task: reference to the current task object
	 * @return	void
	 */
	public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
		$task->setCompetition($submittedData[FIELD_COMPETITION]);
		$task->setFileClub($submittedData[FIELD_FILE_CLUB]);
		$task->setFileSaison($submittedData[FIELD_FILE_SAISON]);
		$task->setPathMatchInfo($submittedData[FIELD_PATH_MATCH_INFO]);
		$task->setPathMatchStats($submittedData[FIELD_PATH_MATCH_STATS]);
	}

}
