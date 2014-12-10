<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2014 Rene Nitzsche (rene@system25.de)
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


require_once(t3lib_extMgm::extPath('rn_base') . 'class.tx_rnbase.php');
tx_rnbase::load('tx_rnbase_util_Logger');
tx_rnbase::load('tx_rnbase_util_Files');
tx_rnbase::load('tx_rnbase_util_XmlElement');
tx_rnbase::load('tx_rnbase_util_Strings');


/**
 *
 */
class Tx_Dflsync_Service_ProfileImport {
	const TABLE_TEAMS = 'tx_cfcleague_teams';
	const TABLE_COMPETITION = 'tx_cfcleague_competition';
	const TABLE_PROFILES = 'tx_cfcleague_profiles';

	/**
	 * Key ist DFL-ID, value ist T3-UID
	 */
	private $teamMap = array();
	/**
	 * Key ist DFL-ID, value ist T3-UID
	 */
	private $matchMap = array();

	/**
	 * Daten aus dem XML für die Teams
	 */
	private $teamData = array();
	/**
	 * Daten aus dem XML für die Vereine
	 */
	private $clubData = array();
	private $pageOwn = 0;
	private $pageOther = 0;

//	private $pathMatchStats = '';
	private $pathClubInfo = '';

	private $stats = array();

	public function doImport($competitionUid, $pathInfo, $pidOwn, $pidOther) {
		$this->pathClubInfo = $pathInfo;
		$this->pageOwn = $pidOwn;
		$this->pageOther = $pidOther;

		$start = microtime(TRUE);

		/* @var $competition tx_cfcleague_models_Competition */
		$competition = tx_rnbase::makeInstance('tx_cfcleague_models_Competition', $competitionUid);
		$teams = $competition->getTeams();
		foreach($teams As $team) {
			$this->refreshTeam($team);
		}
		$this->stats['total']['time'] = intval(microtime(true) - $start).'s';
		tx_rnbase_util_Logger::info('Update profiles finished!', 'dflsync', array('stats'=>$this->stats));
	}
	/**
	 *
	 * @param tx_cfcleague_models_Team $team
	 */
	protected function refreshTeam($team) {
		$dflId = $team->getExtid();
		if(!$dflId) {
			tx_rnbase_util_Logger::notice('Ignore team '.$team->getNameShort().' ('.$team->getUid().') without extid!', 'dflsync');
			return;
		}
		$data = array(
			self::TABLE_PROFILES => array(),
			self::TABLE_TEAMS => array(),
		);
		$club = $team->getClub();
		$pid = $club != null && $club->isFavorite() ? $this->pageOwn : $this->pageOther;

		$this->checkPlayers($data, $team, $dflId, $pid);
		$this->checkCoaches($data, $team, $dflId, $pid);

 		if(!empty($data[self::TABLE_PROFILES]))
 			$this->persist($data);
//tx_rnbase_util_Debug::debug($data, $team->getNameShort().' ' .__FILE__.' : '.__LINE__); // TODO: remove me
	}
	/**
	 * Lesen der ggf. vorhandenen Datei für die Spieler des Teams.
	 * @param array $data
	 * @param tx_cfcleague_models_Team $team
	 * @param string$dflId
	 */
	private function checkCoaches(&$data, $team, $dflId, $pid) {
		$feedFile = tx_rnbase_util_Files::join($this->pathClubInfo, $dflId.'_TEAMOFFICIAL.xml');
		if(!file_exists($feedFile)) {
			tx_rnbase_util_Logger::notice('Ignore team '.$team->getNameShort().' ('.$team->getUid().')! No officials feed file found.', 'dflsync',
			array('file' => $feedFile));
			return;
		}

		// Person aus der Datei lesen
		$dflProfiles = $this->readProfiles($feedFile);
		// Neue Spieler zuordnen
		$profileMap = array();
		$profiles = $team->getCoaches();
		foreach ($profiles As $profile) {
			if($profile->getExtid())
				$profileMap[$profile->getExtid()] = $profile->getUid();
		}

		$newProfileIds = array();
		foreach ($dflProfiles As $dflProfile) {
			if(array_key_exists($dflProfile['extid'], $profileMap)) {
				continue;
			}
			// Nur Trainer übernehmen
			if($dflProfile['type'] != 'TRAINER' && $dflProfile['type'] != 'COTRAINER') {
				continue;
			}
			unset($dflProfile['type']);
			$newProfileIds[] = 'NEW_'.$dflProfile['extid'];
			$data[self::TABLE_PROFILES]['NEW_'.$dflProfile['extid']] = $dflProfile;
			$data[self::TABLE_PROFILES]['NEW_'.$dflProfile['extid']]['pid'] = $pid;
		}
		if(!empty($newProfileIds)) {
			// Im Team zuordnen
			if($team->record['coaches'])
				$data[self::TABLE_TEAMS][$team->getUid()]['coaches'] = $team->record['coaches'] .
									',' . implode(',', $newProfileIds);
			else
				$data[self::TABLE_TEAMS][$team->getUid()]['coaches'] = implode(',', $newProfileIds);
		}
	}
	/**
	 * Lesen der ggf. vorhandenen Datei für die Spieler des Teams.
	 * @param array $data
	 * @param tx_cfcleague_models_Team $team
	 * @param string$dflId
	 */
	private function checkPlayers(&$data, $team, $dflId, $pid) {
		$feedFile = tx_rnbase_util_Files::join($this->pathClubInfo, $dflId.'_PLAYER.xml');
		if(!file_exists($feedFile)) {
			tx_rnbase_util_Logger::notice('Ignore team '.$team->getNameShort().' ('.$team->getUid().')! No player feed file found.', 'dflsync',
						array('file' => $feedFile));
			return;
		}
		//Spieler aus der Datei lesen
		$dflProfiles = $this->readProfiles($feedFile);
		// Neue Spieler zuordnen
		$profileMap = array();
		$profiles = $team->getPlayers();
		foreach ($profiles As $profile) {
			if($profile->getExtid())
				$profileMap[$profile->getExtid()] = $profile->getUid();
		}

		$newPlayerIds = array();
		foreach ($dflProfiles As $dflProfile) {
			if(array_key_exists($dflProfile['extid'], $profileMap)) {
				continue;
			}
			$newPlayerIds[] = 'NEW_'.$dflProfile['extid'];
			unset($dflProfile['type']);
			$data[self::TABLE_PROFILES]['NEW_'.$dflProfile['extid']] = $dflProfile;
			$data[self::TABLE_PROFILES]['NEW_'.$dflProfile['extid']]['pid'] = $pid;
		}
		if(!empty($newPlayerIds)) {
			// Im Team zuordnen
			if($team->record['players'])
				$data[self::TABLE_TEAMS][$team->getUid()]['players'] = $team->record['players'] .
									',' . implode(',', $newPlayerIds);
			else
				$data[self::TABLE_TEAMS][$team->getUid()]['players'] = implode(',', $newPlayerIds);
		}
	}

