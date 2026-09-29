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

        $bgn = preg_quote(BEGIN_REPLACE_DELIMITER, '/');
        $end = preg_quote(END_REPLACE_DELIMITER, '/');

        // Protect blocks
        $protect = '<nowiki>.*?<\/nowiki>|%%.*?%%|<(?:code|file|php|html)(?: [^>]*)?>.*?<\/(?:code|file|php|html)>';

        $p1 = '(?<!' . $bgn . ')(?<![a-zA-Z0-9])' . $bgn . '([\w\-.]+)(?:\|((?:[^' . $bgn;
        $p2 = '\r\n\\\\]|\\\\.)*))?' . $end . '(?!' . $end . ')';
        $pattern = '/(' . $protect . ')|' . $p1 . $p2 . '/is';

        $event->data = preg_replace_callback($pattern, function ($matches) {
            if (!empty($matches[1])) {
                return $matches[1];
            }

            // Only process variables that explicitly have a fallback (e.g. @var|fallback@ or @var|@)
            if (isset($matches[3])) {
                return str_replace(
                    ['\\' . BEGIN_REPLACE_DELIMITER, '\\|', '\\\\'],
                    [BEGIN_REPLACE_DELIMITER, '|', '\\'],
                    $matches[3]
                );
            }

            // If there is no fallback (e.g. just @var@), leave it completely intact as raw syntax
            return $matches[0];
        }, $event->data);
    }
}
