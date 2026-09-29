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
        saveWikiText('recipes:ingredients', 'Here are recipe ingredients.', 'Test setup');

        // Create pages that include the template
        saveWikiText('test:page1', '{{template>templates:ingredients}}', 'Test setup');
        saveWikiText('test:page2', '{{template>templates:ingredients#section|key=value}}', 'Test setup');
        
        // Relative reference inclusion
        saveWikiText('recipes:page', '{{template>ingredients}}', 'Test setup');
        
        // Trigger metadata rendering and indexing so relations are populated for the Move plugin
        idx_addPage('templates:ingredients');
        idx_addPage('recipes:ingredients');
        idx_addPage('test:page1');
        idx_addPage('test:page2');
        idx_addPage('recipes:page');

        // Verify the reference is saved in metadata
        $meta = p_get_metadata('test:page1', 'relation references');
        $this->assertArrayHasKey('templates:ingredients', $meta, 'Metadata reference not found for page1');

        $metaRel = p_get_metadata('recipes:page', 'relation references');
        $this->assertArrayHasKey('recipes:ingredients', $metaRel, 'Relative metadata reference not found for recipes:page');

        // Move the templates
        $this->assertTrue($move->movePage('templates:ingredients', 'templates:new_ingredients'), 'Move operation failed');
        $this->assertTrue($move->movePage('recipes:ingredients', 'recipes:new_ingredients'), 'Relative move operation failed');

        // Verify the syntax was updated
        $page1 = rawWiki('test:page1');
        $this->assertEquals('{{template>templates:new_ingredients}}', trim($page1));

        $page2 = rawWiki('test:page2');
        $this->assertEquals('{{template>templates:new_ingredients#section|key=value}}', trim($page2));

        $page3 = rawWiki('recipes:page');
        $this->assertEquals('{{template>new_ingredients}}', trim($page3));

        // Test moving the including page
        saveWikiText('recipes:move_test', '{{template>new_ingredients}}', 'Test setup');
        idx_addPage('recipes:move_test');
        $this->assertTrue($move->movePage('recipes:move_test', 'docs:moved_test'), 'Move including page operation failed');

        $page4 = rawWiki('docs:moved_test');
        $this->assertEquals('{{template>recipes:new_ingredients}}', trim($page4));
    }
}
