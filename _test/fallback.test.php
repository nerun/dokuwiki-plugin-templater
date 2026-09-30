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

        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $xhtml);
        $links = $document->getElementsByTagName('a');
        $this->assertSame(3, $links->length, $xhtml);
        $this->assertSame('start', $links->item(1)->getAttribute('data-wiki-id'), $xhtml);
        $this->assertSame('start', $links->item(1)->textContent, $xhtml);
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

    public static function fallback_link_provider() {
        return [
            'root target' => ['[[:@page|start@]]', '[[:start]]'],
            'namespace target' => ['[[docs:@page|start@|Label]]', '[[docs:start|Label]]'],
            'prefixed mailto' => ['[[mailto:team-@address|support\\@example.com@|Contact]]', '[[mailto:team-support@example.com|Contact]]'],
            'prefixed bare email' => ['[[team-@address|support\\@example.com@|Contact]]', '[[team-support@example.com|Contact]]'],
            'dotted key' => ['[[mailto:team-@user.address|support\\@example.com@|Contact]]', '[[mailto:team-support@example.com|Contact]]'],
            'bare email' => ['[[alice@example.com|alice@example.com]]', '[[alice@example.com|alice@example.com]]'],
            'mailto with punctuation' => ['[[mailto:sales!+tag@example.com|sales!+tag@example.com]]', '[[mailto:sales!+tag@example.com|sales!+tag@example.com]]'],
            'email with fallback label' => ['[[alice@example.com|@label|Alice@]]', '[[alice@example.com|Alice]]'],
            'link label' => ['[[start|@label|Home@]]', '[[start|Home]]'],
            'escaped pipe in label' => ['[[start|@label|A\\|B@]]', '[[start|A|B]]'],
            'empty fallback' => ['[[start|@label|@]]', '[[start|]]'],
            'media caption' => ['{{:picture.png|@caption|Photo@}}', '{{:picture.png|Photo}}'],
            'literal media filename' => ['{{:photo@2x.jpg|photo@2x.jpg}}', '{{:photo@2x.jpg|photo@2x.jpg}}'],
            'media filename with fallback caption' => ['{{https://example.org/photo@2x.jpg|@caption|Photo@}}', '{{https://example.org/photo@2x.jpg|Photo}}'],
            'literal former token' => ["[[start|\x010\x01 @label|Home@]]", "[[start|\x010\x01 Home]]"],
        ];
    }

    /** @dataProvider fallback_link_provider */
    public function test_fallback_link_rendering($source, $expected) {
        global $conf, $ID;
        $ID = 'caller';
        $conf['plugin']['templater']['enable_direct_preview'] = 1;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;

        $event = new \Doku_Event('PARSER_WIKITEXT_PREPROCESS', $source);
        plugin_load('action', 'templater_fallback')->applyFallbacks($event, []);
        $this->assertSame($expected, $event->data);

        // Compare the actual XHTML with DokuWiki rendering the resolved markup.
        // This checks destinations and captions, rather than words occurring anywhere.
        $info = [];
        $actual = p_render('xhtml', p_get_instructions($source), $info);
        $conf['plugin']['templater']['enable_direct_preview'] = 0;
        $expectedXhtml = p_render('xhtml', p_get_instructions($expected), $info);
        $this->assertSame($expectedXhtml, $actual);
    }

    public function test_direct_preview_tags() {
        global $conf;

        $source = "<code>Test <noinclude>NoInclude Content</noinclude> and <includeonly>IncludeOnly Content</includeonly> and @var|Guest@.</code>";
        
        // When enable_direct_preview_protected is disabled, the tags and variables inside code are protected
        $conf['plugin']['templater']['enable_direct_preview'] = 1;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;
        
        $info = [];
        $actual_disabled = p_render('xhtml', p_get_instructions($source), $info);
        $raw_disabled = html_entity_decode(strip_tags($actual_disabled));
        
        // Assert that tags are protected and remain literal
        $this->assertStringContainsString('<noinclude>NoInclude Content</noinclude>', $raw_disabled);
        $this->assertStringContainsString('<includeonly>IncludeOnly Content</includeonly>', $raw_disabled);
        $this->assertStringContainsString('@var|Guest@', $raw_disabled);

        // When enable_direct_preview_protected is enabled, the tags and variables are processed
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 1;
        
        $actual_enabled = p_render('xhtml', p_get_instructions($source), $info);
        $raw_enabled = html_entity_decode(strip_tags($actual_enabled));

        // Assert that tags are processed (noinclude stripped, includeonly completely hidden, variables replaced)
        $this->assertStringContainsString('NoInclude Content', $raw_enabled);
        $this->assertStringNotContainsString('<noinclude>', $raw_enabled);
        $this->assertStringNotContainsString('IncludeOnly Content', $raw_enabled); // Hidden
        $this->assertStringNotContainsString('<includeonly>', $raw_enabled);
        $this->assertStringContainsString('Guest', $raw_enabled);
        $this->assertStringNotContainsString('@var|Guest@', $raw_enabled);

        // Scenario 3: enable_direct_preview = 0, enable_direct_preview_protected = 1
        // Variables should NOT be replaced, but tags inside code should STILL be processed (stripped/hidden)
        $conf['plugin']['templater']['enable_direct_preview'] = 0;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 1;

        $actual_isolated = p_render('xhtml', p_get_instructions($source), $info);
        $raw_isolated = html_entity_decode(strip_tags($actual_isolated));

        // Tags are processed
        $this->assertStringContainsString('NoInclude Content', $raw_isolated);
        $this->assertStringNotContainsString('<noinclude>', $raw_isolated);
        $this->assertStringNotContainsString('IncludeOnly Content', $raw_isolated); // Hidden
        $this->assertStringNotContainsString('<includeonly>', $raw_isolated);
        
        // Variables are completely untouched (remains literal)
        $this->assertStringContainsString('@var|Guest@', $raw_isolated);
    }
}
