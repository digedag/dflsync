<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2014-2016 Rene Nitzsche (rene@system25.de)
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
tx_rnbase::load('tx_rnbase_util_Strings');


/**
 *
 */
class Tx_Dflsync_Service_Sync {
	const TABLE_GAMES = 'tx_cfcleague_games';
	const TABLE_TEAMS = 'tx_cfcleague_teams';
	const TABLE_STADIUMS = 'tx_cfcleague_stadiums';
	const TABLE_COMPETITION = 'tx_cfcleague_competition';

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
	private $pageUid = 0;

	private $pathMatchStats = '';
	private $pathMatchInfo = '';

	private $stats = array();

	public function doSync($competitionUid, $fileSaison, $fileClub, $pathStats, $pathInfo) {
		$this->pathMatchInfo = $pathInfo;
		$this->pathMatchStats= $pathStats;

		$fileSaison = $this->getFileName($fileSaison);
		$fileClub = $this->getFileName($fileClub);
		$competition = tx_rnbase::makeInstance('tx_cfcleague_models_Competition', $competitionUid);
		$this->pageUid = $competition->record['pid'];
		$this->initMatches($competition);

		// Dateien lesen
		$reader = new XMLReader();
		if(!$reader->open($fileSaison, 'UTF-8', 0)) {
			tx_rnbase_util_Logger::fatal('Error reading match schedule xml string!', 'dflsync', $fileSaison);
			throw new Exception('Error reading xml string!');
		}
		while ($reader->read() && $reader->name !== 'PlayingSchedule');

		// Jetzt die Teams aus dem XML einlesen
		$this->initXmlTeams($fileClub);

		$start = microtime(TRUE);
		$cnt = 0;
		$info = array(
				'match'=>array('new'=>0, 'updated'=>0),
				'team'=>array('new'=>0, 'updated'=>0),
				'stadium'=>array('new'=>0, 'updated'=>0),
		);
		$data = array(
				self::TABLE_TEAMS => array(),
				self::TABLE_STADIUMS => array(),
				self::TABLE_GAMES => array(),
				self::TABLE_COMPETITION => array(),
		);
		$doc = new DOMDocument();
		while ($reader->name === 'PlayingSchedule') {
			try {
				$node = $reader->expand();
				if ($node === FALSE || !$node instanceof DOMNode) {
					throw new LogicException('The current DOMNode PlayingSchedule is invalid. Last error: '.print_r(error_get_last(), true), 1353594857);
				}
				/* @var $matchNode tx_rnbase_util_XmlElement */
				$matchNode = simplexml_import_dom(
						$doc->importNode($node, true),
						'tx_rnbase_util_XmlElement'
				);
				// Es interessieren hier nur die Spiele ohne das Attribut ValidTo
				if($matchNode->getValueFromPath('CompetitionId') == 'DFL-COM-000002'
						&& !$matchNode->hasValueForPath('ValidTo')) {
					$cnt++;
					$this->handleMatch($data, $matchNode, $competition, $info);
					if($cnt % 120 == 0) {
						// Speichern
						$this->persist($data);
						// Wettbewerb neu laden, da ggf. neue Teams drin stehen
						$competition->reset();
					}
				}
			}
			catch(Exception $e) {
				tx_rnbase_util_Logger::fatal('Error reading PlayingSchedule!', 'dflsync', array('msg'=>$e->getMessage()));
			}
			$reader->next('PlayingSchedule');
		}
		// Die restlichen Spiele speichern
		$this->persist($data);
		$this->stats['total']['time'] = intval(microtime(true) - $start).'s';
		$this->stats['total']['matches'] = $cnt;

		tx_rnbase_util_Logger::info('Update match schedule finished!', 'dflsync', array('stats'=>$this->stats, 'info'=>$info));

//		tx_rnbase_util_Debug::debug(array('Spiele'=>$cnt, 'File'=>$fileSaison, 'Valid'=>$reader->isValid()), __FILE__.' : '.__LINE__); // TODO: remove me
//		tx_rnbase_util_Debug::debug($reader, __FILE__.' : '.__LINE__); // TODO: remove me
		$reader->close();

	}
	private function persist(&$data) {
		$start = microtime(TRUE);

		$tce = tx_rnbase_util_DB::getTCEmain($data);
		$tce->process_datamap();

		$this->stats['chunks'][]['time'] = intval(microtime(true) - $start).'s';
		$this->stats['chunks'][]['matches'] = count($data[self::TABLE_GAMES]);

		$data[self::TABLE_TEAMS] = array();
		$data[self::TABLE_STADIUMS] = array();
		$data[self::TABLE_GAMES] = array();
		$data[self::TABLE_COMPETITION] = array();
	}
	private function handleMatch(&$data, tx_rnbase_util_XmlElement $node, $competition, &$info) {
		// Das Spiel suchen und ggf. anlegen
		$dflId = $node->getValueFromPath('MatchId');
		$matchUid = 'NEW_'.$dflId;
		if(array_key_exists($dflId, $this->matchMap)) {
			$matchUid = $this->matchMap[$dflId];
			$info['match']['updated'] += 1;
		}
		else
			$info['match']['new'] += 1;

		$data[self::TABLE_GAMES][$matchUid]['pid'] = $this->pageUid;
		$data[self::TABLE_GAMES][$matchUid]['extid'] = $dflId;
		$data[self::TABLE_GAMES][$matchUid]['competition'] = $competition->getUid();
		$data[self::TABLE_GAMES][$matchUid]['round'] = $node->getValueFromPath('MatchDay');
		$data[self::TABLE_GAMES][$matchUid]['round_name'] = $node->getValueFromPath('MatchDay') . '. Spieltag';
		// Es muss ein lokaler Timestamp gesetzt werden
		$kickoff = $node->getDateTimeFromPath('PlannedKickOffTime');
		$data[self::TABLE_GAMES][$matchUid]['date'] = ($kickoff->getTimestamp() +$kickoff->getOffset());
		$data[self::TABLE_GAMES][$matchUid]['stadium'] = $node->getValueFromPath('StadiumName');
		$data[self::TABLE_GAMES][$matchUid]['home'] = $this->findTeam($node->getValueFromPath('HomeTeamId'), $data, $competition);
		$data[self::TABLE_GAMES][$matchUid]['guest'] = $this->findTeam($node->getValueFromPath('GuestTeamId'), $data, $competition);

		// Ergebnis holen
		$this->checkMatchStats($data, $matchUid, $dflId);
		// Zuschauer holen
		$this->checkMatchInfo($data, $matchUid, $dflId);
	}

