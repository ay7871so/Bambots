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

$count = 0;
$edits = [];
$langs = [];
$username = false;
$matches = [];
$edittypes = [
	'!^wbsetclaim-create:!' => 0,
	'!^wbcreateclaim!' => 0, // no : because matches 5 actions
	'!^wbsetlabel-add:!' => -1,
	'!^wbsetdescription-add:!' => -2,
	'!^wbsetaliases-add:!' => -3,
    '!^wbsetaliases-set:!' => -3,
    '!^wbsetsitelink-add:!' => -4,
	'!^wbmergeitems-from:!' => -5,
    '!^add-form:!' => -6,
    '!^wbeditentity-create-form:!' => -6,
    '!^add-form-representations:!' => -7,
    '!^add-form-grammatical-features:!' => -8,
    '!^add-sense:!' => -9,
    '!^wbeditentity-create-sense:!' => -9,
    '!^add-sense-glosses:!' => -10,
    '!^wbsetreference-add:!' => -11,
    '!^wbsetreference:!' => -11,
    '!^wbsetqualifier-add:!' => -12,
    '!^wbsetlabel-set:!' => -13,
    '!^wbsetlabeldescriptionaliases:!' => -13,
    '!^wbsetdescription-set:!' => -14,
    '!^wbremoveclaims-remove:!' => -15,
    '!^wbsetclaim-update:!' => -16,
    '!^undo:!' => -17,
    '!^wbeditentity-create:!' => -18,
    '!^wbeditentity-create-item:!' => -18,
    '!^wbeditentity-create-property:!' => -18,
    '!^wbeditentity:!' => -18,
    '!^special-create-item:!' => -18,
    '!^special-create-property:!' => -18,
    '!^wbcreate-new:!' => -18,
    '!^wbeditentity-update!' => -19, // no : because matches multiple actions
    '!^wbsetsitelink-remove:!' => -20,
    '!^wbsetdescription-remove:!' => -21,
    '!^wbsetaliases-remove:!' => -22,
    '!^restore:!' => -23,
    '!^wbsetlabel-remove:!' => -24,
    '!^wbsetsitelink-set:!' => -25,
    '!^wbremovereferences-remove:!' => -26,
    '!^wbsetaliases-update:!' => -27,
    '!^wbsetreference-set:!' => -28,
    '!^wbsetclaim-update-references:!' => -28,
    '!^wbsetclaim-update-qualifiers:!' => -29,
    '!^wbsetqualifier-update:!' => -29,
    '!^update-form-grammatical-features:!' => -30,
    '!^update-form-representations:!' => -30,
    '!^update-form-elements:!' => -30,
    '!^remove-form:!' => -31,
    '!^set-form-representations:!' => -32,
    '!^remove-form-representations:!' => -33,
    '!^remove-form-grammatical-features:!' => -34,
    '!^remove-sense:!' => -35,
    '!^update-sense-glosses:!' => -36,
    '!^set-sense-glosses:!' => -36,
    '!^update-sense-elements:!' => -36,
    '!^remove-sense-glosses:!' => -37,
    '!^wbremovequalifiers-remove:!' => -38,
    '!^wbeditentity-create-lexeme:!' => -39,
    '!^wblmergelexemes-from:!' => -40
];

DEFINE('MONTHLY_INCREMENT', 0x100000000);
DEFINE('GRANDTOTAL_MASK', MONTHLY_INCREMENT - 1);

/* wbsetlabel-add:1|he */
/* wbsetdescription-add:1|yo */
/* wbsetaliases-add:1|sv */
/* wbsetsitelink-add:1|nlwikinews */

$prevmonth = date('Y-m', strtotime('-1 month'));

$hndl = fopen('php://stdin', 'r');
$revhndl = fopen('navelgazerrev.tsv', 'w');

