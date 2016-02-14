<?php
/**
 Copyright 2016 Myers Enterprises II

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 */

namespace com_brucemyers\TemplateParamBot;

use com_brucemyers\Util\L10N;
use PDO;

class UIHelper
{
	protected $serviceMgr;
	protected $dbh_tools;

	public function __construct()
	{
		$this->serviceMgr = new ServiceManager();
		$this->dbh_tools = $this->serviceMgr->getDBConnection('tools');
	}

	/**
	 * Get a list of wikis.
	 *
	 * @return array wikiname => array('title', 'domain')
	 */
	public function getWikis()
	{
		$sql = 'SELECT * FROM wikis ORDER BY wikititle';
		$sth = $this->dbh_tools->query($sql);
		$sth->setFetchMode(PDO::FETCH_ASSOC);

		$wikis = array('enwiki' => array('title' => 'English Wikipedia', 'domain' => 'en.wikipedia.org', 'lang' => 'en'));
//			'commonswiki' => array('title' => 'Wikipedia Commons', 'domain' => 'commons.wikimedia.org', 'lang' => 'en')); // Want first

		while ($row = $sth->fetch()) {
			$wikiname = $row['wikiname'];

			$wikis[$wikiname] = array('title' => $row['wikititle'], 'domain' => $row['wikidomain'], 'lang' => $row['lang'],
				'lastdumpdate' => $row['lastdumpdate']);
		}

		return $wikis;
	}

	/**
	 * Get watch list results
	 *
	 * @param array $params
	 * @param int $max_rows
	 * @return array Results, keys = errors - array(), results - array()
	 */
	public function getAllTemplates($params, $max_rows)
	{
		$results = array();
		$errors = array();
		$wikiname = $params['wiki'];

		$page = $params['page'];
		$page = $page - 1;
		if ($page < 0 || $page > 1000) $page = 0;
		$offset = $page * $max_rows;

		$sql = "SELECT * FROM {$wikiname}_templates ORDER BY instance_count DESC LIMIT $offset,$max_rows";
		$sth = $this->dbh_tools->query($sql);
		$sth->setFetchMode(PDO::FETCH_ASSOC);

		while ($row = $sth->fetch()) {
			$results[] = $row;
		}

		return array('errors' => $errors, 'results' => $results);
	}

	/**
	 * Get watch list results
	 *
	 * @param array $params
	 * @return array Results, keys = errors - array(), info - array('page_count', 'instance_count', 'TemplateData', 'params' => array())
	 */
	public function getTemplate($params)
	{
		$info = array();
		$errors = array();
		$wikiname = $params['wiki'];

		$sth = $this->dbh_tools->prepare("SELECT * FROM {$wikiname}_templates WHERE `name` = ?");
		$sth->execute(array($params['template']));

		if ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
			$info['page_count'] = $row['page_count'];
			$info['instance_count'] = $row['instance_count'];
			$tmplid = $row['id'];
			$info['params'] = array();

			$sql = "SELECT * FROM {$wikiname}_totals WHERE template_id = $tmplid ORDER BY param_name";
			$sth = $this->dbh_tools->query($sql);
			$sth->setFetchMode(PDO::FETCH_ASSOC);

			while ($row = $sth->fetch()) {
				$info['params'][] = $row;
			}

			// Fetch the TemplateData
			$dbh_wiki = $this->serviceMgr->getDBConnection($wikiname);
			$sql = "SELECT pp_value FROM page_props WHERE pp_page = $tmplid AND pp_propname = 'templatedata'";
			$sth = $dbh_wiki->query($sql);
			if ($row = $sth->fetch(PDO::FETCH_NUM)) {
				$info['TemplateData'] = new TemplateData($row[0]);
			} else {
				$wikis = $this->getWikis();
				$l10n = new L10N($wikis[$wikiname]['lang']);
				$errors[] = htmlentities($l10n->get('templatedatanotfound', true), ENT_COMPAT, 'UTF-8');
			}
		} else {
			$wikis = $this->getWikis();
			$l10n = new L10N($wikis[$wikiname]['lang']);
			$errors[] = htmlentities($l10n->get('templatenotfound', true), ENT_COMPAT, 'UTF-8');
		}

		return array('errors' => $errors, 'info' => $info);
	}
}