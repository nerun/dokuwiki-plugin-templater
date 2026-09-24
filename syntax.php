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
 * @license        GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author         Jonathan Arkell <jonnay@jonnay.net>
 *                    based on code by Esther Brunner <esther@kaffeehaus.ch>
 * @maintainer     Daniel Dias Rodrigues (aka Nerun) <danieldiasr@gmail.com>
 * @contributors   Vincent de Lau <vincent@delau.nl>
 *                 Ximin Luo <xl269@cam.ac.uk>
 *                 jack126guy <halfgray7e@gmail.com>
 *                 Turq Whiteside <turq@mage.city>
 */

use dokuwiki\File\PageResolver;

define('BEGIN_REPLACE_DELIMITER', '@');
define('END_REPLACE_DELIMITER', '@');

require_once('debug.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_templater extends DokuWiki_Syntax_Plugin {
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
        $replacers = preg_split('/(?<!\\\\)\|/', $match);        // Get the replacers
        $wikipage = array_shift($replacers);

        $replacers = $this->_massageReplacers($replacers);

        $wikipage = preg_split('/\#/u', $wikipage, 2);                        // split hash from filename
        $parentpage = empty(self::$pagestack)? $ID : end(self::$pagestack); // get correct namespace
        // resolve shortcuts:
        $resolver = new PageResolver(getNS($parentpage));
        $wikipage[0] = $resolver->resolveId($wikipage[0]);
        $exists = page_exists($wikipage[0]);

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
            $renderer->doc .= '<div class="templater"> ';
            $renderer->doc .= $this->getLang('no_permissions_view');
            $renderer->doc .= ' </div>';
            $renderer->info['cache'] = FALSE;
            return true;
        }

        $file = wikiFN($data[0]);
        if (!@file_exists($file)) {
            $renderer->doc .= '<div class="templater">— ';
            $renderer->doc .= $this->getLang('template');
            $renderer->doc .= ' ';
            $renderer->internalLink($data[0]);
            $renderer->doc .= ' ';
            $renderer->doc .= $this->getLang('not_found');
            $renderer->doc .= '<br/><br/></div>';
            $renderer->info['cache'] = FALSE;
            return true;
        } else if (array_search($data[0], self::$pagestack) !== false) {
            $renderer->doc .= '<div class="templater">— ';
            $renderer->doc .= $this->getLang('processing_template');
            $renderer->doc .= ' ';
            $renderer->internalLink($data[0]);
            $renderer->doc .= ' ';
            $renderer->doc .= $this->getLang('stopped_recursion');
            $renderer->doc .= '<br/><br/></div>';
            return true;
        }
        self::$pagestack[] = $data[0]; // push this onto the stack

        // Get the raw file, and parse it into its instructions. This could be cached... maybe.
        $rawFile = io_readfile($file);
        
        $replacements = array();
        $DEFAULT_STR = "";
        $has_replacements = false;

        // fill in all known values
        if(!empty($data[1]['keys']) && !empty($data[1]['vals'])) {
            // $has_replacements tracks whether the user passed any parameters in the template call.
            // E.g., true for {{template>page|foo=bar}}, false for {{template>page}}
            $has_replacements = true;
            foreach($data[1]['keys'] as $i => $k) {
                // Example: If the user wrote {{template>page|name=John}}
                // The $k variable here is the exact string '@name@'. 
                // We strip the '@' characters from both ends to extract just 'name'.
                $inner_key = substr($k, strlen(BEGIN_REPLACE_DELIMITER), -strlen(END_REPLACE_DELIMITER));
                // Using our index $i, $data[1]['vals'][$i] retrieves 'John'.
                // Duplicate parameters should respect the first provided value
                if (!array_key_exists($inner_key, $replacements)) {
                    $replacements[$inner_key] = $data[1]['vals'][$i];
                }
            }

            // DEFAULT_STR is a special parameter you can pass to the template (e.g. {{template>page|DEFAULT_STR=Unknown}})
            // It acts as a global fallback for ANY variable that doesn't have a value or a specific fallback.
            if (isset($replacements['DEFAULT_STR'])) {
                $DEFAULT_STR = $replacements['DEFAULT_STR'];
            }
        }

        // Build a regex pattern that matches either an explicitly provided key or a strict identifier ([\w\-.]+)
        // This prevents ordinary text (like alice@example.org) from accidentally being consumed across lines.
        $keys = array_keys($replacements);
        $literal_keys = array_map(function($k) { return preg_quote($k, '/'); }, $keys);
        usort($literal_keys, function($a, $b) { return strlen($b) - strlen($a); });
        
        $key_pattern = '[\w\-.]+';
        if (!empty($literal_keys)) {
            $key_pattern = '(?:' . implode('|', $literal_keys) . '|' . $key_pattern . ')';
        }

        // Regex matches variables with optional fallbacks using the format @key@ or @key|fallback@ inside the destination template page.
        // Literal '@' inside the fallback can be escaped with '\@'
        $pattern = '/'.preg_quote(BEGIN_REPLACE_DELIMITER, '/').'(' . $key_pattern . ')(?:\|((?:[^'.preg_quote(BEGIN_REPLACE_DELIMITER, '/').'\r\n\\\\]|\\\\.)*))?'.preg_quote(END_REPLACE_DELIMITER, '/').'/';

        // Apply replacements iteratively (multi-pass) up to 10 times to support nested substitutions (e.g. a=@b@|b=Hello)
        for ($pass = 0; $pass < 10; $pass++) {
            $newRawFile = preg_replace_callback($pattern, function($matches) use ($replacements, $DEFAULT_STR, $has_replacements) {
                $key = trim($matches[1]);
                $fallback = isset($matches[2]) ? str_replace(
                    ['\\'.BEGIN_REPLACE_DELIMITER, '\\|', '\\\\'], 
                    [BEGIN_REPLACE_DELIMITER, '|', '\\'], 
                    $matches[2]
                ) : null;

                // If a value was explicitly passed in the template call, use it.
                // Example: @foo@ or @foo|fallback@ -> Replaced by the provided value of 'foo'.
                if (array_key_exists($key, $replacements)) {
                    return $replacements[$key];
                }

                // If a fallback is provided in the template file and no value was passed, use the fallback.
                // Example: @foo|fallback@ -> Replaced by 'fallback' if 'foo' is missing.
                // Example: @foo|@         -> Replaced by empty string if 'foo' is missing (hides the variable).
                if ($fallback !== null) {
                    return $fallback;
                }

                // Otherwise, use DEFAULT_STR, but only if the user passed parameters (legacy behavior).
                // Example: @foo@ -> Replaced by DEFAULT_STR (often "") if $has_replacements is true.
                if ($has_replacements) {
                    return $DEFAULT_STR;
                }

                // Leave the variable intact on the page if no replacements were provided
                return $matches[0];
            }, $rawFile);

            if ($newRawFile === $rawFile) {
                break;
            }
            $rawFile = $newRawFile;
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
            $no_section_begin = '<div class="templater">— ';
            $no_section_end = $this->getLang('no_such_section');
            $no_section = $no_section_begin . $no_section_end . ' ';
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
            if ( str_contains($replacers, '=') && (substr(trim($replacers), -1) != '=') ){
                list($k, $v) = explode('=', $replacers, 2);
                $r['keys'] = BEGIN_REPLACE_DELIMITER.trim($k).END_REPLACE_DELIMITER;
                $r['vals'] = trim(str_replace('\|', '|', $v));
            }
        } else if ( is_array($replacers) ) {
            foreach($replacers as $rep) {
                if ( str_contains($rep, '=') && (substr(trim($rep), -1) != '=') ){
                    list($k, $v) = explode('=', $rep, 2);
                    $r['keys'][] = BEGIN_REPLACE_DELIMITER.trim($k).END_REPLACE_DELIMITER;
                    if (trim($v)[0] == '"' and trim($v)[-1] == '"') {
                        $r['vals'][] = substr(trim(str_replace('\|','|',$v)), 1, -1);
                    } else {
                        $r['vals'][] = trim(str_replace('\|','|',$v));
                    }
                }
            }
        } else {
            // This is an assertion failure. We should NEVER get here.
            //die("FATAL ERROR!  Unknown type passed to syntax_plugin_templater::massageReplaceMentArray() can't message syntax_plugin_templater::\$replacers!  Type is:".gettype($r)." Value is:".$r);
            $r['keys'] = null;
            $r['vals'] = null;
        }
        return $r;
    }
}
