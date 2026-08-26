<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Market\Infrastructure\Security\SvgSanitizer;
use PHPUnit\Framework\TestCase;

final class SvgSanitizerTest extends TestCase
{
    private SvgSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new SvgSanitizer();
    }

    public function testSanitizesCleanSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="red"/></svg>';
        $clean = $this->sanitizer->sanitize($svg);

        self::assertNotNull($clean);
        self::assertStringContainsString('<circle', $clean);
        self::assertStringContainsString('fill="red"', $clean);
    }

    public function testStripsScriptTags(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10"/></svg>';
        $clean = $this->sanitizer->sanitize($svg);

        self::assertNotNull($clean);
        self::assertStringNotContainsString('<script', $clean);
        self::assertStringNotContainsString('alert', $clean);
        self::assertStringContainsString('<rect', $clean);
    }

    public function testStripsEventHandlers(): void
    {
        //phpcs:ignore
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><g onclick="evil()"><rect width="10" height="10" onerror="bad()"/></g></svg>';
        $clean = $this->sanitizer->sanitize($svg);

        self::assertNotNull($clean);
        self::assertStringNotContainsString('onload', $clean);
        self::assertStringNotContainsString('onclick', $clean);
        self::assertStringNotContainsString('onerror', $clean);
    }

    public function testStripsJavascriptUrls(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><rect width="10" height="10"/></a></svg>';
        $clean = $this->sanitizer->sanitize($svg);

        self::assertNotNull($clean);
        self::assertStringNotContainsString('javascript:', $clean);
    }

    public function testStripsMaliciousStyleTagsAndImportDirectives(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url("http://evil.com/xss.css"); .a { fill: red; }</style><rect width="10" height="10"/></svg>';
        $clean = $this->sanitizer->sanitize($svg);

        self::assertNotNull($clean);
        self::assertStringNotContainsString('@import', $clean);
        self::assertStringNotContainsString('evil.com', $clean);
        self::assertStringContainsString('<rect', $clean);
    }

    public function testStripsMaliciousInlineStyleAttributes(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10" style="background:url(javascript:alert(1))"/></svg>';
        $clean = $this->sanitizer->sanitize($svg);

        self::assertNotNull($clean);
        self::assertStringNotContainsString('javascript:', $clean);
        self::assertStringNotContainsString('style=', $clean);
    }

    public function testRejectsNonSvg(): void
    {
        self::assertNull($this->sanitizer->sanitize('<html><body>hello</body></html>'));
        self::assertNull($this->sanitizer->sanitize(''));
        self::assertNull($this->sanitizer->sanitize('plain text'));
    }
}
