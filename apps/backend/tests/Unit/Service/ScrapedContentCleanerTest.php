<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ScrapedContentCleaner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class ScrapedContentCleanerTest extends TestCase
{
    private ScrapedContentCleaner $cleaner;

    protected function setUp(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturnCallback(function (string $html): string {
            return strip_tags($html, '<p><br><strong><em><a><ul><ol><li><h2><h3><h4><blockquote><img>');
        });

        $this->cleaner = new ScrapedContentCleaner($sanitizer);
    }

    #[Test]
    public function removesScriptAndStyleTags(): void
    {
        $html = '<p>Aceasta este o propoziție completă de test suficient de lungă.</p>'
            . '<script>alert("xss")</script><style>.x{color:red}</style>'
            . '<p>Alt paragraf cu text suficient de lung pentru a trece filtrul.</p>';
        $clean = $this->cleaner->clean($html);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('style', $clean);
        $this->assertStringContainsString('propoziție completă', $clean);
        $this->assertStringContainsString('paragraf cu text', $clean);
    }

    #[Test]
    public function removesNavHeaderFooterAside(): void
    {
        $html = '<nav>Navigation links here</nav>'
            . '<header>Header content for the site</header>'
            . '<p>Conținutul principal al articolului care este relevant pentru cititor.</p>'
            . '<footer>Footer with copyright info</footer>'
            . '<aside>Sidebar content here</aside>';
        $clean = $this->cleaner->clean($html);

        $this->assertStringNotContainsString('Navigation', $clean);
        $this->assertStringNotContainsString('Header content', $clean);
        $this->assertStringNotContainsString('Footer', $clean);
        $this->assertStringNotContainsString('Sidebar', $clean);
        $this->assertStringContainsString('principal al articolului', $clean);
    }

    #[Test]
    public function removesIframeAndForm(): void
    {
        $html = '<iframe src="x">frame content</iframe>'
            . '<form action="/submit"><input type="text" /></form>'
            . '<p>Conținutul util al paginii care trebuie păstrat după curățare.</p>';
        $clean = $this->cleaner->clean($html);

        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('form', $clean);
        $this->assertStringContainsString('util al paginii', $clean);
    }

    #[Test]
    public function handlesFullHtmlPage(): void
    {
        $html = '<html><head><title>Test</title></head><body>'
            . '<article><p>Articolul principal cu conținut relevant și suficient de lung.</p></article>'
            . '</body></html>';
        $clean = $this->cleaner->clean($html);

        $this->assertStringNotContainsString('<html', $clean);
        $this->assertStringNotContainsString('<head', $clean);
        $this->assertStringContainsString('principal cu conținut', $clean);
    }

    #[Test]
    public function extractLeadDoesNotCutMidSentence(): void
    {
        $html = '<p>Prima propoziție completă aici. A doua propoziție este mai lungă și are mai multe cuvinte. A treia propoziție.</p>';
        $lead = $this->cleaner->extractLead($html, 60);

        $this->assertStringEndsWith('.', $lead);
        $this->assertLessThanOrEqual(60, mb_strlen($lead));
    }

    #[Test]
    public function extractLeadFromEmptyReturnsEmpty(): void
    {
        $lead = $this->cleaner->extractLead('');
        $this->assertSame('', $lead);
    }

    #[Test]
    public function extractLeadReturnsFirstSentenceIfShort(): void
    {
        $html = '<p>Propoziție scurtă de test. Propoziția a doua care depășește limita maximă stabilită de parametrul funcției.</p>';
        $lead = $this->cleaner->extractLead($html, 50);

        $this->assertSame('Propoziție scurtă de test.', $lead);
    }
}