while (! feof($hndl)) {
	$buffer = fgets($hndl);
	if (empty($buffer)) continue;
	$buffer = substr($buffer, 24); // strip /mediawiki/page/revision

	if (preg_match('!^/id=(\d+)!', $buffer, $matches)) {
	    $revid = $matches[1];
	} elseif (preg_match('!^/contributor/ip=!', $buffer, $matches)) {
	    $username = false;
	} elseif (preg_match('!^/contributor/@deleted!', $buffer, $matches)) {
	    $username = false;
	} elseif (preg_match('!^/contributor/username=([^\n]+)!', $buffer, $matches)) {
		$username = $matches[1];
	} elseif (preg_match('!^/timestamp=(\d{4}-\d{2})!', $buffer, $matches)) {
		$timestamp = $matches[1];
	} elseif (preg_match('!^/comment=/\\* ([^\n]+)!', $buffer, $matches)) {
	    if (++$count % 10000000 == 0) echo "Processed " . number_format($count) . "\n";
	    if ($username === false) $username = ''; // anonymous edit
		$comment = $matches[1];

		foreach ($edittypes as $edittype => $typevalue) {
		    if (preg_match($edittype, $comment)) {
				if ($typevalue === 0) {
				    if (! preg_match('!\\[\\[Property:P(\d+)!', $comment, $matches)){
				        if (! preg_match('!^wbcreateclaim:1 \\*/ p(\d+)!', $comment, $matches)) break;
				    }

					$typevalue = $matches[1];
				}

				$key = "a$typevalue"; // don't want a numeric key

				if (! isset($edits[$username])) $edits[$username] = [];
				if (! isset($edits[$username][$key])) $edits[$username][$key] = 0; // grand total lower 32 bits, month total upper 32 bits

				$multiplier = 1;

				if ($typevalue === -3 || $typevalue === -22 || $typevalue === -27) { // can have multiple alias changes per edit
				    preg_match('!^([a-z\-]+)\s*(:\s*(.*?)\s*)?\\*/!', $comment, $matches);

				    $args = isset($matches[3]) ? explode('|', $matches[3]) : [];

				    if (isset($args[0])) {
				        $multiplier = intval($args[0]);
				    }
				}

				$edits[$username][$key] += $multiplier;

				if ($timestamp == $prevmonth) {
				    $edits[$username][$key] += (MONTHLY_INCREMENT * $multiplier);
				}

				if ($typevalue === -1 || $typevalue === -2 || $typevalue === -3 || $typevalue === -4) {
				    $lang = '';

				    if ($typevalue == -4) {
				        if (preg_match('!\\|([a-z]{2,3})wiki!', $comment, $matches)) {
				            $lang = $matches[1];
				        }
				    } else {
				        if (preg_match('!\\|([a-z]{2,3}(?:-[a-z]+)*)!', $comment, $matches)) {
				            $lang = $matches[1];
				        }
				    }

				    if (! empty($lang)) {
				        if (! isset($langs[$lang])) $langs[$lang] = [];
				        if (! isset($langs[$lang][$username])) $langs[$lang][$username] = 0;
				        $langs[$lang][$username] += $multiplier;
				        if ($timestamp == $prevmonth) $langs[$lang][$username] += (MONTHLY_INCREMENT * $multiplier);
				    }
				}

				break;
			}
		}

		if (! empty ($username) && strcasecmp(substr($username, -3), 'bot') != 0) {
		    if ($timestamp == $prevmonth) fwrite($revhndl, "$revid\t$username\n");
		}
	}

}

echo "Processed " . number_format($count) . "\n";

fclose($revhndl);
fclose($hndl);
$hndl = fopen('navelgazer.tsv', 'w');

foreach ($edits as $username => $totals) {
    foreach ($totals as $key => $total) {
        $key = substr($key, 1);
        $grandtotal = $total & GRANDTOTAL_MASK;
        $monthtotal = $total >> 32;
        fwrite($hndl, "$username\t$key\t$grandtotal\t$monthtotal\n");
    }
}

fclose($hndl);

$hndl = fopen('navelgazerlang.tsv', 'w');

foreach ($langs as $lang => $totals) {
    foreach ($totals as $username => $total) {
        $grandtotal = $total & GRANDTOTAL_MASK;
        $monthtotal = $total >> 32;
        fwrite($hndl, "$lang\t$username\t$grandtotal\t$monthtotal\n");
    }
}

fclose($hndl);
