<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2017 Rene Nitzsche (rene@system25.de)
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
tx_rnbase::load('tx_rnbase_util_Logger');
tx_rnbase::load('tx_rnbase_util_Files');
tx_rnbase::load('tx_rnbase_util_XmlElement');

class Tx_Dflsync_Service_ProfileImport
{
    const TABLE_TEAMS = 'tx_cfcleague_teams';

    const TABLE_COMPETITION = 'tx_cfcleague_competition';

    const TABLE_PROFILES = 'tx_cfcleague_profiles';

    /**
     * Key ist DFL-ID, value ist T3-UID.
     */
    private $teamMap = [];

    /**
     * Key ist DFL-ID, value ist T3-UID.
     */
    private $matchMap = [];

    /**
     * Daten aus dem XML für die Teams.
     */
    private $teamData = [];

    /**
     * Daten aus dem XML für die Vereine.
     */
    private $clubData = [];

    private $pageOwn = 0;

    private $pageOther = 0;

    // private $pathMatchStats = '';
    private $pathClubInfo = '';

    private $stats = [];

    /**
     * Start synchronization of profiles in given competition.
     *
     * @param int $competitionUid
     * @param string $pathInfo
     * @param int $pidOwn
     * @param int $pidOther
     */
    public function doImport($competitionUid, $pathInfo, $pidOwn, $pidOther)
    {
        $this->pathClubInfo = $pathInfo;
        $this->pageOwn = $pidOwn;
        $this->pageOther = $pidOther;

        $start = microtime(true);

        /* @var $competition tx_cfcleague_models_Competition */
        $competition = tx_rnbase::makeInstance('tx_cfcleague_models_Competition', $competitionUid);
        $teams = $competition->getTeams();
        foreach ($teams as $team) {
            $this->refreshTeam($team);
        }
        $this->stats['total']['time'] = intval(microtime(true) - $start).'s';
        tx_rnbase_util_Logger::info('Update profiles finished!', 'dflsync', [
            'stats' => $this->stats,
        ]);
    }

    /**
     * @param tx_cfcleague_models_Team $team
     */
    protected function refreshTeam($team)
    {
        $dflId = $team->getExtid();
        if (!$dflId) {
            tx_rnbase_util_Logger::notice('Ignore team '.$team->getNameShort().' ('.$team->getUid().') without extid!', 'dflsync');

            return;
        }
        $data = [
            self::TABLE_PROFILES => [],
            self::TABLE_TEAMS => [],
        ];
        $club = $team->getClub();
        $pid = null != $club && $club->isFavorite() ? $this->pageOwn : $this->pageOther;

        if (0 == $pid) {
            tx_rnbase_util_Logger::notice('Ignore team '.$team->getNameShort().' ('.$team->getUid().')! No PID configured.', 'dflsync', [
                'pageOwn' => $this->pageOwn,
                'pageOther' => $this->pageOther,
            ]);

            return;
        }

        $this->checkPlayers($data, $team, $dflId, $pid);
        $this->checkCoaches($data, $team, $dflId, $pid);

        if (!empty($data[self::TABLE_PROFILES])) {
            $this->persist($data);
        }
        // tx_rnbase_util_Debug::debug($data, $team->getNameShort().' ' .__FILE__.' : '.__LINE__); // TODO: remove me
    }

