<?php

namespace System25\T3sports\DflSync\Scheduler;

use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\Enumeration\Action;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2024 René Nitzsche <rene@system25.de>
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

class ProfileTaskAddFieldProvider extends AbstractAdditionalFieldProvider
{
    public const FIELD_COMPETITION = 'competition';
    public const FIELD_PATH_CLUB_INFO = 'pathClubInfo';
    public const FIELD_PID_OWN = 'pidOwn';
    public const FIELD_PID_OTHER = 'pidOther';

    /**
     * This method is used to define new fields for adding or editing a task
     * In this case, it adds an email field.
     *
     * @param array $taskInfo reference to the array containing the info used in the add/edit form
     * @param ProfileTask $task when editing, reference to the current task object. Null when adding.
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     *
     * @return array Array containg all the information pertaining to the additional fields
     *         The array is multidimensional, keyed to the task class name and each field's id
     *         For each field it provides an associative sub-array with the following:
     *         ['code'] => The HTML code for the field
     *         ['label'] => The label of the field (possibly localized)
     *         ['cshKey'] => The CSH key for the field
     *         ['cshLabel'] => The code of the CSH label
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule)
    {
        $currentSchedulerModuleAction = $schedulerModule->getCurrentAction();
        // Initialize extra field value
        if (!array_key_exists(self::FIELD_PATH_CLUB_INFO, $taskInfo) || empty($taskInfo[self::FIELD_PATH_CLUB_INFO])) {
            $taskInfo[self::FIELD_COMPETITION] = '';
            $taskInfo[self::FIELD_PATH_CLUB_INFO] = '';
            $taskInfo[self::FIELD_PID_OWN] = '';
            $taskInfo[self::FIELD_PID_OTHER] = '';
            if ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                // Editing a task, set to internal value if data was not submitted already
                $taskInfo[self::FIELD_COMPETITION] = $task->getCompetition();
                $taskInfo[self::FIELD_PATH_CLUB_INFO] = $task->getPathClubInfo();
                $taskInfo[self::FIELD_PID_OWN] = $task->getPidOwn();
                $taskInfo[self::FIELD_PID_OTHER] = $task->getPidOther();
            }
        }

        $additionalFields = [];
        $this->makeField($additionalFields, self::FIELD_COMPETITION, $taskInfo, 10);
        $this->makeField($additionalFields, self::FIELD_PATH_CLUB_INFO, $taskInfo, 80);
        $this->makeField($additionalFields, self::FIELD_PID_OWN, $taskInfo, 10);
        $this->makeField($additionalFields, self::FIELD_PID_OTHER, $taskInfo, 10);

        return $additionalFields;
    }

    private function makeField(&$additionalFields, $fieldName, $taskInfo, $size = 30)
    {
        // Write the code for the field
        $fieldID = 'field_'.$fieldName;
        // Note: Name qualifier MUST be "tx_scheduler" as the tx_scheduler's BE module is used!
        $fieldCode = '<input type="text" name="tx_scheduler['.$fieldName.']" id="'.$fieldID.'" value="'.$taskInfo[$fieldName].'" size="'.$size.'" />';
        $additionalFields[$fieldID] = [
            'code' => $fieldCode,
            'label' => 'LLL:EXT:dflsync/Resources/Private/Language/locallang_db.xlf:scheduler_syncTask_field_'.$fieldName,
            'cshKey' => '_MOD_web_txschedulerM1',
            // 'cshLabel' => $fieldID
        ];
    }

    /**
     * This method checks any additional data that is relevant to the specific task
     * If the task class is not relevant, the method is expected to return true.
     *
     * @param array $submittedData:
     *            reference to the array containing the data submitted by the user
     * @param tx_scheduler_Module $parentObject:
     *            reference to the calling object (Scheduler's BE module)
     *
     * @return bool True if validation was ok (or selected class is not relevant), false otherwise
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $parentObject)
    {
        return true;
    }

    /**
     * This method is used to save any additional input into the current task object
     * if the task class matches.
     *
     * @param array $submittedData array containing the data submitted by the user
     * @param ProfileTask $task reference to the current task object
     *
     * @return void
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task)
    {
        $task->setCompetition($submittedData[self::FIELD_COMPETITION]);
        $task->setPathClubInfo($submittedData[self::FIELD_PATH_CLUB_INFO]);
        $task->setPidOwn($submittedData[self::FIELD_PID_OWN]);
        $task->setPidOther($submittedData[self::FIELD_PID_OTHER]);
    }
}
