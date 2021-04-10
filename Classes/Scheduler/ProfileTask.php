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
 * Import von Spielern und Trainern.
 */
class Tx_Dflsync_Scheduler_ProfileTask extends tx_scheduler_Task
{
    /**
     * Amount of items to be indexed at one run.
     *
     * @var int
     */
    private $competition;

    private $pathClubInfo;

    private $pidOwn;

    private $pidOther;

    /**
     * Function executed from the Scheduler.
     * Sends an email.
     *
     * @return void
     */
    public function execute()
    {
        $success = true;

        try {
            $sync = tx_rnbase::makeInstance('Tx_Dflsync_Service_ProfileImport');
            $sync->doImport($this->competition, $this->pathClubInfo, $this->pidOwn, $this->pidOther);
        } catch (Exception $e) {
            tx_rnbase_util_Logger::fatal('Task failed!', 'dflsync', [
                'Exception' => $e->getMessage(),
            ]);
            // Da die Exception gefangen wird, würden die Entwickler keine Mail bekommen
            // also machen wir das manuell
            if ($addr = tx_rnbase_configurations::getExtensionCfgValue('rn_base', 'sendEmailOnException')) {
                tx_rnbase::load('tx_rnbase_util_Misc');
                tx_rnbase_util_Misc::sendErrorMail($addr, 'Tx_Dflsync_Scheduler_SyncTask', $e);
            }
            $success = false;
        }

        return $success;
    }

    /**
     * @return int
     */
    public function getCompetition()
    {
        return $this->competition;
    }

    /**
     * Set amount of items.
     *
     * @param int $val
     *
     * @return void
     */
    public function setCompetition($val)
    {
        if (!intval($val)) {
            throw new Exception('tx_dflsync_scheduler_SyncTask->setCompetition(): Invalid Competition given!');
        }
        // else
        $this->competition = intval($val);
    }

    /**
     * Das Verzeichnis mit den Dateien für die Spielinformationen
     * DFL-MAT-0002MV.xml.
     */
    public function getPathClubInfo()
    {
        return $this->pathClubInfo;
    }

    public function setPathClubInfo($val)
    {
        $this->pathClubInfo = $val;
    }

    public function getPidOwn()
    {
        return $this->pidOwn;
    }

    public function setPidOwn($val)
    {
        $this->pidOwn = $val;
    }

    public function getPidOther()
    {
        return $this->pidOther;
    }

    public function setPidOther($val)
    {
        $this->pidOther = $val;
    }

    /**
     * This method returns the destination mail address as additional information.
     *
     * @return string Information to display
     */
    public function getAdditionalInformation()
    {
        $compName = '';

        if ($compUid = $this->getCompetition()) {
            $competition = tx_rnbase::makeInstance('tx_cfcleague_models_Competition', $compUid);
            $compName = $competition->isValid() ? $competition->getName() : '[invalid!]';
        }

        return sprintf('Aktualisierung der Spieler für Wettbewerb >%s<', $compName);
        // return sprintf( $GLOBALS['LANG']->sL('LLL:EXT:mksearch/locallang_db.xml:scheduler_indexTask_taskinfo'),
        // $this->getTargetPath(), $this->getItemsInQueue());
    }
}
