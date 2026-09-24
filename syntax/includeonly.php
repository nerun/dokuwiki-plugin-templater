<?php
/**
 * Templater Plugin: Hides <includeonly> content when viewing the template directly
 */

class syntax_plugin_templater_includeonly extends DokuWiki_Syntax_Plugin
{
    function getType() { return 'container'; }
    function getPType() { return 'normal'; }
    function getSort() { return 302; }

    function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<includeonly>.*?</includeonly>', $mode, 'plugin_templater_includeonly');
    }

    function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // Return an empty string, effectively deleting the block during standard parsing
        return ''; 
    }

    function render($mode, Doku_Renderer $renderer, $data)
    {
        return true; // We don't render anything
    }
}