	/**
	 * Liest ein XML ein
	 * @param string $file
	 */
	protected function readProfiles($file) {
		$reader = new XMLReader();
		if(!$reader->open($file, 'UTF-8', 0)) {
			tx_rnbase_util_Logger::fatal('Error reading profile feed '.$file.'!', 'dflsync');
			throw new Exception('Error reading profile feed '.$file.' !');
		}
		while ($reader->read() && $reader->name !== 'Object');

		$doc = new DOMDocument();
		$profiles = array();
		while ($reader->name === 'Object') {
			$node = $reader->expand();
			if ($node === FALSE || !$node instanceof DOMNode) {
				throw new LogicException('The current DOMNode Object is invalid. File ['.$file.'] Last error: '.print_r(error_get_last(), true), 1353542747);
			}
			/* @var $envNode tx_rnbase_util_XmlElement */
			$envNode = simplexml_import_dom(
					$doc->importNode($node, true),
					'tx_rnbase_util_XmlElement'
			);
			// Es interessieren hier nur die Daten ohne das Attribut ValidTo
			if(!$envNode->hasValueForPath('ValidTo')) {
				$profile = array();
				foreach (self::$fieldMap As $dflField => $t3sField) {
					$value = $envNode->getValueFromPath($dflField);
					if($value && $value != 'null') {
						if($dflField == 'BirthDate') {
							$value = $envNode->getDateTimeFromPath($dflField);
							$profile[$t3sField] = $value->getTimestamp() + $value->getOffset();
						}
						else
							$profile[$t3sField] = $envNode->getValueFromPath($dflField);
					}
				}
				$profiles[] = $profile;
			}
			$reader->next('Object');
		}
		return $profiles;
	}
	private function persist(&$data) {
		$start = microtime(TRUE);

		$tce = tx_rnbase_util_DB::getTCEmain($data);
		$tce->process_datamap();

		$this->stats['chunks'][]['time'] = intval(microtime(true) - $start).'s';
		$this->stats['chunks'][]['profiles'] = count($data[self::TABLE_PROFILES]);

		$data[self::TABLE_TEAMS] = array();
		$data[self::TABLE_PROFILES] = array();
	}

	static $fieldMap = array(
			'ObjectId' => 'extid',
			'FirstName' => 'first_name',
			'LastName' => 'last_name',
			'FirstNationality' => 'nationality',
			'BirthDate' => 'birthday',
			'BirthPlace' => 'native_town',
			'PlayingPosition' => 'position',
			'Type' => 'type',
	);
}
/*
<Object ObjectId="DFL-OBJ-0002HP" DlProviderId="p191839" Valid="true"
Type="PLAYER" Name="Dominik Machmeier" FirstName="Dominik"
LastName="Machmeier" FirstNationality="Deutsch"
ShirtNumber="36" PlayingPosition="Tor" BirthDate="03.11.1995"
BirthPlace="null" ClubId="DFL-CLU-000012" ClubName="SV Sandhausen" />
*/


