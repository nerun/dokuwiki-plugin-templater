<?php

/**
 * Action module for templater plugin: Replaces @var|fallback@ with its fallback value
 * globally before the wiki parses it, so it works inside <WRAP> tags when viewing the page directly.
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;

class action_plugin_templater_fallback extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'BEFORE', $this, 'applyFallbacks');
    }

    public function applyFallbacks(Doku_Event $event, $params)
    {
        if (!$this->getConf('enable_direct_preview')) {
            return;
        }

        // Prevent second-pass replacement inside template parsing.
        // syntax.php already resolves fallbacks before calling p_get_instructions().
        if (class_exists('syntax_plugin_templater') && !empty(syntax_plugin_templater::$pagestack)) {
            return;
        }

        if (!defined('BEGIN_REPLACE_DELIMITER')) {
            define('BEGIN_REPLACE_DELIMITER', '@');
        }
        if (!defined('END_REPLACE_DELIMITER')) {
            define('END_REPLACE_DELIMITER', '@');
        }

        $enableProtected = $this->getConf('enable_direct_preview_protected');

        $bgn = preg_quote(BEGIN_REPLACE_DELIMITER, '/');
        $end = preg_quote(END_REPLACE_DELIMITER, '/');

        // Protect standard blocks if not explicitly enabled
        if (!$enableProtected) {
            $protect1 = '<(nowiki|code|file|php|html)(?: [^>]*)?>.*?<\/\2>|%%.*?%%' .
                        '|(?:^|\n)[ \t]{2,}+(?![*\-][ \t]).*?(?=\n|$)';
        } else {
            // Maintains the 2 capturing groups so index offsets stay consistent
            $protect1 = '(?!)()';
        }

        // Always protect links and media to prevent cross-boundary fallback corruption (e.g., emails)
        $protect2 = '\[\[.*?\]\]|\{\{.*?\}\}';

        $p1 = '(?<!' . $bgn . ')' . $bgn . '([\w\-.]+)(?:\|((?:[^' . $bgn;
        $p2 = '\r\n\\\\]|\\\\.)*))?' . $end . '(?!' . $end . ')';

        // Group 1: $protect1, Group 2: tag name, Group 3: $protect2
        // Group 4: Variable name, Group 5: Fallback
        $pattern = '/(' . $protect1 . ')|(' . $protect2 . ')|' . $p1 . $p2 . '/is';

        $event->data = preg_replace_callback($pattern, function ($matches) use ($p1, $p2) {
            if (!empty($matches[3])) {
                // It's a DokuWiki link or media syntax
                $inner = substr($matches[3], 2, -2);
                // Skip literal emails and filenames without hiding complete fallbacks.
                // In docs:@page|start@ and team-@address|support\@example.com@,
                // the text following @ is a placeholder, not a literal address.
                $literalPattern = '[^\s@|]+@(?![\w\-.]+\|(?:[^@\r\n\\\\]|\\\\.)*@(?!@)(?=\||$))'
                    . '[^\s@|]+(?=[|\]}?# ]|$)(*SKIP)(*FAIL)|';
                $linkPattern = '/' . $literalPattern . $p1 . $p2 . '/is';

                $inner = preg_replace_callback($linkPattern, function ($m) {
                    if (isset($m[2])) {
                        return str_replace(
                            ['\\' . BEGIN_REPLACE_DELIMITER, '\\|', '\\\\'],
                            [BEGIN_REPLACE_DELIMITER, '|', '\\'],
                            $m[2]
                        );
                    }
                    return $m[0];
                }, $inner);

                $prefix = substr($matches[3], 0, 2);
                $suffix = substr($matches[3], -2);
                return $prefix . $inner . $suffix;
            }

            if (!empty($matches[1])) {
                return $matches[1]; // Protect code/nowiki/etc
            }

            // Only process variables that explicitly have a fallback (e.g. @var|fallback@ or @var|@)
            if (isset($matches[5])) {
                return str_replace(
                    ['\\' . BEGIN_REPLACE_DELIMITER, '\\|', '\\\\'],
                    [BEGIN_REPLACE_DELIMITER, '|', '\\'],
                    $matches[5]
                );
            }

            // If there is no fallback (e.g. just @var@), leave it completely intact as raw syntax
            return $matches[0];
        }, $event->data);
    }
}
