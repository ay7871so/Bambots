<?php
/**
 Copyright 2014 Myers Enterprises II

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

namespace com_brucemyers\Util;

/**
 * TemplateParamParser
 */
class TemplateParamParser
{
	static $regexs = array(
		'passed_param' => '!\{\{\{(?P<content>[^{}]*?\}\}\})!', // Highest priority
		'htmlstub' => '!<\s*(?P<content>[\w]+(?:(?:\s+\w+(?:\s*=\s*(?:"[^"]*+"|\'[^\']*+\'|[^\'">\s]+))?)+\s*|\s*)/>)!',
		'html' => '!<\s*(?P<content>(?P<tag>[\w]+)[^>]*>[^<]*?<\s*/\s*(?P=tag)\s*>)!',
	    'template' => '!\{\{\s*(?P<content>(?P<name>[^{}\|]+?)(?:\|(?P<params>[^{}]+?)?)?\}\})!',
	    'table' => '!\{\|(?P<content>[^{]*?\|\})!',
		'link' => '/\[\[(?P<content>(?:.(?!\[\[))+?\]\])/s'
	);

	const MAX_ITERATIONS = 10000;

	/**
	 * Get template names and parameters in a string.
	 * Numbered params are relative to 1
	 *
	 * @param string $origdata
	 * @return array Templates array('name' => string, 'params' = array('name' => 'value')
	 */
	public static function getTemplates($origdata)
	{
		$itercnt = 0;
		$match_found = true;
		$markers = array();
		$templates = array();
		$data = preg_replace(CommonRegex::COMMENT_REGEX, '', $origdata); // Strip comments
		$data = preg_replace(CommonRegex::BR_REGEX, ' ', $data); // Strip BRs

		while ($match_found) {
			if (++$itercnt > self::MAX_ITERATIONS) {
			    //Logger::log("Max iterations reached data=$origdata");
			    return array();
			}
			$match_found = false;

			foreach (self::$regexs as $type => $regex) {
				$match_cnt = preg_match_all($regex, $data, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
				$offset_adjust = 0;

				if ($match_cnt) {
				    $match_found = true;
					$replacement_made = false;

					foreach ($matches as $match) {
						// See if there are any containers inside
						$content = $match['content'][0];

						foreach (self::$regexs as $regex2) {
							if (preg_match($regex2, $content)) {
//								echo "$regex2\n";
//								echo "$content\n";
								continue 2;
							}
						}

						// Replace the match with a marker
						$marker_id = "\x02" . count($markers) . "\x03";
						$content = $match[0][0];
						$content_len = strlen($content);
						$offset = $match[0][1] - $offset_adjust;
						$offset_adjust += $content_len - strlen($marker_id);

						$data = substr_replace($data, $marker_id, $offset, $content_len);

						if ($type == 'template') $templates[] = $content;

						// Replace any markers in the content
						preg_match_all("!\\x02\\d+\\x03!", $content, $marker_matches);
						foreach ($marker_matches[0] as $marker_match) {
							$content = str_replace($marker_match, $markers[$marker_match], $content);
						}

						$markers[$marker_id] = $content;
						$replacement_made = true;
					}

					if ($replacement_made) continue 2; // restart with the first regex
				}
			}
		}

		$results = array();

		// Parse the template names and parameters
		foreach ($templates as $template) {
			preg_match(self::$regexs['template'], $template, $matches);
			$tmpl_name = $matches['name'];

			// Replace any markers in the name
			preg_match_all("!\\x02\\d+\\x03!", $tmpl_name, $marker_matches);
			foreach ($marker_matches[0] as $marker_match) {
				$tmpl_name = str_replace($marker_match, $markers[$marker_match], $tmpl_name);
			}

			$tmpl_name = ucfirst(trim(str_replace('_', ' ', $tmpl_name)));
			if (strpos($tmpl_name, 'Template:') === 0) {
				$tmpl_name = ucfirst(ltrim(substr($tmpl_name, 9)));
			}

			$tmpl_params = array();
			if (isset($matches['params'])) {
				$numbered_param = 1;
				$params = explode('|', $matches['params']);

				foreach ($params as $param) {
					if (strpos($param, '=') !== false) {
						list($param_name, $param_value) = explode('=', $param, 2);

						// Replace any markers in the name
						preg_match_all("!\\x02\\d+\\x03!", $param_name, $marker_matches);
						foreach ($marker_matches[0] as $marker_match) {
							$param_name = str_replace($marker_match, $markers[$marker_match], $param_name);
						}
					} else {
						$param_name = "$numbered_param";
						$param_value = $param;
						++$numbered_param;
					}

					// Replace any markers in the content
					preg_match_all("!\\x02\\d+\\x03!", $param_value, $marker_matches);
					foreach ($marker_matches[0] as $marker_match) {
						$param_value = str_replace($marker_match, $markers[$marker_match], $param_value);
					}

					$param_name = trim($param_name);
					if (strlen($param_name)) $tmpl_params[$param_name] = trim($param_value);
				}
			}

			$results[] = array('name' => $tmpl_name, 'params' => $tmpl_params);
		}

		return $results;
	}
}