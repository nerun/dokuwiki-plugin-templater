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
        saveWikiText('test_template', 'Hello @name|Unknown@', 'Test setup');
        
        // Without parameter, it should use the fallback
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_template}}'), $info);
        $this->assertStringContainsString('Hello Unknown', $xhtml);

        // With parameter, it should use the parameter
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_template|name=Bob}}'), $info);
        $this->assertStringContainsString('Hello Bob', $xhtml);
    }

    public function test_prevent_crossline_text_corruption() {
        saveWikiText('test_email', "Contact: alice@example.org\nOwner: @name@", 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_email|name=Bob}}'), $info);
        $this->assertStringContainsString('alice@example.org', $xhtml);
        $this->assertStringContainsString('Owner: Bob', $xhtml);

        // Same line corruption
        saveWikiText('test_email_sameline', 'alice@example.org and bob@example.org Owner: @name@', 'Test setup');
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_email_sameline|name=Bob}}'), $info);
        $this->assertStringContainsString('alice@example.org and bob@example.org', $xhtml);
        $this->assertStringContainsString('Owner: Bob', $xhtml);
    }

    public function test_literal_at_in_fallback() {
        // Escaped @ characters should become literal and NOT become active on subsequent passes
        saveWikiText('test_at', 'Email: @x|\@name\@@', 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_at|name=Bob}}'), $info);
        $this->assertStringContainsString('Email: @name@', $xhtml);
        $this->assertStringNotContainsString('Bob', $xhtml);
    }

    public function test_duplicate_parameters() {
        saveWikiText('test_dup', 'A: @a@', 'Test setup');
        
        // Duplicate handling: template @a@ with a=@a@|a=Hello previously produced Hello.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dup|a=@a@|a=Hello}}'), $info);
        $this->assertStringContainsString('A: Hello', $xhtml);
    }

    public function test_ordered_replacement() {
        // Multi-pass substitution should preserve original ordered replacement behavior.
        // With template @a@ and parameters b=Hello|a=@b@, the original produced an empty string.
        saveWikiText('test_order1', 'A: @a@', 'Test setup');
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_order1|b=Hello|a=@b@}}'), $info);
        // It outputs an empty string because @b@ is inserted after b was evaluated, leaving @b@ unmatched.
        // Then DEFAULT_STR (empty string) replaces unmatched @b@.
        $this->assertStringContainsString('A: </p>', $xhtml);

        // However, a=@b@|b=Hello produced Hello.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_order1|a=@b@|b=Hello}}'), $info);
        $this->assertStringContainsString('A: Hello', $xhtml);
    }

    public function test_default_str() {
        saveWikiText('test_def', 'A: @a@, B: @b@', 'Test setup');
        
        // DEFAULT_STR as the first parameter
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_def|DEFAULT_STR=Missing|a=First}}'), $info);
        $this->assertStringContainsString('A: First, B: Missing', $xhtml);
    }

    public function test_quoted_empty_values_and_zero() {
        saveWikiText('test_val', 'A: @a@, B: @b@, C: @c|Fallback@', 'Test setup');
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_val|a=0|b=""|c=}}'), $info);
        $this->assertStringContainsString('A: 0', $xhtml);
        // Assuming "" translates to empty string if quoted
        $this->assertStringContainsString('B: ', $xhtml);
        $this->assertStringNotContainsString('B: ""', $xhtml);
        // c= should pass an empty string, preventing fallback since the variable is explicitly passed
        $this->assertStringNotContainsString('C: Fallback', $xhtml);
    }

    public function test_empty_fallback() {
        saveWikiText('test_empty_fallback', 'A: @a|@', 'Test setup');
        
        // Without parameter, it should fall back to empty string
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_empty_fallback}}'), $info);
        $this->assertStringContainsString('A: </p>', $xhtml);
    }

    public function test_literal_backreferences() {
        saveWikiText('test_backref', 'A: @a@', 'Test setup');
        
        // preg_replace interprets $1 or \1 as backreferences. We must ensure they are treated as literal.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_backref|a=$1}}'), $info);
        $this->assertStringContainsString('A: $1', $xhtml);
        
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_backref|a=\1}}'), $info);
        $this->assertStringContainsString('A: \1', $xhtml);
    }

    public function test_duplicate_default_str() {
        saveWikiText('test_dup_def', 'A: @missing@', 'Test setup');
        
        // DEFAULT_STR as duplicate should respect the first occurrence.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dup_def|x=OK|DEFAULT_STR=First|DEFAULT_STR=Second}}'), $info);
        $this->assertStringContainsString('A: First', $xhtml);

        // Also test when the first DEFAULT_STR is intentionally empty.
        $xhtml = p_render('xhtml', p_get_instructions('{{template>test_dup_def|x=OK|DEFAULT_STR=|DEFAULT_STR=Second}}'), $info);
        $this->assertStringContainsString('A: </p>', $xhtml);
    }
}
