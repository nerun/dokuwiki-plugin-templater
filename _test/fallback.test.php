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

    public function test_fallback_regression_emails() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;

        $text = '[[mailto:sales_@example.com|sales_@example.com]] [[mailto:sales!@example.com|sales!@example.com]]';
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals('[[mailto:sales_@example.com|sales_@example.com]] [[mailto:sales!@example.com|sales!@example.com]]', $event->data);
    }

    public function test_fallback_regression_protected_html() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;

        $text = '<code>Literal </html> @name|Guest@</code>';
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals('<code>Literal </html> @name|Guest@</code>', $event->data);
    }

    public function test_fallback_regression_indented_code() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;

        $text = "  @name|Guest@\nnormal @name|Guest@";
        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $text);
        
        $plugin = plugin_load('action', 'templater_fallback');
        $plugin->applyFallbacks($event, []);
        
        $this->assertEquals("  @name|Guest@\nnormal Guest", $event->data);
    }

    public function test_fallback_regression_link_pipes() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;

        $text = "[[start|@label|Home@]]\n[[:@page|start@]]\n{{:picture.png|@caption|Photo@}}";
        
        $info = [];
        $xhtml = p_render('xhtml', p_get_instructions($text), $info);
        
        $this->assertStringContainsString('Home', $xhtml);
        $this->assertStringContainsString('start', $xhtml);
        $this->assertStringContainsString('picture.png', $xhtml);
        $this->assertStringContainsString('Photo', $xhtml);
        
        // Ensure placeholders are gone
        $this->assertStringNotContainsString('@label', $xhtml);
        $this->assertStringNotContainsString('@page', $xhtml);
        $this->assertStringNotContainsString('@caption', $xhtml);
    }

    public function test_fallback_regression_lists() {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;

        $text = "  * @name|Guest@\n  - @name|Guest@\n\n  @name|Guest@";
        
        $info = [];
        $xhtml = p_render('xhtml', p_get_instructions($text), $info);
        
        // Should parse as lists with fallback resolved
        $this->assertStringContainsString('<ul>', $xhtml);
        $this->assertStringContainsString('<li class="level1"><div class="li"> Guest</div>', $xhtml);
        
        // Should parse as protected code with fallback intact
        $this->assertStringContainsString('<pre class="code">@name|Guest@</pre>', $xhtml);
    }
}
