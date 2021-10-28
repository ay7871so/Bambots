<?php
/**
 Copyright 2013 Myers Enterprises II

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

namespace com_brucemyers\test\InceptionBot;

use com_brucemyers\InceptionBot\RuleSet;
use UnitTestCase;

class TestRuleSet extends UnitTestCase
{

    public function testGoodRules()
    {
        $rules = <<<'EOT'
        {{RuleBanner}}
        <!-- Multiline
                        comment -->
        @@40@@
        ##SuppressNS=Category|Template|Draft##
        $$NZ-stub$$
        -5 $$AU-stub$$
        20  /$SIZE>2500/
        -20  /$SIZE<2500/
        /$TITLE:National Park/
        /$LEAD:\([^)]*(born|b\.)/ , /Borneo/
        /$ORES:STEM.Medicine > 0.5/
        /Category:$CURRENTYEAR births/
        /$CURRENTYEAR-1: births/
        /$CURRENTDECADEs births/
        /$CURRENTDECADE-1:s births/
        5 /Bay\W*of\W*Plenty\P{M}\x{2460}\p{Greek}\p{isCyrillic}\P{IsArmenian}/ <!-- Tests Unicode -->
        /Northland\Wgeo\Wstub/
        6  /\WOtago\W/ , /Australia/ , /Tasmania/ , /Hobart/
        /$$NZ-stub$$/ , /$$AU-stub$$/
        [[Category:WikiProject Canadian music]]
EOT;

        $ruleset = new RuleSet('test', $rules);
        $errorcnt = count($ruleset->errors);
        $this->assertEqual($ruleset->minScore, 40, 'Invalid min score');
        $this->assertEqual($errorcnt, 0, 'Parse error');
        $this->assertTrue(isset($ruleset->options['SuppressNS']), 'Missing option SuppressNS');
        $this->assertEqual($ruleset->options['SuppressNS'], array('Category','Template','Draft'), 'SuppressNS option value mismatch');
        if ($errorcnt) print_r($ruleset->errors);

        // Check inhibitors
        $inhibitcnt = 0;
        foreach ($ruleset->rules as $rule) {
            $inhibitcnt += count($rule['inhibitors']);
        }
        $this->assertEqual($inhibitcnt, 5, 'Missing inhibitors');

        print_r($ruleset->rules);
    }

    public function testBadRules()
    {
        $rules = <<<'EOT'
        ##SuppressNS=User##
        ##InvalidOption##
        xxx
        +5 /Bay\W*of\W*Plenty/
        /Northland\Wgeo\Wstub
        6  /.?*/ , /.?*/
        /\p{gfgfgg}/
EOT;

        $ruleset = new RuleSet('test', $rules);
        $errorcnt = count($ruleset->errors);
        $realerrors = 7;
        $this->assertEqual($errorcnt, $realerrors);
        if ($errorcnt != $realerrors) print_r($ruleset->errors);
        //print_r($ruleset->rules);
    }
}