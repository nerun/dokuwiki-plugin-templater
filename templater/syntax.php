<?php
/**
 * Templater Plugin: Based from the include plugin, like MediaWiki's template
 * Usage:
 *    {{template>page}} for "page" in same namespace
 *    {{template>:page}} for "page" in top namespace
 *    {{template>namespace:page}} for "page" in namespace "namespace"
 *    {{template>.namespace:page}} for "page" in subnamespace "namespace"
 *    {{template>page#section}} for a section of "page"
 *
 * Replacers are handled in a simple key/value pair method:
 *    {{template>page|key=val|key2=val|key3=val}}
 *
 * Templates are wiki pages, with replacers being delimited like:
 *    @key1@ @key2@ @key3@
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jonathan Arkell <jonnay@jonnay.net>
 *                based on code by Esther Brunner <esther@kaffeehaus.ch>
 * @updaters   Vincent de Lau <vincent@delau.nl>
 *                with bugfix from Ximin Luo <xl269@cam.ac.uk>
 *             Daniel Dias Rodrigues (aka Nerun) <danieldiasr@gmail.com>
 *                with one bugfix from jack126guy <halfgray7e@gmail.com>
 * @version    0.6 (2022-11-27)
 */

define('BEGIN_REPLACE_DELIMITER', '@');
define('END_REPLACE_DELIMITER', '@');

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once('debug.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_templater extends DokuWiki_Syntax_Plugin {
	/**
	 * return some info
	 */
	function getInfo() {
		return array(
			'author' => 'Jonathan Arkell (updated by Daniel Dias Rodrigues)',
			'email'  => 'jonnay@jonnay.net',
			'date'   => '2022-11-27',
			'name'   => 'Templater Plugin',
			'desc'   => 'Displays a wiki page (or a section thereof) within another, with user selectable replacements',
			'url'    => 'http://www.dokuwiki.org/plugin:templater',
		);
	}

	/**
	 * What kind of syntax are we?
	 */
	function getType() {
		return 'container';
	}

	function getAllowedTypes() {
		return array('container', 'substition', 'protected', 'disabled', 'formatting');
	}

	/**
	 * Where to sort in?
	 */
	function getSort() {
		return 302;
	}

	/**
	 * Paragraph Type
	 */
	function getPType() {
		return 'block';
	}

	/**
	 * Connect pattern to lexer
	 */
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern("{{template>.+?}}", $mode, 'plugin_templater');
	}

	/**
	 * Handle the match
	 */
	function handle($match, $state, $pos, Doku_Handler $handler) {
		global $ID;

		$match = substr($match, 11, -2);                        // strip markup
		$replacers = preg_split('/(?<!\\\\)\|/', $match);       // Get the replacers
		$wikipage = array_shift($replacers);

		$replacers = $this->_massageReplacers($replacers);

		$wikipage = preg_split('/\#/u', $wikipage, 2);                      // split hash from filename
		$parentpage = empty(self::$pagestack)? $ID : end(self::$pagestack); // get correct namespace
		resolve_pageid(getNS($parentpage), $wikipage[0], $exists);          // resolve shortcuts

		// check for perrmission
		if (auth_quickaclcheck($wikipage[0]) < 1)
			return false;
		
		// $wikipage[1] is the header of a template enclosed within a section {{template>page#section}}
		// Not all template calls will be {{template>page#section}}, some will be {{template>page}}
		// It fix "Undefined array key 1" warning
		if (array_key_exists(1, $wikipage)) {
			$section = cleanID($wikipage[1]);
		} else {
			$section = null;
		}
		
		return array($wikipage[0], $replacers, $section);
	}

	private static $pagestack = array(); // keep track of recursing template renderings

	/**
	 * Create output
	 * This is a refactoring candidate. Needs to be a little clearer.
	 */
	function render($mode, Doku_Renderer $renderer, $data) {
		if ($mode != 'xhtml')
			return false;

		if ($data[0] === false) {
			// False means no permissions
			$renderer->doc .= $this->getLang('no_permissions_view');
			$renderer->info['cache'] = FALSE;
			return true;
		}

		$file = wikiFN($data[0]);
		if (!@file_exists($file)) {
			$renderer->doc .= '<div class="templater">';
			$renderer->doc .= $this->getLang('template');
			$renderer->internalLink($data[0]);
			$renderer->doc .= $this->getLang('not_found');
			$renderer->doc .= '<br/><br/></div>';
			$renderer->info['cache'] = FALSE;
			return true;
		} else if (array_search($data[0], self::$pagestack) !== false) {
			$renderer->doc .= '<div class="templater">';
			$renderer->doc .= $this->getLang('processing_template');
			$renderer->internalLink($data[0]);
			$renderer->doc .= $this->getLang('stopped_recursion');
			$renderer->doc .= '<br/><br/></div>';
			return true;
		}
		self::$pagestack[] = $data[0]; // push this onto the stack

		// Get the raw file, and parse it into its instructions. This could be cached... maybe.
		$rawFile = io_readfile($file);
		if(!empty($data[1]['keys']) && !empty($data[1]['vals'])) {
			$rawFile = str_replace($data[1]['keys'], $data[1]['vals'], $rawFile);
		}

		// replace unmatched substitutions with "" or use DEFAULT_STR from data arguments if exists.
		$left_overs = '/'.BEGIN_REPLACE_DELIMITER.'.*'.END_REPLACE_DELIMITER.'/';

		if(!empty($data[1]['keys']) && !empty($data[1]['vals'])) {
			$def_key = array_search(BEGIN_REPLACE_DELIMITER."DEFAULT_STR".END_REPLACE_DELIMITER, $data[1]['keys']);
			$DEFAULT_STR = $def_key ? $data[1]['vals'][$def_key] : "";
			$rawFile = preg_replace($left_overs, $DEFAULT_STR, $rawFile);
		}

		$instr = p_get_instructions($rawFile);

		// filter section if given
		if ($data[2]) {
			$getSection = $this->_getSection($data[2], $instr);
			
			$instr = $getSection[0];
			
			if(!is_null($getSection[1])) {
				$renderer->doc .= sprintf($getSection[1], $data[2]);
				$renderer->internalLink($data[0]);
				$renderer->doc .= '.<br/><br/></div>';
			}
		}
		
		// correct relative internal links and media
		$instr = $this->_correctRelNS($instr, $data[0]);
		
		// doesn't show the heading for each template if {{template>page#section}}
		if (sizeof($instr) > 0 && !isset($getSection[1])) {
			if (array_key_exists(0, $instr[0][1]) && $instr[0][1][0] == $data[2]) {
				$instr[0][1][0] = null;
			}
		}
		
		// render the instructructions on the fly
		$text = p_render('xhtml', $instr, $info);

		// remove toc, section edit buttons and category tags
		$patterns = array('!<div class="toc">.*?(</div>\n</div>)!s',
						  '#<!-- SECTION \[(\d*-\d*)\] -->#',
						  '!<div class="category">.*?</div>!s');
		$replace  = array('', '', '');
		$text = preg_replace($patterns, $replace, $text);

		// prevent caching to ensure the included page is always fresh
		$renderer->info['cache'] = FALSE;

		// embed the included page
		$renderer->doc .= '<div class="templater">';
		$renderer->doc .= $text;
		$renderer->doc .= '</div>';

		array_pop(self::$pagestack); // pop off the stack when done
		return true;
	}

	/**
	 * Get a section including its subsections
	 */
	function _getSection($title, $instructions) {
	   	$i = (array) null;
	   	$level = null;
	   	$no_section = null;
		
		foreach ($instructions as $instruction) {
			if ($instruction[0] == 'header') {
				
				// found the right header
				if (cleanID($instruction[1][0]) == $title) {
					$level = $instruction[1][1];
					$i[] = $instruction;
				} else {
					if (isset($level) && isset($i)) {
						if ($instruction[1][1] > $level) {
							$i[] = $instruction;
				// next header of the same level or higher -> exit
						} else {
							return array($i,null);
						}
					}
				}
			} else { // content between headers
				if (isset($level) && isset($i)) {
					$i[] = $instruction;
				}
			} 
		}
		
		// Fix for when page#section doesn't exist
		if(sizeof($i) == 0) {
			$no_section = $this->getLang('no_such_section');
		}
		
		return array($i,$no_section);
	}

	/**
	 * Corrects relative internal links and media
	 */
	function _correctRelNS($instr, $incl) {
		global $ID;

		// check if included page is in same namespace
		$iNS = getNS($incl);
		if (getNS($ID) == $iNS)
			return $instr;

		// convert internal links and media from relative to absolute
		$n = count($instr);
		for($i = 0; $i < $n; $i++) {
			if (substr($instr[$i][0], 0, 8) != 'internal')
				continue;

			// relative subnamespace
			if ($instr[$i][1][0][0] == '.') {
				$instr[$i][1][0] = $iNS.':'.substr($instr[$i][1][0], 1);

			// relative link
			} else if (strpos($instr[$i][1][0], ':') === false) {
				$instr[$i][1][0] = $iNS.':'.$instr[$i][1][0];
			}
		}
		
		return $instr;
	}

	/**
	 * Handles the replacement array
	 */
	function _massageReplacers($replacers) {
		$r = array();
		if (is_null($replacers)) {
			$r['keys'] = null;
			$r['vals'] = null;
		} else if (is_string($replacers)) {
			list($k, $v) = explode('=', $replacers, 2);
			$r['keys'] = BEGIN_REPLACE_DELIMITER.trim($k).END_REPLACE_DELIMITER;
			$r['vals'] = trim(str_replace('\|', '|', $v));
		} else if (is_array($replacers)) {
			foreach($replacers as $rep) {
				list($k, $v) = explode('=', $rep, 2);
				$r['keys'][] = BEGIN_REPLACE_DELIMITER.trim($k).END_REPLACE_DELIMITER;
				$r['vals'][] = trim(trim(str_replace('\|','|',$v)), "\"");
			}
		} else {
			// This is an assertion failure. We should NEVER get here.
			//die("FATAL ERROR!  Unknown type passed to syntax_plugin_templater::massageReplaceMentArray() can't massage syntax_plugin_templater::\$replacers!  Type is:".gettype($r)." Value is:".$r);
			$r['keys'] = null;
			$r['vals'] = null;
		}
		return $r;
	}
}
?>
