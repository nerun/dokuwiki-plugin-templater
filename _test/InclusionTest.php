<?php

namespace dokuwiki\plugin\templater\test;

use DokuWikiTest;
use DOMDocument;

/**
 * @group plugin_templater
 * @group plugins
 */
class InclusionTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['templater'];

    public function setUp(): void
    {
        parent::setUp();
        global $ID, $conf;
        $ID = 'docs:caller';
        $conf['plugin']['templater']['namespace'] = '';
        $conf['plugin']['templater']['enable_direct_preview'] = 0;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 0;
    }

    private function renderFixture($source, $parameters = '')
    {
        $page = 'templates:review_' . substr(sha1($source . $parameters), 0, 12);
        saveWikiText($page, $source, 'Review fixture');
        $info = [];
        return p_render('xhtml', p_get_instructions('{{template>:' . $page . $parameters . '}}'), $info);
    }

    public function testCaseSensitiveParameterNames(): void
    {
        $html = $this->renderFixture('@Name@ / @name@', '|name=Bob|Name=Alice');
        $this->assertStringContainsString('Alice / Bob', $html);
    }

    public static function literalBlocks(): array
    {
        return [
            'code' => ['<code>@name@ / @missing|Guest@</code>'],
            'file' => ['<file>@name@ / @missing|Guest@</file>'],
            'nowiki' => ['<nowiki>@name@ / @missing|Guest@</nowiki>'],
            'percent' => ['%%@name@ / @missing|Guest@%%'],
            'indented' => ["  @name@ / @missing|Guest@\n"],
        ];
    }

    /** @dataProvider literalBlocks */
    public function testLegacyParametersInLiteralBlocks($source): void
    {
        $html = $this->renderFixture($source, '|name=Bob');
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Bob / Guest', $text, $html);
    }

    public function testRelativeLinksAndMedia(): void
    {
        $html = $this->renderFixture('[[target|Target]] {{picture.png|Picture}}');
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $this->assertSame('templates:target', $document->getElementsByTagName('a')->item(0)->getAttribute('data-wiki-id'), $html);
        $source = $document->getElementsByTagName('img')->item(0)->getAttribute('src');
        parse_str(parse_url($source, PHP_URL_QUERY), $query);
        $this->assertSame('templates:picture.png', $query['media'], $html);
    }

    public function testMetadataCorrectNamespaces(): void
    {
        saveWikiText('templates:review_card', '[[target]]', 'Review fixture');
        saveWikiText('docs:review_caller', '{{template>:templates:review_card}}', 'Review fixture');
        $meta = p_get_metadata('docs:review_caller', '', METADATA_RENDER_UNLIMITED);
        $refs = $meta['relation']['references'];
        $this->assertArrayHasKey('templates:review_card', $refs);
        $this->assertArrayHasKey('templates:target', $refs);
        $this->assertArrayNotHasKey('docs:target', $refs);

        saveWikiText('review_rootcard', '[[target]]', 'Review fixture');
        saveWikiText('docs:review_root_caller', '{{template>:review_rootcard}}', 'Review fixture');
        $meta = p_get_metadata('docs:review_root_caller', '', METADATA_RENDER_UNLIMITED);
        $refs = $meta['relation']['references'];
        $this->assertArrayHasKey('review_rootcard', $refs);
        $this->assertArrayNotHasKey('docs:review_rootcard', $refs);
        $this->assertArrayHasKey('target', $refs);
        $this->assertArrayNotHasKey('docs:target', $refs);
    }

    public static function protectedTags(): array
    {
        return [
            'code noinclude' => ['<code><noinclude>Example</noinclude></code>', '<noinclude>Example</noinclude>'],
            'file noinclude' => ['<file><noinclude>Example</noinclude></file>', '<noinclude>Example</noinclude>'],
            'nowiki noinclude' => ['<nowiki><noinclude>Example</noinclude></nowiki>', '<noinclude>Example</noinclude>'],
            'percent noinclude' => ['%%<noinclude>Example</noinclude>%%', '<noinclude>Example</noinclude>'],
            'code includeonly' => ['<code><includeonly>Example</includeonly></code>', '<includeonly>Example</includeonly>'],
            'file includeonly' => ['<file><includeonly>Example</includeonly></file>', '<includeonly>Example</includeonly>'],
            'nowiki includeonly' => ['<nowiki><includeonly>Example</includeonly></nowiki>', '<includeonly>Example</includeonly>'],
            'percent includeonly' => ['%%<includeonly>Example</includeonly>%%', '<includeonly>Example</includeonly>'],
            'uppercase code' => ['<CODE><NOINCLUDE>Example</NOINCLUDE></CODE>', '<NOINCLUDE>Example</NOINCLUDE>'],
            'indented noinclude' => ["  <noinclude>Example</noinclude>\n", '<noinclude>Example</noinclude>'],
        ];
    }

    /** @dataProvider protectedTags */
    public function testLiteralTagExamples($source, $expected): void
    {
        $text = html_entity_decode(strip_tags($this->renderFixture($source)), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString($expected, $text);
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview_protected'] = 1;
        $text = html_entity_decode(strip_tags($this->renderFixture($source)), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString($expected, $text);
    }

    public function testNormalInclusionTags(): void
    {
        $html = $this->renderFixture('Keep <noinclude>Remove</noinclude> <includeonly>Included</includeonly>');
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Included', $text);
        $this->assertStringNotContainsString('Remove', $text);
    }

    public function testNestedMetadataAndRecursion(): void
    {
        saveWikiText('nested:review_outer', '[[one]] {{template>review_inner}}', 'Review fixture');
        saveWikiText('nested:review_inner', '[[two]] {{:picture.png}} {{template>review_outer}}', 'Review fixture');
        saveWikiText('docs:review_nested', "====== Caller ======\n{{template>:nested:review_outer}}", 'Review fixture');
        $meta = p_get_metadata('docs:review_nested', '', METADATA_RENDER_UNLIMITED);
        $refs = $meta['relation']['references'];
        foreach (['nested:review_outer', 'nested:review_inner', 'nested:one', 'nested:two'] as $id) {
            $this->assertArrayHasKey($id, $refs);
        }
        $this->assertArrayNotHasKey('docs:review_inner', $refs);
        $this->assertArrayHasKey('picture.png', $meta['relation']['media']);
        $this->assertSame('Caller', $meta['title']);
        $this->assertSame([], \syntax_plugin_templater::$pagestack);

        // Removing the inclusion must remove nested references and media as well.
        saveWikiText('docs:review_nested', 'No template', 'Review fixture');
        $meta = p_get_metadata('docs:review_nested', '', METADATA_RENDER_UNLIMITED);
        $refs = $meta['relation']['references'] ?? [];
        $this->assertArrayNotHasKey('nested:review_inner', $refs);
        $this->assertArrayNotHasKey('nested:two', $refs);
        $this->assertArrayNotHasKey('picture.png', $meta['relation']['media'] ?? []);
    }

    public function testMetadataDoesNotReprocessEscapedFallbacks(): void
    {
        global $conf;
        $conf['plugin']['templater']['enable_direct_preview'] = 1;
        $source = '[[@x|\\@name\\|Guest\\@@]]';
        saveWikiText('templates:review_escaped', $source, 'Review fixture');
        $call = '{{template>:templates:review_escaped}}';
        saveWikiText('docs:review_escaped', $call, 'Review fixture');

        $info = [];
        $html = p_render('xhtml', p_get_instructions($call), $info);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $target = $document->getElementsByTagName('a')->item(0)->getAttribute('data-wiki-id');
        $this->assertSame('templates:name', $target, $html);

        $meta = p_get_metadata('docs:review_escaped', '', METADATA_RENDER_UNLIMITED);
        $this->assertArrayHasKey($target, $meta['relation']['references']);
        $this->assertArrayNotHasKey('templates:guest', $meta['relation']['references']);
        $this->assertSame([], \syntax_plugin_templater::$pagestack);
    }

    public function testMetadataRespectsInclusionTags(): void
    {
        saveWikiText('templates:review_tags', '[[visible]] <noinclude>[[hidden]]</noinclude> <includeonly>[[included]]</includeonly> <code><includeonly>[[literal]]</includeonly></code>', 'Review fixture');
        saveWikiText('docs:review_tags', '{{template>:templates:review_tags}}', 'Review fixture');
        $meta = p_get_metadata('docs:review_tags', '', METADATA_RENDER_UNLIMITED);
        $refs = $meta['relation']['references'];
        $this->assertArrayHasKey('templates:visible', $refs);
        $this->assertArrayHasKey('templates:included', $refs);
        $this->assertArrayNotHasKey('templates:hidden', $refs);
        $this->assertArrayNotHasKey('templates:literal', $refs);
    }

    public function testDefaultNamespaceWithExplicitRoot(): void
    {
        global $conf;
        $conf['plugin']['templater']['namespace'] = 'templates';
        saveWikiText('templates:review_default', 'Default template', 'Review fixture');
        saveWikiText('review_absolute', 'Root template', 'Review fixture');
        $info = [];
        $html = p_render('xhtml', p_get_instructions('{{template>review_default}} {{template>:review_absolute}}'), $info);
        $this->assertStringContainsString('Default template', $html);
        $this->assertStringContainsString('Root template', $html);
    }
}
