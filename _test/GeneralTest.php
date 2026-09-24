<?php

namespace dokuwiki\plugin\templater\test;

use DokuWikiTest;

/**
 * General tests for the templater plugin
 *
 * @group plugin_templater
 * @group plugins
 */
class GeneralTest extends DokuWikiTest
{
    /**
     * Simple test to make sure the plugin.info.txt is in correct format
     */
    public function testPluginInfo(): void
    {
        $file = __DIR__ . '/../plugin.info.txt';
        $this->assertFileExists($file);

        $info = confToHash($file);

        $this->assertArrayHasKey('base', $info);
        $this->assertArrayHasKey('author', $info);
        $this->assertArrayHasKey('email', $info);
        $this->assertArrayHasKey('date', $info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('desc', $info);
        $this->assertArrayHasKey('url', $info);

        $this->assertEquals('templater', $info['base']);
        $this->assertMatchesRegularExpression('/^https?:\/\//', $info['url']);
        $this->assertTrue(\dokuwiki\MailUtils::isValid($info['email']));
        $this->assertMatchesRegularExpression('/^\d\d\d\d-\d\d-\d\d$/', $info['date']);
        $this->assertTrue(false !== strtotime($info['date']));
    }
}
