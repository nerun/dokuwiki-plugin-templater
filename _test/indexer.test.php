<?php

namespace dokuwiki\plugin\templater\test;

use DokuWikiTest;

/**
 * @group plugin_templater
 * @group plugins
 */
class indexer_plugin_templater_test extends DokuWikiTest {

    protected $pluginsEnabled = array('templater');

    public function setUp(): void {
        parent::setUp();
        global $ID;
        $ID = 'test:start';
    }

    public function test_indexer_metadata() {
        // Setup template with an internal link
        saveWikiText('test_tmpl', 'Link to [[bob]] and [[charlie]]', 'Test setup');
        
        // Setup page including template AND normal link
        saveWikiText('test_page', 'Normal link to [[alice]]. {{template>test_tmpl}}', 'Test setup');
        
        // Render metadata (DokuWiki automatically runs metadata renderer)
        $meta = p_get_metadata('test_page', '', METADATA_RENDER_UNLIMITED);
        $refs = isset($meta['relation']['references']) ? $meta['relation']['references'] : [];
        
        // Preserves ordinary page links
        $this->assertArrayHasKey('alice', $refs);
        
        // Registers links from inside the template
        $this->assertArrayHasKey('bob', $refs);
        $this->assertArrayHasKey('charlie', $refs);

        // Registers the template itself as a reference (needed for Move plugin)
        $this->assertArrayHasKey('test_tmpl', $refs);

        // Test section extraction logic in metadata (only scans the section)
        saveWikiText('test_tmpl_sec', "==== Section 1 ====\n[[bob]]\n==== Section 2 ====\n[[charlie]]", 'Test setup');
        saveWikiText('test_page_sec', '{{template>test_tmpl_sec#Section 1}}', 'Test setup');
        $meta = p_get_metadata('test_page_sec', '', METADATA_RENDER_UNLIMITED);
        $refs = isset($meta['relation']['references']) ? $meta['relation']['references'] : [];
        
        $this->assertArrayHasKey('bob', $refs);
        $this->assertArrayNotHasKey('charlie', $refs); // Charlie is in section 2

        // Test parameters logic in metadata
        saveWikiText('test_tmpl_param', "==== Section 1 ====\n[[@user@]]\n", 'Test setup');
        saveWikiText('test_page_param', '{{template>test_tmpl_param#Section 1|user=dave}}', 'Test setup');
        $meta = p_get_metadata('test_page_param', '', METADATA_RENDER_UNLIMITED);
        $refs = isset($meta['relation']['references']) ? $meta['relation']['references'] : [];

        $this->assertArrayHasKey('dave', $refs); // Parameter substitution should be applied!

        // Test removing inclusion (removes obsolete references)
        saveWikiText('test_page_sec', 'No more template', 'Test setup');
        $meta = p_get_metadata('test_page_sec', '', METADATA_RENDER_UNLIMITED);
        $refs = isset($meta['relation']['references']) ? $meta['relation']['references'] : [];
        
        // Ensure stale links are totally gone
        $this->assertArrayNotHasKey('bob', $refs);
        $this->assertArrayNotHasKey('test_tmpl_sec', $refs);
    }
}
