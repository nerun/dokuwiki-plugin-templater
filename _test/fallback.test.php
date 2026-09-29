<?php

namespace dokuwiki\plugin\templater\test;

use DokuWikiTest;

/**
 * @group plugin_templater
 * @group plugins
 */
class fallback_plugin_templater_test extends DokuWikiTest {

    protected $pluginsEnabled = array('templater');

    public function test_fallback_enabled() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;

        $text = 'Hello @name|Guest@';
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals('Hello Guest', $event->data);
    }

    public function test_fallback_disabled() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 0;

        $text = 'Hello @name|Guest@';
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals('Hello @name|Guest@', $event->data);
    }

    public function test_fallback_protected_blocks() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;

        $text = '<code>@name|Guest@</code> %%@name|Guest@%% <nowiki>@name|Guest@</nowiki>';
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals('<code>@name|Guest@</code> %%@name|Guest@%% <nowiki>@name|Guest@</nowiki>', $event->data);
    }

    public function test_fallback_protected_blocks_enabled() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 1;

        $text = '<code>@name|Guest@</code> %%@name|Guest@%% <nowiki>@name|Guest@</nowiki>';
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals('<code>Guest</code> %%Guest%% <nowiki>Guest</nowiki>', $event->data);
    }

    public function test_fallback_email_corruption() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;

        $text = '[[mailto:alice@example.com|alice@example.com]]';
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals('[[mailto:alice@example.com|alice@example.com]]', $event->data);
    }

    public function test_fallback_double_processing() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;

        saveWikiText('test_fallback_double', 'Email: @name|Guest@', 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_fallback_double|name=\@name\|Guest\@}}'), $info);
        
        $this->assertStringContainsString('Email: \@name|Guest\@', $xhtml);
        // Ensure "Guest" without "@name|" is not present, meaning it wasn't double-processed
        $this->assertStringNotContainsString('Email: Guest', $xhtml);
    }
}
