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

 bunzip2 -c wikidatawiki*-pages-articles.xml.bz2 | ./xml2 | grep '"P279"\|"P31"\|"P360"' | php wdsubclass.php&
 */

define('PROP_SUBCLASSOF', 'P279');
define('PROP_INSTANCEOF', 'P31');
define('PROP_ISLISTOF', 'P360');

define('MIN_ORPHAN_DIRECT_INST_CNT', 5);
define('DIRECT_INSTANCE_CNT', 'a');
define('INDIRECT_INSTANCE_CNT', 'b');
define('DIRECT_CHILD_COUNT', 'c');
define('INDIRECT_CHILD_COUNT', 'd');
define('ISLISTOF_COUNT', 'e');
define('CLASS_FOUND', 'f');
define('PARENTS', 'p');
define('INSTANCE_OF', 'i');

$count = 0;
$classes = [];
$values = [];
$valuecounts = [];

$whndl = fopen('wdsubclassclasses.tsv', 'w');
$hndl = fopen('php://stdin', 'r');

while (! feof($hndl)) {
	if (++$count % 100000 == 0) echo "Processed $count\n";
	$buffer = fgets($hndl);
	if (empty($buffer)) continue;
	$buffer = substr($buffer, 30); // /mediawiki/page/revision/text=

	$data = json_decode($buffer, true);
	if (! isset($data['id'])) continue;
	$qid = $data['id'];
	if (empty($qid)) continue;
	$qid = (int)substr($qid, 1);

	if (! isset($data['claims']) || (! isset($data['claims'][PROP_SUBCLASSOF]) && ! isset($data['claims'][PROP_INSTANCEOF]) &&
		! isset($data['claims'][PROP_ISLISTOF]))) continue;

	if (isset($data['claims'][PROP_SUBCLASSOF])) {
		foreach ($data['claims'][PROP_SUBCLASSOF] as $parent) {
			if (! isset($parent['mainsnak']['datavalue']['value']['numeric-id'])) continue;
	    	$parentqid = (int)$parent['mainsnak']['datavalue']['value']['numeric-id'];

	    	if (! isset($classes[$qid])) $classes[$qid] = init_class();
	    	$classes[$qid][CLASS_FOUND] = true;
	    	$classes[$qid][PARENTS][] = $parentqid;

	    	if (! isset($classes[$parentqid])) $classes[$parentqid] = init_class();
	    	++$classes[$parentqid][DIRECT_CHILD_COUNT];
	    	$classes[$parentqid][CLASS_FOUND] = true;

	    	fwrite($whndl, "$parentqid\t$qid\n");
		}
		
		if (isset($data['claims'][PROP_INSTANCEOF]) && isset($classes[$qid])) {
		    foreach ($data['claims'][PROP_INSTANCEOF] as $instanceof) {
		        if (! isset($instanceof['mainsnak']['datavalue']['value']['numeric-id'])) continue;
		        $instanceqid = (int)$instanceof['mainsnak']['datavalue']['value']['numeric-id'];
		        
		        if (! isset($classes[$qid][INSTANCE_OF])) $classes[$qid][INSTANCE_OF] = [];
		        $classes[$qid][INSTANCE_OF][] = $instanceqid;
		    }
		}
	}

	if (isset($data['claims'][PROP_INSTANCEOF])) {
		foreach ($data['claims'][PROP_INSTANCEOF] as $instanceof) {
			if (! isset($instanceof['mainsnak']['datavalue']['value']['numeric-id'])) continue;
			$instanceqid = (int)$instanceof['mainsnak']['datavalue']['value']['numeric-id'];

			if (! isset($classes[$instanceqid])) $classes[$instanceqid] = init_class();
			++$classes[$instanceqid][DIRECT_INSTANCE_CNT];

			processvalues($instanceqid, $data);
		}
	}

	if (isset($data['claims'][PROP_ISLISTOF])) {
		foreach ($data['claims'][PROP_ISLISTOF] as $islistof) {
			if (! isset($islistof['mainsnak']['datavalue']['value']['numeric-id'])) continue;
			$classqid = (int)$islistof['mainsnak']['datavalue']['value']['numeric-id'];

	    	if (! isset($classes[$classqid])) $classes[$classqid] = init_class();
	    	++$classes[$classqid][ISLISTOF_COUNT];
		}
	}
}

echo "Processed $count\n";
echo "Class count " . count($classes) . "\n";

fclose($hndl);
fclose($whndl);
$whndl = fopen('wdsubclassinstof.tsv', 'w');

// Calc totals
$count = 0;

foreach ($classes as $classqid => $class) {
	if (++$count % 100000 == 0) echo "Processed $count\n";

	if (! $class[CLASS_FOUND] && $class[DIRECT_INSTANCE_CNT] < MIN_ORPHAN_DIRECT_INST_CNT) {
		unset($classes[$classqid]); // No need to report because other report catches these.
		continue;
	}

	$parents = [];
	foreach ($class[PARENTS] as $parentqid) {
		recurse_parents($parentqid, $parents, $classes, 0);
	}

	foreach ($parents as $parentqid => $dummy) {
		$classes[$parentqid][INDIRECT_CHILD_COUNT] += $class[DIRECT_CHILD_COUNT];
		$classes[$parentqid][INDIRECT_INSTANCE_CNT] += $class[DIRECT_INSTANCE_CNT];
		
		if (isset($class[INSTANCE_OF]) && in_array($parentqid, $class[INSTANCE_OF])) {
		    fwrite($whndl, "$classqid\t$parentqid\n");
		}
	}
}

