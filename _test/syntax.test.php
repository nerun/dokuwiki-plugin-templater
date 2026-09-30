<?php

namespace dokuwiki\plugin\templater\test;

use DokuWikiTest;

/**
 * @group plugin_templater
 * @group plugins
 */
class syntax_plugin_templater_test extends DokuWikiTest {

    protected $pluginsEnabled = array('templater');

    public function setUp(): void {
        parent::setUp();
        global $ID;
        $ID = 'test:start';
    }

    public function test_variable_fallback() {
        saveWikiText('test:test_template', 'Hello @name|Unknown@', 'Test setup');
        
        // Without parameter, it should use the fallback
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_template}}'), $info);
        $this->assertStringContainsString('Hello Unknown', $xhtml);

        // With parameter, it should use the parameter
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_template|name=Bob}}'), $info);
        $this->assertStringContainsString('Hello Bob', $xhtml);
    }

    public function test_prevent_crossline_text_corruption() {
        saveWikiText('test:test_email', "Contact: alice@example.org\nOwner: @name@", 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_email|name=Bob}}'), $info);
        $this->assertStringContainsString('alice@example.org', $xhtml);
        $this->assertStringContainsString('Owner: Bob', $xhtml);

        // Same line corruption
        saveWikiText('test:test_email_sameline', 'alice@example.org and bob@example.org Owner: @name@', 'Test setup');
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_email_sameline|name=Bob}}'), $info);
        $this->assertStringContainsString('alice@example.org and bob@example.org', $xhtml);
        $this->assertStringContainsString('Owner: Bob', $xhtml);
    }

    public function test_email_link_corruption() {
        // Prevent email addresses with domains inside links from matching the variable fallback regex
        // (Because if variable names allowed dots, @example.com|atendimento@ would match the fallback syntax)
        saveWikiText('test:test_email_link', 'Link: [[mailto:atendimento@example.com|atendimento@example.com]] and [[mailto:sales!@example.com|sales!@example.com]]', 'Test setup');
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_email_link|name=Bob}}'), $info);
        
        $this->assertStringContainsString('atendimento@example.com', $xhtml);
        $this->assertStringContainsString('sales!@example.com', $xhtml);
        // It should NOT output atendimentoatendimentoexample.com or sales!sales!example.com
        $this->assertStringNotContainsString('atendimentoatendimentoexample.com', $xhtml);
        $this->assertStringNotContainsString('sales!sales!example.com', $xhtml);
    }

    public function test_dots_in_variable_name() {
        saveWikiText('test:test_dots', 'A: report-@user.name|Guest@, B: report-@user.name@', 'Test setup');
        
        // Without parameter, A falls back to Guest, B remains unchanged or becomes empty depending on logic.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dots}}'), $info);
        $this->assertStringContainsString('A: report-Guest', $xhtml);
        
        // Without parameter, with DEFAULT_STR, B falls back to DEFAULT_STR
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dots|DEFAULT_STR=Missing}}'), $info);
        $this->assertStringContainsString('B: report-Missing', $xhtml);

        // With parameter explicitly passed
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dots|user.name=Bob|DEFAULT_STR=Missing}}'), $info);
        $this->assertStringContainsString('A: report-Bob', $xhtml);
        $this->assertStringContainsString('B: report-Bob', $xhtml);
    }

    public function test_placeholders_in_link_targets() {
        // Placeholders should be fully evaluated when present in link targets
        saveWikiText('test:test_link_targets', 'X: [[:@page|start@]], Y: [[docs:@page|start@|Label]]', 'Test setup');

        // Without parameter, fallback (start) is used
        // Since there is no explicit label, X uses 'start' as target and 'start' as label depending on Dokuwiki
        // Actually, since [[:start]] has no label, it links to start
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_link_targets}}'), $info);
        $this->assertStringContainsString('href="/doku.php?id=start"', $xhtml);
        $this->assertStringContainsString('href="/doku.php?id=docs:start"', $xhtml);
        $this->assertStringNotContainsString('@page|start@', $xhtml);

        // With parameter passed, it should use the parameter
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_link_targets|page=custom}}'), $info);
        $this->assertStringContainsString('href="/doku.php?id=custom"', $xhtml);
        $this->assertStringContainsString('href="/doku.php?id=docs:custom"', $xhtml);
        $this->assertStringNotContainsString('start', $xhtml);
    }

    public function test_literal_at_in_fallback() {
        // Escaped @ characters should become literal and NOT become active on subsequent passes
        saveWikiText('test:test_at', 'Email: @x|\@name\@@', 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_at|name=Bob}}'), $info);
        $this->assertStringContainsString('Email: @name@', $xhtml);
        $this->assertStringNotContainsString('Bob', $xhtml);
    }

    public function test_duplicate_parameters() {
        saveWikiText('test:test_dup', 'A: @a@', 'Test setup');
        
        // Duplicate handling: template @a@ with a=@a@|a=Hello previously produced Hello.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dup|a=@a@|a=Hello}}'), $info);
        $this->assertStringContainsString('A: Hello', $xhtml);
    }

    public function test_ordered_replacement() {
        // Multi-pass substitution should preserve original ordered replacement behavior.
        // With template @a@ and parameters b=Hello|a=@b@, the original produced an empty string.
        saveWikiText('test:test_order1', 'A: @a@', 'Test setup');
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_order1|b=Hello|a=@b@}}'), $info);
        // It outputs an empty string because @b@ is inserted after b was evaluated, leaving @b@ unmatched.
        // Then DEFAULT_STR (empty string) replaces unmatched @b@.
        $this->assertMatchesRegularExpression('/A:\s*<\/p>/', $xhtml);

        // However, a=@b@|b=Hello produced Hello.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_order1|a=@b@|b=Hello}}'), $info);
        $this->assertStringContainsString('A: Hello', $xhtml);
    }

    public function test_default_str() {
        saveWikiText('test:test_def', 'A: @a@, B: @b@', 'Test setup');
        
        // DEFAULT_STR as the first parameter
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_def|DEFAULT_STR=Missing|a=First}}'), $info);
        $this->assertStringContainsString('A: First, B: Missing', $xhtml);
    }

    public function test_quoted_empty_values_and_zero() {
        saveWikiText('test:test_val', 'A: @a@, B: @b@, C: @c|Fallback@', 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_val|a=0|b=""|c=}}'), $info);
        $this->assertStringContainsString('A: 0', $xhtml);
        // Assuming "" translates to empty string if quoted
        $this->assertStringContainsString('B: ', $xhtml);
        $this->assertStringNotContainsString('B: ""', $xhtml);
        // c= should pass an empty string, preventing fallback since the variable is explicitly passed
        $this->assertStringNotContainsString('C: Fallback', $xhtml);
    }

    public function test_empty_fallback() {
        saveWikiText('test:test_empty_fallback', 'A: @a|@', 'Test setup');
        
        // Without parameter, it should fall back to empty string
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_empty_fallback}}'), $info);
        $this->assertMatchesRegularExpression('/A:\s*<\/p>/', $xhtml);
    }

    public function test_literal_backreferences() {
        saveWikiText('test:test_backref', 'A: @a@', 'Test setup');
        
        // preg_replace interprets $1 or \1 as backreferences. We must ensure they are treated as literal.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_backref|a=$1}}'), $info);
        $this->assertStringContainsString('A: $1', $xhtml);
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_backref|a=\1}}'), $info);
        $this->assertStringContainsString('A: \1', $xhtml);
    }

    public function test_duplicate_default_str() {
        saveWikiText('test:test_dup_def', 'A: @missing@', 'Test setup');
        
        // DEFAULT_STR as duplicate should respect the first occurrence.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dup_def|x=OK|DEFAULT_STR=First|DEFAULT_STR=Second}}'), $info);
        $this->assertStringContainsString('A: First', $xhtml);

        // Also test when the first DEFAULT_STR is intentionally empty.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dup_def|x=OK|DEFAULT_STR=|DEFAULT_STR=Second}}'), $info);
        $this->assertMatchesRegularExpression('/A:\s*<\/p>/', $xhtml);
    }

    public function test_ignore_double_delimiters() {
        // Bureaucracy syntax (@@foo@@) should not be processed or destroyed by templater.
        saveWikiText('test:test_bureaucracy', 'A: @@foo@@, B: @@foo|bar@@', 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_bureaucracy|foo=replaced}}'), $info);
        $this->assertStringContainsString('A: @@foo@@', $xhtml);
        $this->assertStringContainsString('B: @@foo|bar@@', $xhtml);
    }

    public function test_protected_literal_contexts_variables() {
        global $conf;

        saveWikiText('test:test_protect_var', '<code>@var|Guest@</code> and <code>@passed@</code>', 'Test setup');

        // Test with enable_direct_preview_protected disabled (default)
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test:test_protect_var|passed=Replaced}}'), $info);
        $rawText = html_entity_decode(strip_tags($xhtml));
        
        // Variables should NOT be replaced, remaining literal
        $this->assertStringContainsString('@var|Guest@', $rawText);
        $this->assertStringContainsString('@passed@', $rawText);

        // Test with enable_direct_preview_protected enabled
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 1;
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test:test_protect_var|passed=Replaced}}'), $info);
        $rawText = html_entity_decode(strip_tags($xhtml));
        
        // Variables should be replaced by fallback/passed value
        $this->assertStringContainsString('Guest', $rawText);
        $this->assertStringContainsString('Replaced', $rawText);
        $this->assertStringNotContainsString('@var|Guest@', $rawText);
        $this->assertStringNotContainsString('@passed@', $rawText);
        
        // Restore default for other tests
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;
    }
}