	/**
	 * Lesen der ggf. vorhandenen Datei für die Spiel-Statistik. Hier befindet sich
	 * das Ergebnis.
	 * @param array $data
	 * @param int $matchUid
	 * @param string$dflId
	 */
	private function checkMatchStats(&$data, $matchUid, $dflId) {
		$prefix = 'DFL_03_03_events_matchstatistics_periods_DFL-COM-000002_';
		$statsFile = tx_rnbase_util_Files::join($this->pathMatchStats, $prefix.$dflId.'.xml');
		if(!file_exists($statsFile))
			return;

		// Dateien lesen
		$reader = new XMLReader();
		if(!$reader->open($statsFile, 'UTF-8', 0)) {
			tx_rnbase_util_Logger::fatal('Error reading match stats '.$dflId.'.xml file!', 'dflsync');
			throw new Exception('Error reading match statistics '.$dflId.'.xml!');
		}
		while ($reader->read() && $reader->name !== 'MatchStatistic');

		$doc = new DOMDocument();
		$found = 0;
		while ($reader->name === 'MatchStatistic' && $found < 2) {
			// Wir müssen den Tag mit dem Scope match suchen

			$node = $reader->expand();
			if ($node === FALSE || !$node instanceof DOMNode) {
				throw new LogicException('The current DOMNode MatchStatistic is invalid. File ['.$statsFile.'] Last error: '.print_r(error_get_last(), true), 1353592747);
			}
			/* @var $envNode tx_rnbase_util_XmlElement */
			$envNode = simplexml_import_dom(
					$doc->importNode($node, true),
					'tx_rnbase_util_XmlElement'
			);
			$scope = $envNode->getValueFromPath('Scope');
			if($scope == 'match') {
				$data[self::TABLE_GAMES][$matchUid]['status'] = 2;
				if($result = $envNode->getValueFromPath('Result')) {
					$result = tx_rnbase_util_Strings::intExplode(':', $result);
					$data[self::TABLE_GAMES][$matchUid]['goals_home_2'] = $result[0];
					$data[self::TABLE_GAMES][$matchUid]['goals_guest_2'] = $result[1];
				}
				$found++;
			}
			elseif($scope == 'firstHalf') {
				// Halbzeitergebnis
				if($result = $envNode->getValueFromPath('Result')) {
					$result = tx_rnbase_util_Strings::intExplode(':', $result);
					$data[self::TABLE_GAMES][$matchUid]['goals_home_1'] = $result[0];
					$data[self::TABLE_GAMES][$matchUid]['goals_guest_1'] = $result[1];
				}
				$found++;
			}
			$reader->next('MatchStatistic');
		}
		$reader->close();
	}