    /**
     * Lesen der ggf.
     * vorhandenen Datei für die Spieler des Teams.
     *
     * @param array $data
     * @param tx_cfcleague_models_Team $team
     * @param
     *            string$dflId
     */
    private function checkCoaches(&$data, $team, $dflId, $pid)
    {
//         $prefix = 'DFL_01_05_masterdata_';
//         $feedFile = tx_rnbase_util_Files::join($this->pathClubInfo, $prefix . $dflId . '_teamofficial.xml');
        $feedFile = sprintf($this->pathClubInfo, $dflId, 'teamofficial');
        if (!file_exists($feedFile)) {
            tx_rnbase_util_Logger::warn('Ignore team '.$team->getNameShort().' ('.$team->getUid().')! No officials feed file found.', 'dflsync', [
                'file' => $feedFile,
            ]);

            return;
        }

        // Person aus der Datei lesen
        $dflProfiles = $this->readProfiles($feedFile);
        // Neue Trainer zuordnen
        $profileMap = [];
        $profiles = $team->getCoaches();
        foreach ($profiles as $profile) {
            if ($profile->getExtid()) {
                $profileMap[$profile->getExtid()] = $profile->getUid();
            }
        }

        $newProfileIds = [];
        foreach ($dflProfiles as $dflProfile) {
            if (array_key_exists($dflProfile['extid'], $profileMap)) {
                continue;
            }
            // Nur Trainer übernehmen
            if (!$this->isCoach($dflProfile['type'])) {
                continue;
            }
            unset($dflProfile['type']);
            $newProfileIds[] = 'NEW_'.$dflProfile['extid'];
            $data[self::TABLE_PROFILES]['NEW_'.$dflProfile['extid']] = $dflProfile;
            $data[self::TABLE_PROFILES]['NEW_'.$dflProfile['extid']]['pid'] = $pid;
        }
        if (!empty($newProfileIds)) {
            // Im Team zuordnen
            if ($team->getProperty('coaches')) {
                $data[self::TABLE_TEAMS][$team->getUid()]['coaches'] = $team->getProperty('coaches').','.implode(',', $newProfileIds);
            } else {
                $data[self::TABLE_TEAMS][$team->getUid()]['coaches'] = implode(',', $newProfileIds);
            }
        }
    }

    protected function isCoach($type)
    {
        $coachTypes = [
            'headcoach',
            'assistantHeadcoach',
        ];

        return in_array($type, $coachTypes);
    }

    /**
     * Lesen der ggf.
     * vorhandenen Datei für die Spieler des Teams.
     *
     * @param array $data
     * @param tx_cfcleague_models_Team $team
     * @param
     *            string$dflId
     */
    private function checkPlayers(&$data, $team, $dflId, $pid)
    {
//         $prefix = 'DFL_01_05_masterdata_';
//         $feedFile = tx_rnbase_util_Files::join($this->pathClubInfo, $prefix . $dflId . '_player.xml');
        $feedFile = sprintf($this->pathClubInfo, $dflId, 'player');
        if (!file_exists($feedFile)) {
            tx_rnbase_util_Logger::warn('Ignore team '.$team->getNameShort().' ('.$team->getUid().')! No player feed file found.', 'dflsync', [
                'file' => $feedFile,
            ]);

            return;
        }

        // Spieler aus Team einlesen
        $profileMap = [];
        $profiles = $team->getPlayers();
        foreach ($profiles as $profile) {
            if ($profile->getExtid()) {
                $profileMap[$profile->getExtid()] = $profile->getUid();
            }
        }

        // Spieler aus der Datei lesen
        $dflProfiles = $this->readProfiles($feedFile);

        // Fehlende Spieler suchen
        $newPlayerIds = [];
        foreach ($dflProfiles as $dflProfile) {
            if (array_key_exists($dflProfile['extid'], $profileMap)) {
                continue; // Ist schon im Team
            }
            // Der Spieler ist noch nicht im Team
            $newPlayerId = 'NEW_'.$dflProfile['extid'];
            // Gibt es in schon in der Datenbank?
            $existingPlayerUid = $this->findPlayerByDflId($dflProfile['extid']);
            if ($existingPlayerUid) {
                $newPlayerId = $existingPlayerUid;
            } else {
                // Spieler neu anlegen
                unset($dflProfile['type']);
                $data[self::TABLE_PROFILES][$newPlayerId] = $dflProfile;
                $data[self::TABLE_PROFILES][$newPlayerId]['pid'] = $pid;
            }
            $newPlayerIds[] = $newPlayerId;
        }
        if (!empty($newPlayerIds)) {
            // Im Team zuordnen
            if ($team->getProperty('players')) {
                $data[self::TABLE_TEAMS][$team->getUid()]['players'] = $team->getProperty('players').','.implode(',', $newPlayerIds);
            } else {
                $data[self::TABLE_TEAMS][$team->getUid()]['players'] = implode(',', $newPlayerIds);
            }
        }
    }