// Write totals
fclose($whndl);
$whndl = fopen('wdsubclasstotals.tsv', 'w');

foreach ($classes as $classqid => $class) {
    $isroot = count($class[PARENTS]) ? 'N' : 'Y';

    fwrite($whndl, "$classqid\t$isroot\t{$class[DIRECT_CHILD_COUNT]}\t{$class[INDIRECT_CHILD_COUNT]}\t{$class[DIRECT_INSTANCE_CNT]}\t{$class[INDIRECT_INSTANCE_CNT]}\t{$class[ISLISTOF_COUNT]}\n");
}

fclose($whndl);

// Write value counts
$whndl = fopen('wdsubclassvalues.tsv', 'w');

foreach ($values as $key => $valuevalues) {
    list($classqid, $pid, $qualpid) = explode('-', $key);

    if ($valuevalues === false) { // > 50 values
        fwrite($whndl, "$classqid\t$pid\t$qualpid\t0\t0\n");
    } else {
        foreach ($valuevalues as $vkey => $count) {
            fwrite($whndl, "$classqid\t$pid\t$qualpid\t$vkey\t$count\n");
        }
    }
}

foreach ($valuecounts as $key => $count) {
    list($classqid, $pid, $qualpid) = explode('-', $key);
    if ($count > 9) fwrite($whndl, "$classqid\t$pid\t$qualpid\tC\t$count\n");
}

fclose($whndl);
echo "Finished\n";

/**
 * Recurse class parents collecting qids
 *
 * @param unknown $parentqid
 * @param unknown $parents
 * @param unknown $classes
 * @param unknown $depth
 */
function recurse_parents($parentqid, &$parents, $classes, $depth)
{
	++$depth;
	if ($depth > 100) return;

	if (isset($parents[$parentqid])) return;
	$parents[$parentqid] = true; // Prevents dups

	$parent = $classes[$parentqid];

	foreach ($parent[PARENTS] as $parentqid) {
		recurse_parents($parentqid, $parents, $classes, $depth);
	}
}

/**
 * Initialize a class object
 */
function init_class()
{
	return [DIRECT_CHILD_COUNT => 0,  INDIRECT_CHILD_COUNT => 0, PARENTS => [], DIRECT_INSTANCE_CNT => 0,
	    INDIRECT_INSTANCE_CNT => 0, ISLISTOF_COUNT => 0, CLASS_FOUND => false];
}

/**
 * Save wikibase-item type values for properties and their qualifiers.
 *
 * @param unknown $classqid
 * @param unknown $data
 */
function processvalues($classqid, $data)
{
    global $values, $valuecounts;

    foreach ($data['claims'] as $pid => $claims) {
        if ($pid == PROP_INSTANCEOF || $pid == PROP_SUBCLASSOF) continue;
        $pid = substr($pid, 1);

        $ckey = "$classqid-$pid-0";
        if (! isset($valuecounts[$ckey])) $valuecounts[$ckey] = 0;
        ++$valuecounts[$ckey];

        foreach ($claims as $claim) {
            $mainsnak = $claim['mainsnak'];

            if ($mainsnak['snaktype'] == 'value' && $mainsnak['datavalue']['type'] == 'wikibase-entityid' && $mainsnak['datavalue']['value']['entity-type'] == 'item') {
                $valueid = $mainsnak['datavalue']['value']['numeric-id'];

                if (! isset($values[$ckey])) $values[$ckey] = [$valueid => 0];

                if ($values[$ckey] !== false) {
                    if (! isset($values[$ckey][$valueid])) $values[$ckey][$valueid] = 0;
                    if (count($values[$ckey]) == 50) $values[$ckey] = false; // limit to 50
                    else ++$values[$ckey][$valueid];
                }
            }

            if (isset($claim['qualifiers'])) {
                foreach ($claim['qualifiers'] as $qualpid => $qualifiers) {
                    $qualpid = substr($qualpid, 1);

                    $qkey = "$classqid-$pid-$qualpid";
                    if (! isset($valuecounts[$qkey])) $valuecounts[$qkey] = 0;
                    ++$valuecounts[$qkey];

                    foreach ($qualifiers as $qualifier) {
                        if ($qualifier['snaktype'] != 'value' || $qualifier['datavalue']['type'] != 'wikibase-entityid' ||
                            $qualifier['datavalue']['value']['entity-type'] != 'item') continue;
                        $valueid = $qualifier['datavalue']['value']['numeric-id'];

                        if (! isset($values[$qkey])) $values[$qkey] = [$valueid => 0];

                        if ($values[$qkey] !== false) {
                            if (! isset($values[$qkey][$valueid])) $values[$qkey][$valueid] = 0;
                            if (count($values[$qkey]) == 50) $values[$qkey] = false; // limit to 50
                            else ++$values[$qkey][$valueid];
                        }
                    }
                }
            }
        }
    }
}