	/**
	 * Lesen der ggf. vorhandenen Datei für die MatchInfo
	 * @param array $data
	 * @param int $matchUid
	 * @param string$dflId
	 */
	private function checkMatchInfo(&$data, $matchUid, $dflId) {
		$prefix = 'DFL_02_01_matchinformation_DFL-COM-000002_';
		$infoFile = tx_rnbase_util_Files::join($this->pathMatchInfo, $prefix.$dflId.'.xml');
		if(!file_exists($infoFile))
			return;

		// Dateien lesen
		$reader = new XMLReader();
		if(!$reader->open($infoFile, 'UTF-8', 0)) {
			tx_rnbase_util_Logger::fatal('Error reading match info '.$dflId.'.xml file!', 'dflsync');
			throw new Exception('Error reading match information '.$dflId.'.xml!');
		}
		while ($reader->read() && $reader->name !== 'Environment');

		$doc = new DOMDocument();
		if ($reader->name === 'Environment') {
			// Hier wird nur ein Tag ausgelesen
			$node = $reader->expand();
			if ($node === FALSE || !$node instanceof DOMNode) {
				throw new LogicException('The current DOMNode Environment is invalid. File ['.$infoFile.'] Last error: '.print_r(error_get_last(), true), 1353593847);
			}
			/* @var $envNode tx_rnbase_util_XmlElement */
			$envNode = simplexml_import_dom(
					$doc->importNode($node, true),
					'tx_rnbase_util_XmlElement'
			);
			$visitors = $envNode->getIntFromPath('NumberOfSpectators');
			if($visitors > 0) {
				$data[self::TABLE_GAMES][$matchUid]['visitors'] = $visitors;
			}
		}
		$reader->close();
	}
	/**
	 * Liefert die UID des Teams, oder einen NEW_-Key
	 * @param string $dflId
	 * @return string
	 */
	private function findTeam($dflId, &$data, $competition) {
		$uid = 'NEW_'.$dflId;
		if(!array_key_exists($dflId, $this->teamMap)) {
			// Das Team ist noch nicht im Cache, also in der DB suchen
			/* @var $teamSrv tx_cfcleague_services_Teams */
			$teamSrv = tx_cfcleague_util_ServiceRegistry::getTeamService();
			$fields = array();
			$fields['TEAM.EXTID'][OP_EQ_NOCASE] = $dflId;
			$options = array(
					'what' => 'uid',
			);
			$ret = $teamSrv->searchTeams($fields, $options);
			if(!empty($ret)) {
				$this->teamMap[$dflId] = $ret[0]['uid'];
				$uid = $this->teamMap[$dflId];
			}
			else {
				// Team anlegen, falls es noch nicht in der Data-Map liegt
				if(!array_key_exists($uid, $data[self::TABLE_TEAMS])) {
					$data[self::TABLE_TEAMS][$uid] = $this->loadTeamData($dflId);
				}
			}
			// Sicherstellen, daß das Team im Wettbewerb ist
			$this->addTeamToCompetition($uid, $data, $competition);
		}
		else {
			$uid = $this->teamMap[$dflId];
		}
		return $uid;
	}
	/**
	 * Stellt sicher, daß das Team im Wettbewerb gespeichert wird.
	 * @param mixed $teamUid
	 * @param array $data
	 * @param tx_cfcleague_models_Competition $competition
	 */
	private function addTeamToCompetition($teamUid, &$data, $competition) {
		$add = TRUE;
		if($competition->record['teams']) {
			$teamUids = array_flip( tx_rnbase_util_Strings::intExplode(',', $competition->record['teams']));
			$add = !(array_key_exists($teamUid, $teamUids));
		}
		if(!$add)
			return;
		// Das geht bestimmt auch kürzer...
		// Das Team in den Wettbewerb legen
		if(isset($data[self::TABLE_COMPETITION][$competition->getUid()]['teams'])) {
			$data[self::TABLE_COMPETITION][$competition->getUid()]['teams'] .= ','.$teamUid;
		}
		else {
			// Das erste Team
			if($competition->record['teams']) {
				$data[self::TABLE_COMPETITION][$competition->getUid()]['teams'] = $competition->record['teams'];
				$data[self::TABLE_COMPETITION][$competition->getUid()]['teams'] .= ','.$teamUid;
			}
			else
				$data[self::TABLE_COMPETITION][$competition->getUid()]['teams'] = $teamUid;
		}
		tx_rnbase_util_Logger::info('Team with id ' . $teamUid . ' added to competition', 'dflsync',
						array('competition'=>$competition->getUid(), 'existing'=>$competition->record['teams']));
	}

