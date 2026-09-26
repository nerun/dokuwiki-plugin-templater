<?php

namespace dokuwiki\plugin\templater\test;

use DokuWikiTest;
use DOMDocument;
use DOMXPath;
use splitbrain\PHPArchive\Zip;

/**
 * Integration tests using the real ODT renderer and generated archive.
 *
 * @group plugin_templater
 * @group plugin_templater_odt
 * @group plugins
 */
class OdtTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['templater', 'odt'];

    public function setUp(): void
    {
        parent::setUp();
        global $ID;
        $ID = 'odt_test:start';
        $this->assertFileExists(
            DOKU_PLUGIN . 'odt/renderer/page.php',
            'Install the ODT dependency with bash lib/plugins/templater/_test/pretest.sh'
        );
        saveWikiText(
            'odt_test:template',
            "====== Selected ======\nHello @name@\n\n====== Other ======\nOutside section\n",
            'ODT test fixture'
        );
    }

    /** Render a page and read the document body from the resulting ODT archive. */
    private function exportBody(string $source): DOMXPath
    {
        $info = [];
        // ODT predates current PHP/PHPUnit: ignore only its deprecations and
        // its known collision with the test bootstrap's SIMPLE_TEST constant.
        $previousHandler = null;
        $odtRoot = realpath(DOKU_PLUGIN . 'odt') . DIRECTORY_SEPARATOR;
        $previousHandler = set_error_handler(static function (
            $severity,
            $message,
            $file,
            $line
        ) use (
            &$previousHandler,
            $odtRoot
        ) {
            if (strpos($file, $odtRoot) === 0) {
                if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                    return true;
                }
                if (
                    $file === $odtRoot . 'helper/dwcssloader.php'
                    && strpos($message, 'Constant SIMPLE_TEST already defined') === 0
                ) {
                    return true;
                }
            }
            return $previousHandler ? $previousHandler($severity, $message, $file, $line) : false;
        });
        try {
            $odt = p_render('odt_page', p_get_instructions($source), $info);
        } finally {
            restore_error_handler();
        }
        $this->assertIsString($odt);
        $this->assertSame('PK', substr($odt, 0, 2));

        $file = tempnam(TMP_DIR, 'templater-odt-');
        $this->assertNotFalse($file);
        file_put_contents($file, $odt);
        $directory = $file . '-contents';
        $zip = new Zip();
        $zip->open($file);
        $zip->extract($directory, '', '', '/^(content\.xml|mimetype)$/');
        $this->assertSame(
            'application/vnd.oasis.opendocument.text',
            file_get_contents($directory . '/mimetype')
        );

        $document = new DOMDocument();
        $this->assertTrue($document->loadXML(file_get_contents($directory . '/content.xml'), LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $this->assertSame(1, $xpath->query('/office:document-content/office:body/office:text')->length);
        return $xpath;
    }

    private function bodyText(DOMXPath $xpath): string
    {
        return $xpath->evaluate('string(/office:document-content/office:body/office:text)');
    }

    public function testWholeTemplateWithParameter(): void
    {
        $text = $this->bodyText($this->exportBody('{{template>:odt_test:template|name=Bob}}'));
        $this->assertStringContainsString('Hello Bob', $text);
        $this->assertStringContainsString('Outside section', $text);
        $this->assertStringNotContainsString('@name@', $text);
    }

    public function testSectionWithoutEmptyHeading(): void
    {
        $xpath = $this->exportBody('{{template>:odt_test:template#selected|name=Bob}}');
        $text = $this->bodyText($xpath);
        $this->assertStringContainsString('Hello Bob', $text);
        $this->assertStringNotContainsString('Selected', $text);
        $this->assertStringNotContainsString('Outside section', $text);
        $this->assertSame(0, $xpath->query('//office:body//text:h')->length);
    }

    public function testSurroundingTextIsPreserved(): void
    {
        $text = $this->bodyText($this->exportBody(
            "Before inclusion\n\n{{template>:odt_test:template|name=Bob}}\n\nAfter inclusion"
        ));
        $this->assertMatchesRegularExpression('/Before inclusion.*Hello Bob.*After inclusion/s', $text);
    }

    public function testTwoInclusionsInOneDocument(): void
    {
        $text = $this->bodyText($this->exportBody(
            "{{template>:odt_test:template|name=Bob}}\n\n{{template>:odt_test:template|name=Alice}}"
        ));
        $this->assertSame(1, substr_count($text, 'Hello Bob'));
        $this->assertSame(1, substr_count($text, 'Hello Alice'));
        $this->assertSame(2, substr_count($text, 'Outside section'));
        $this->assertMatchesRegularExpression('/Hello Bob.*Hello Alice/s', $text);
    }
}
