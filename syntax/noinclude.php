<?php

/**
 * Templater Plugin: Hides literal <noinclude> tags when viewing the template directly
 */

class syntax_plugin_templater_noinclude extends DokuWiki_Syntax_Plugin
{
    public function getType()
    {
        return 'container';
    }

    public function getPType()
    {
        return 'normal';
    }

    public function getSort()
    {
        return 302;
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<noinclude>|</noinclude>', $mode, 'plugin_templater_noinclude');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // Return empty string to delete the literal tags themselves
        return '';
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        return true;
    }
}
