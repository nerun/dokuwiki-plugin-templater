<?php

namespace dokuwiki\plugin\templater\test;

use DokuWikiTest;

/**
 * @group plugin_templater
 * @group plugins
 */
class EmailLinkTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['templater'];

    public function setUp(): void
    {
        parent::setUp();
        global $ID;
        $ID = 'caller';
    }

    public static function emailLinkProvider(): array
    {
        return [
            'bare email without parameters' => ['[[alice@example.com|alice@example.com]]', '', 'alice@example.com', 'alice@example.com'],
            'bare email with DEFAULT_STR' => ['[[alice@example.com|alice@example.com]]', 'DEFAULT_STR=Missing', 'alice@example.com', 'alice@example.com'],
            'bare email with matching domain key' => ['[[alice@example.com|alice@example.com]]', 'example.com=Changed', 'alice@example.com', 'alice@example.com'],
            'mailto email with matching domain key' => ['[[mailto:alice@example.com|alice@example.com]]', 'example.com=Changed', 'alice@example.com', 'alice@example.com'],
            'punctuation in local part' => ['[[sales!+tag@example.com|sales!+tag@example.com]]', 'name=Bob', 'sales!+tag@example.com', 'sales!+tag@example.com'],
            'placeholder in email label' => ['[[alice@example.com|@name@]]', 'name=Alice', 'alice@example.com', 'Alice'],
            'fallback in email label' => ['[[alice@example.com|@name|Alice@]]', '', 'alice@example.com', 'Alice'],
            'prefixed mailto fallback' => ['[[mailto:team-@address|support\@example.com@|Contact]]', '', 'team-support@example.com', 'Contact'],
            'prefixed mailto parameter' => ['[[mailto:team-@address|support\@example.com@|Contact]]', 'address=alice@example.com', 'team-alice@example.com', 'Contact'],
            'prefixed bare email fallback' => ['[[team-@address|support\@example.com@|Contact]]', '', 'team-support@example.com', 'Contact'],
            'prefixed bare email parameter' => ['[[team-@address|support\@example.com@|Contact]]', 'address=alice@example.com', 'team-alice@example.com', 'Contact'],
            'dotted address key fallback' => ['[[mailto:team-@user.address|support\@example.com@|Contact]]', '', 'team-support@example.com', 'Contact'],
            'dotted address key parameter' => ['[[mailto:team-@user.address|support\@example.com@|Contact]]', 'user.address=alice@example.com', 'team-alice@example.com', 'Contact'],
            'internal target fallback' => ['[[:@page|start@]]', '', 'id=start', 'start'],
            'namespaced target parameter' => ['[[docs:@page|start@|Label]]', 'page=custom', 'id=docs:custom', 'Label'],
        ];
    }

    /** @dataProvider emailLinkProvider */
    public function testEmailLink($source, $parameters, $target, $label): void
    {
        $page = 'email_link_' . substr(sha1($source . $parameters), 0, 12);
        saveWikiText($page, $source, 'Test setup');
        $call = '{{template>:' . $page . ($parameters === '' ? '' : '|' . $parameters) . '}}';
        $info = [];
        $html = p_render('xhtml', p_get_instructions($call), $info);

        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $links = $document->getElementsByTagName('a');
        $this->assertSame(1, $links->length, $html);
        $this->assertStringContainsString($target, $links->item(0)->getAttribute('href'), $html);
        $this->assertSame($label, $links->item(0)->textContent, $html);
    }
}