    /**
     * @param string $dflId
     *
     * @return int or NULL
     */
    protected function findPlayerByDflId($dflId)
    {
        $srv = tx_cfcleague_util_ServiceRegistry::getProfileService();
        $fields = [];
        $fields['PROFILE.EXTID'][OP_EQ_NOCASE] = $dflId;
        $options = [
            'what' => 'uid',
        ];
        $ret = $srv->search($fields, $options);

        return empty($ret) ? null : $ret[0]['uid'];
    }

    /**
     * Liest ein XML ein.
     *
     * @param string $file
     */
    protected function readProfiles($file)
    {
        $reader = new XMLReader();
        if (!$reader->open($file, 'UTF-8', 0)) {
            tx_rnbase_util_Logger::fatal('Error reading profile feed '.$file.'!', 'dflsync');
            throw new Exception('Error reading profile feed '.$file.' !');
        }
        while ($reader->read() && 'Object' !== $reader->name);

        $doc = new DOMDocument();
        $profiles = [];
        while ('Object' === $reader->name) {
            $node = $reader->expand();
            if (false === $node || !$node instanceof DOMNode) {
                throw new LogicException('The current DOMNode Object is invalid. File ['.$file.'] Last error: '.print_r(error_get_last(), true), 1353542747);
            }
            /* @var $envNode tx_rnbase_util_XmlElement */
            $envNode = simplexml_import_dom($doc->importNode($node, true), 'tx_rnbase_util_XmlElement');
            // Es interessieren hier nur die Daten ohne das Attribut ValidTo
            if (!$envNode->hasValueForPath('ValidTo')) {
                $profile = [];
                foreach (self::$fieldMap as $dflField => $t3sField) {
                    $value = $envNode->getValueFromPath($dflField);
                    if ($value && 'null' != $value) {
                        if ('BirthDate' == $dflField) {
                            $value = $envNode->getDateTimeFromPath($dflField);
                            $profile[$t3sField] = $value->getTimestamp() + $value->getOffset();
                        } else {
                            $profile[$t3sField] = $envNode->getValueFromPath($dflField);
                        }
                    }
                }
                $profiles[] = $profile;
            }
            $reader->next('Object');
        }

        return $profiles;
    }

    private function persist(&$data)
    {
        $start = microtime(true);

        $tce = Tx_Rnbase_Database_Connection::getInstance()->getTCEmain($data);
        $tce->process_datamap();

        $this->stats['chunks'][]['time'] = intval(microtime(true) - $start).'s';
        $this->stats['chunks'][]['profiles'] = count($data[self::TABLE_PROFILES]);

        $data[self::TABLE_TEAMS] = [];
        $data[self::TABLE_PROFILES] = [];
    }

    public static $fieldMap = [
        'ObjectId' => 'extid',
        'FirstName' => 'first_name',
        'LastName' => 'last_name',
        'FirstNationality' => 'nationality',
        'BirthDate' => 'birthday',
        'BirthPlace' => 'native_town',
        'PlayingPosition' => 'position',
        'Type' => 'type',
    ];
}
/*
<Object ObjectId="DFL-OBJ-0002HP" DlProviderId="p191839" Valid="true"
Type="PLAYER" Name="Dominik Machmeier" FirstName="Dominik"
LastName="Machmeier" FirstNationality="Deutsch"
ShirtNumber="36" PlayingPosition="Tor" BirthDate="03.11.1995"
BirthPlace="null" ClubId="DFL-CLU-000012" ClubName="SV Sandhausen" />
*/
