<?php
/**
 Copyright 2015 Myers Enterprises II

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

use com_brucemyers\CategoryWatchlistBot\UIHelper;
use com_brucemyers\Util\MySQLDate;
use com_brucemyers\Util\DateUtil;
use com_brucemyers\Util\HttpUtil;

$webdir = dirname(__FILE__);
// Marker so include files can tell if they are called directly.
$GLOBALS['included'] = true;
$GLOBALS['botname'] = 'CategoryWatchlistBot';
define('BOT_REGEX', '!(?:spider|bot[\s_+:,\.\;\/\\\-]|[\s_+:,\.\;\/\\\-]bot)!i');
define('COOKIE_QUERYID', 'catwl:queryid');

require $webdir . DIRECTORY_SEPARATOR . 'bootstrap.php';

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
ini_set("display_errors", 1);

$uihelper = new UIHelper();
$wikis = $uihelper->getWikis();
$params = array();

get_params();

display_form();

/**
 * Display form
 *
 */
function display_form()
{
	global $uihelper, $params, $wikis;
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
	    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	    <meta name="robots" content="noindex, nofollow" />
	    <title>Category Membership / Template Usage Recent Changes</title>
    	<link rel='stylesheet' type='text/css' href='css/catwl.css' />
	    <style>
	        .plusminus {
                text-align: center;
            }
            </style>
    	<script type='text/javascript' src='js/jquery-2.1.1.min.js'></script>
		<script type='text/javascript' src='js/jquery.tablesorter.min.js'></script>
	</head>
	<body>
		<script type='text/javascript'>
			$(document).ready(function()
			    {
		        $('.tablesorter').tablesorter({ headers: { 1: {sorter:"text"} } });
			    }
			);
		</script>
		<div style="display: table; margin: 0 auto;">
		<h2>Category Membership / Template Usage Recent Changes</h2>
        <form action="RecentCategoryChanges.php" method="post"><b>Wiki</b> <select name="wiki"><?php
        foreach ($wikis as $wikiname => $wikidata) {
			$wikititle = htmlentities($wikidata['title'], ENT_COMPAT, 'UTF-8');
			$selected = '';
			if ($wikiname == $params['wiki']) $selected = ' selected="1"';
			echo "<option value='$wikiname'$selected>$wikititle</option>";
		}
        ?></select><input type="submit" value="Submit" />
        </form>
    <?php

    display_recent();

    ?></div><br /><div style="display: table; margin: 0 auto;">
    <a href="CategoryWatchlist.php" class='novisited'>Category Watchlist</a> <b>&bull;</b>
    <a href="https://en.wikipedia.org/wiki/User:CategoryWatchlistBot" class='novisited'>Documentation</a> <b>&bull;</b>
    Author: <a href="https://en.wikipedia.org/wiki/User:Bamyers99" class='novisited'>Bamyers99</a></div></body></html><?php
}

/**
 * Display recent changes
 */
function display_recent()
{
	global $uihelper, $params, $wikis;
	$errors = array();

	$results = $uihelper->getRecent($params['wiki'], $params['page'], 100);
	if (empty($results)) $errors[] = 'No more results';

	if (! empty($errors)) {
		echo '<h3>Messages</h3><ul>';
		foreach ($errors as $msg) {
			echo "<li>$msg</li>";
		}
		echo '</ul>';
	}

	if (! empty($results)) {
		$protocol = HttpUtil::getProtocol();
		$domain = $wikis[$params['wiki']]['domain'];
		$wikiprefix = "$protocol://$domain/wiki/";

		// Sort by date
		$dategroups = array();
		foreach ($results as &$result) {
			$date = $result['diffdate'];
			unset($result['diffdate']);
			if (! isset($dategroups[$date])) $dategroups[$date] = array();
			$dategroups[$date][] = $result;
		}
		unset($result);

		foreach ($dategroups as $date => &$dategroup) {
			$displaydate = date('F j, Y G', MySQLDate::toPHP($date));
			$ord = DateUtil::ordinal(date('G', MySQLDate::toPHP($date)));
			echo "<h3>$displaydate$ord hour</h3>";
			echo "<table class='wikitable tablesorter'><thead><tr><th>Page</th><th>+/&ndash;</th><th>Category / Template</th></tr></thead><tbody>\n";
			$x = 0;
			$prevtitle = '';
			$prevaction = '';

			foreach ($dategroup as &$result) {
				$title = $result['pagetitle'];
				$action = $result['plusminus'];
				$category = htmlentities($result['category'], ENT_COMPAT, 'UTF-8');
				if ($result['cat_template'] == 'T') $category = '{{' . $category . '}}';
				$displayaction = ($action == '-') ? '&ndash;' : $action;

				if ($title == $prevtitle && $action == $prevaction) {
					echo "; $category";
				} elseif ($title == $prevtitle) {
					echo "</td></tr>\n";
					echo "<tr><td>&nbsp;</td><td class='plusminus'>$displayaction</td><td>$category";
				} else {
					if ($x++ > 0) echo "</td></tr>\n";
					echo "<tr><td><a href=\"$wikiprefix" . urlencode(str_replace(' ', '_', $title)) . "\">" .
						htmlentities($title, ENT_COMPAT, 'UTF-8') . "</a></td><td class='plusminus'>$displayaction</td><td>$category";
				}
				$prevtitle = $title;
				$prevaction = $action;
			}

			if ($x > 0) echo "</td></tr>\n";

			echo "</tbody></table>\n";
		}
		unset($dategroup);
		unset($result);

		$host  = $_SERVER['HTTP_HOST'];
		$uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
		$protocol = HttpUtil::getProtocol();

		$extra = "RecentCategoryChanges.php?wiki={$params['wiki']}&amp;page=" . ($params['page'] + 1);
		echo "<div style='padding-bottom: 5px;'><a href='$protocol://$host$uri/$extra' class='novisited'>Next page</a></div>";

		echo '<div>+ = Added<br />&ndash; = Removed</div>';
	}
}

/**
 * Get the input parameters
 */
function get_params()
{
	global $params, $wikis, $uihelper;

	$params = array();

	$params['page'] = isset($_REQUEST['page']) ? $_REQUEST['page'] : '1';

	$params['wiki'] = isset($_REQUEST['wiki']) ? $_REQUEST['wiki'] : '';
	if (! isset($wikis[$params['wiki']])) $params['wiki'] = 'enwiki';
}
?>