	private function loadTeamData($dflId) {
		if(array_key_exists($dflId, $this->teamData)) {
			return $this->teamData[$dflId];
		}
		throw new Exception('Team not found: ' . $dflId);

	}

	private function initXmlTeams($fileClub) {
		$reader = new XMLReader();
		if(!$reader->open($fileClub, 'UTF-8', 0)) {
			tx_rnbase_util_Logger::fatal('Error reading team data xml string!', 'dflsync', $fileClub);
			throw new Exception('Error reading xml string!');
		}
		while ($reader->read() && $reader->name !== 'Club');

		$doc = new DOMDocument();
		while ($reader->name === 'Club') {
			try {
				$node = $reader->expand();
				if ($node === FALSE || !$node instanceof DOMNode) {
					throw new LogicException('The current DOMNode is invalid. Last error: '.print_r(error_get_last(), true), 1353594857);
				}
				/* @var $clubNode tx_rnbase_util_XmlElement */
				$clubNode = simplexml_import_dom(
						$doc->importNode($node, true),
						'tx_rnbase_util_XmlElement'
				);
				// Es interessieren hier nur die Teams ohne das Attribut ValidTo
				if(!$clubNode->hasValueForPath('ValidTo')) {
					$this->teamData[$clubNode->getValueFromPath('ClubId')] = array(
							'pid' => $this->pageUid,
							'extid' => $clubNode->getValueFromPath('ClubId'),
							'name' => $clubNode->getValueFromPath('LongName'),
							'short_name' => $clubNode->getValueFromPath('ShortName'),
							'tlc' => $clubNode->getValueFromPath('ThreeLetterCode'),
					);
					$this->clubData[$clubNode->getValueFromPath('ClubId')] = array(
							'extid' => $clubNode->getValueFromPath('ClubId'),
							'name' => $clubNode->getValueFromPath('Name'),
							'short_name' => $clubNode->getValueFromPath('ShortName'),
							'email' => $clubNode->getValueFromPath('Mail'),
							'www' => $clubNode->getValueFromPath('Website'),
							'street' => trim($clubNode->getValueFromPath('Street'). ' '. $clubNode->getValueFromPath('HouseNumber')),
							'zip' => $clubNode->getValueFromPath('PostalCode'),
							'city' => $clubNode->getValueFromPath('City'),
							'email' => $clubNode->getValueFromPath('Mail'),
							'yearestablished' => $clubNode->getValueFromPath('Founded'),
					);


				}
			}
			catch(Exception $e) {
				tx_rnbase_util_Logger::fatal('Error reading PlayingSchedule!', 'dflsync', $e->getMessage());
			}
			$reader->next('Club');
		}
	}

	/**
	 * Lädt die vorhandenen Spiele des Wettbewerbs in die matchMap
	 * @param tx_cfcleague_models_Competition $competition
	 */
	private function initMatches(tx_cfcleague_models_Competition $competition) {
		$fields = array();
		$options = array();
		/* @var $matchSrv tx_cfcleague_services_Match */
		$matchSrv = tx_cfcleague_util_ServiceRegistry::getMatchService();
		$fields['MATCH.COMPETITION'][OP_EQ_INT] = $competition->getUid();
		$options['what'] = 'uid,extid';
		$options['orderby'] = 'uid asc';
		$options['callback'] = array($this, 'cbAddMatch');
		$matchSrv->search($fields, $options);
		// TODO: Validierung??
	}
	public function cbAddMatch($record) {
		$this->matchMap[$record['extid']] = $record['uid'];
	}

	private function getFileName($filename) {
		$filename = tx_rnbase_util_Files::getFileAbsFileName($filename, FALSE);
		if(!is_file($filename)) {
			throw new Exception('File not found: ' . $filename);
		}
		if(!@is_readable($filename)) {
			throw new Exception('File is not readable: '.$filename);
		}
		return $filename;
	}
}


