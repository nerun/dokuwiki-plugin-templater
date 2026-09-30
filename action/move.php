<?php

/**
 * Action module for templater plugin: Adapts syntax when a page is moved using the move plugin
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;

class action_plugin_templater_move extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_MOVE_HANDLERS_REGISTER', 'BEFORE', $this, 'handleMoveRegister');
    }

    public function handleMoveRegister(Doku_Event $event, $params)
    {
        $event->data['handlers']['templater'] = [$this, 'rewriteTemplater'];
    }

    /**
     * Rewrite the template instruction when a page is moved
     *
     * @param string $match The matched syntax
     * @param int $pos The position of the match
     * @param int $state The state of the lexer
     * @param string $plugin The plugin name
     * @param helper_plugin_move_handler $handler The move handler
     * @return string The rewritten syntax
     */
    public function rewriteTemplater($match, $pos, $state, $plugin, helper_plugin_move_handler $handler)
    {
        if (!preg_match('/^(\{\{template>)([^#|}]+)(.*?\}\})$/s', $match, $m)) {
            return $match;
        }

        $prefix = $m[1];
        $page = $m[2];
        $suffix = $m[3];

        // Forward-compatibility with the default namespace feature (from feat/backport-yatp-features)
        $syntax = plugin_load('syntax', 'templater');
        $defaultNamespace = ($syntax && method_exists($syntax, 'getConf')) ? $syntax->getConf('namespace') : '';

        $resolvedPage = $page;
        if (!empty($defaultNamespace) && !preg_match('/^[:.]/', $page)) {
            $resolvedPage = $defaultNamespace . ':' . $page;
        }

        if (method_exists($handler, 'adaptRelativeId')) { // move plugin before version 2015-05-16
            $newpage = $handler->adaptRelativeId($resolvedPage);
        } else {
            $newpage = $handler->resolveMoves($resolvedPage, 'page');
            $newpage = $handler->relativeLink($resolvedPage, $newpage, 'page');
        }

        // If the link was resolved using the default namespace, and the new page is still in that namespace,
        // we can strip the namespace prefix to keep the syntax clean.
        if (!empty($defaultNamespace) && strpos($newpage, $defaultNamespace . ':') === 0) {
            $clean_newpage = substr($newpage, strlen($defaultNamespace) + 1);
            if (strpos($clean_newpage, ':') === false) {
                $newpage = $clean_newpage;
            }
        }

        if ($newpage == $resolvedPage || $newpage == $page) {
            return $match;
        } else {
            return $prefix . $newpage . $suffix;
        }
    }
}
