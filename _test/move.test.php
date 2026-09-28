<?php
/**
 * Tests the move support for adapting the syntax of the templater plugin
 *
 * @group plugin_templater
 * @group plugins
 */
class plugin_templater_move_test extends DokuWikiTest {

    protected $pluginsEnabled = array('templater', 'move');

    public function setUp(): void {
        parent::setUp();
        global $USERINFO;
        $USERINFO['grps'] = array('admin');
        $_SERVER['REMOTE_USER'] = 'admin';
    }

    public function test_move_support() {
        /** @var helper_plugin_move_op $move */
        $move = plugin_load('helper', 'move_op');
        if (!$move) {
            $this->markTestSkipped('The move plugin is not installed.');
            return;
        }

        // Create a template page
        saveWikiText('templates:ingredients', 'Here are some ingredients.', 'Test setup');

        // Create pages that include the template
        saveWikiText('test:page1', '{{template>templates:ingredients}}', 'Test setup');
        saveWikiText('test:page2', '{{template>templates:ingredients#section|key=value}}', 'Test setup');
        
        // Trigger metadata rendering so relations are indexed
        p_get_metadata('test:page1');
        p_get_metadata('test:page2');

        // Verify the reference is saved in metadata
        $meta = p_get_metadata('test:page1', 'relation references');
        $this->assertArrayHasKey('templates:ingredients', $meta, 'Metadata reference not found for page1');

        // Move the template
        $this->assertTrue($move->movePage('templates:ingredients', 'templates:new_ingredients'), 'Move operation failed');

        // Verify the syntax was updated
        $page1 = rawWiki('test:page1');
        $this->assertEquals('{{template>templates:new_ingredients}}', trim($page1));

        $page2 = rawWiki('test:page2');
        $this->assertEquals('{{template>templates:new_ingredients#section|key=value}}', trim($page2));
    }
}
