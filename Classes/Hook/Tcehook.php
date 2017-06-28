<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2017 Rene Nitzsche <rene@system25.de>
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
class Tx_Dflsync_Hook_Tcehook
{

    /**
     * Wir müssen dafür sorgen, daß die neuen IDs der Teams in den Spielen
     * verwendet werden.
     */
    public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, &$tcemain)
    {
        if ($table == 'tx_cfcleague_games') {
            if (strstr($incomingFieldArray['home'], 'NEW')) {
                $incomingFieldArray['home'] = $tcemain->substNEWwithIDs[$incomingFieldArray['home']];
            }
            if (strstr($incomingFieldArray['guest'], 'NEW')) {
                $incomingFieldArray['guest'] = $tcemain->substNEWwithIDs[$incomingFieldArray['guest']];
            }
        }
        if ($table == 'tx_cfcleague_competition') {
            // Neue Teams im Wettbewerb?
            if (strstr($incomingFieldArray['teams'], 'NEW')) {
                tx_rnbase::load('tx_rnbase_util_Strings');
                $newItemIds = tx_rnbase_util_Strings::trimExplode(',', $incomingFieldArray['teams']);
                $itemUids = array();
                for ($i = 0; $i < count($newItemIds); $i ++) {
                    if (strstr($newItemIds[$i], 'NEW'))
                        $itemUid = $tcemain->substNEWwithIDs[$newItemIds[$i]];
                    else
                        $itemUid = $newItemIds[$i];
                    // Wir übernehmen nur UIDs, die gefunden werden
                    if ($itemUid)
                        $itemUids[] = $itemUid;
                }
                $itemUids = array_unique($itemUids);
                $incomingFieldArray['teams'] = implode($itemUids, ',');
            }
        }
    }
}
