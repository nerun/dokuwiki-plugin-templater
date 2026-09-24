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
        $controller->register_hook('PLUGIN_MOVE_HANDLERS_REGISTER', 'BEFORE', $this, 'handle_move_register');
    }

    public function handle_move_register(Doku_Event $event, $params)
    {
        $event->data['handlers']['templater'] = [$this, 'rewrite_templater'];
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
    public function rewrite_templater($match, $pos, $state, $plugin, helper_plugin_move_handler $handler)
    {
        if (!preg_match('/^(\{\{template>)([^#|}]+)(.*?\}\})$/s', $match, $m)) {
            return $match;
        }

        $prefix = $m[1];
        $page = $m[2];
        $suffix = $m[3];

        if (method_exists($handler, 'adaptRelativeId')) { // move plugin before version 2015-05-16
            $newpage = $handler->adaptRelativeId($page);
        } else {
            $newpage = $handler->resolveMoves($page, 'page');
            $newpage = $handler->relativeLink($page, $newpage, 'page');
        }

        if ($newpage == $page) {
            return $match;
        } else {
            return $prefix . $newpage . $suffix;
        }
    }
}
