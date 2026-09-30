<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Parsing\Handler;

/**
 * Templater Plugin: Hides <includeonly> content when viewing the template directly
 */
class syntax_plugin_templater_includeonly extends SyntaxPlugin
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
        $this->Lexer->addSpecialPattern('<includeonly>.*?</includeonly>', $mode, 'plugin_templater_includeonly');
    }

    public function handle($match, $state, $pos, Handler $handler)
    {
        // Return an empty string, effectively deleting the block during standard parsing
        return '';
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        return true; // We don't render anything
    }
}
