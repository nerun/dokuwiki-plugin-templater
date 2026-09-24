<?php
/**
 * Templater Plugin: Hides literal <noinclude> tags when viewing the template directly
 */

class syntax_plugin_templater_noinclude extends DokuWiki_Syntax_Plugin
{
    function getType() { return 'container'; }
    function getPType() { return 'normal'; }
    function getSort() { return 302; }

    function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<noinclude>|</noinclude>', $mode, 'plugin_templater_noinclude');
    }

    function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // Return empty string to delete the literal tags themselves
        return '';
    }

    function render($mode, Doku_Renderer $renderer, $data)
    {
        return true;
    }
}
