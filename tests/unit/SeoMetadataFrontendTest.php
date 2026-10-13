<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;

class SeoMetadataFrontendTest extends TestCase
{
    /**
     * Pure renderer mimicking SeoMetadataService::renderHtml.
     */
    private function render(array $data, array $options = [], array &$renderedCache = []): string
    {
        $elementId = $data['elementId'] ?? 0;
        $siteId = $data['siteId'] ?? 1;
        $renderKey = "{$elementId}:{$siteId}";

        if (!empty($renderedCache[$renderKey]) && empty($options['force'])) {
            return '';
        }

        $title = $data['seoTitle'] ?? ($data['entryTitle'] ?? '');
        $description = $data['metaDescription'] ?? ($options['fallbackDescription'] ?? '');
        $canonicalUrl = $data['canonicalUrl'] ?? ($options['canonicalUrl'] ?? '');
        $ogImage = $data['ogImage'] ?? ($options['ogImage'] ?? '');
        $siteName = $data['siteName'] ?? 'Craft CMS Site';

        if (empty($title) && empty($description) && empty($canonicalUrl)) {
            return '';
        }

        $shouldCanonical = $options['canonical'] ?? true;
        $shouldOg = $options['og'] ?? ($options['openGraph'] ?? true);
        $shouldTwitter = $options['twitter'] ?? true;
        $twitterCard = $options['twitterCard'] ?? 'summary_large_image';

        $htmlParts = [];
        if (!empty($title)) {
            $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<title>{$escapedTitle}</title>";
        }

        if (!empty($description)) {
            $escapedDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<meta name=\"description\" content=\"{$escapedDesc}\">";
        }

        if ($shouldCanonical && !empty($canonicalUrl)) {
            $escapedUrl = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<link rel=\"canonical\" href=\"{$escapedUrl}\">";
        }

        if ($shouldOg) {
            if (!empty($title)) {
                $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:title\" content=\"{$escapedTitle}\">";
            }
            if (!empty($description)) {
                $escapedDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:description\" content=\"{$escapedDesc}\">";
            }
            if (!empty($canonicalUrl)) {
                $escapedUrl = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:url\" content=\"{$escapedUrl}\">";
            }
            $htmlParts[] = "<meta property=\"og:type\" content=\"website\">";
            $escapedSite = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<meta property=\"og:site_name\" content=\"{$escapedSite}\">";
            if (!empty($ogImage)) {
                $escapedImg = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:image\" content=\"{$escapedImg}\">";
            }
        }

        if ($shouldTwitter) {
            $escapedCard = htmlspecialchars($twitterCard, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<meta name=\"twitter:card\" content=\"{$escapedCard}\">";
            if (!empty($title)) {
                $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta name=\"twitter:title\" content=\"{$escapedTitle}\">";
            }
            if (!empty($description)) {
                $escapedDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta name=\"twitter:description\" content=\"{$escapedDesc}\">";
            }
            if (!empty($ogImage)) {
                $escapedImg = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta name=\"twitter:image\" content=\"{$escapedImg}\">";
            }
        }

        $renderedCache[$renderKey] = true;

        return implode("\n", $htmlParts);
    }

    public function testRendersStandardHtmlTags(): void
    {
        $cache = [];
        $data = [
            'elementId' => 10,
            'siteId' => 1,
            'seoTitle' => 'Craft CMS Development Services | Example Company',
            'metaDescription' => 'Discover expert Craft CMS development services for fast, scalable business websites.',
        ];

        $html = $this->render($data, [], $cache);

        $this->assertStringContainsString('<title>Craft CMS Development Services | Example Company</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Discover expert Craft CMS development services for fast, scalable business websites.">', $html);
    }

    public function testEscapesHtmlSpecialCharacters(): void
    {
        $cache = [];
        $data = [
            'elementId' => 11,
            'siteId' => 1,
            'seoTitle' => 'Tom & Jerry: "The Movie" <2026> & Beyond',
            'metaDescription' => 'Watch <script>alert("xss")</script> & discover "amazing" offers.',
        ];

        $html = $this->render($data, [], $cache);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert("xss")', $html);
        $this->assertStringContainsString('&quot;The Movie&quot;', $html);
        $this->assertStringContainsString('alert(&quot;xss&quot;)', $html);
        $this->assertStringContainsString('&lt;2026&gt;', $html);
        $this->assertStringContainsString('Tom &amp; Jerry', $html);
    }

    public function testPreventsDuplicateTagsInSameRequest(): void
    {
        $cache = [];
        $data = [
            'elementId' => 12,
            'siteId' => 1,
            'seoTitle' => 'Unique Page Title',
            'metaDescription' => 'Unique Page Description',
        ];

        // First call in layout template
        $firstCall = $this->render($data, [], $cache);
        $this->assertStringContainsString('<title>Unique Page Title</title>', $firstCall);

        // Second call (e.g. nested sub-template or duplicate include)
        $secondCall = $this->render($data, [], $cache);
        $this->assertSame('', $secondCall, 'Duplicate call in same request must return empty string to avoid duplicated <title> tags');

        // Third call with force option
        $forcedCall = $this->render($data, ['force' => true], $cache);
        $this->assertStringContainsString('<title>Unique Page Title</title>', $forcedCall);
    }

    public function testFallbackWhenSeoTitleUnset(): void
    {
        $cache = [];
        $data = [
            'elementId' => 13,
            'siteId' => 1,
            'seoTitle' => null, // Not set
            'entryTitle' => 'About Our Agency',
            'metaDescription' => null, // Not set
        ];

        $html = $this->render($data, ['fallbackDescription' => 'Default agency description.'], $cache);

        // Falls back to entry title
        $this->assertStringContainsString('<title>About Our Agency</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Default agency description.">', $html);
    }

    public function testMultisiteIsolationInOutput(): void
    {
        $cache = [];
        $site1Data = [
            'elementId' => 14,
            'siteId' => 1,
            'seoTitle' => 'English Page Title | Brand',
            'metaDescription' => 'English Description.',
        ];
        $site2Data = [
            'elementId' => 14,
            'siteId' => 2,
            'seoTitle' => 'German Page Title | Marke',
            'metaDescription' => 'German Description.',
        ];

        $htmlSite1 = $this->render($site1Data, [], $cache);
        $htmlSite2 = $this->render($site2Data, [], $cache);

        $this->assertStringContainsString('English Page Title | Brand', $htmlSite1);
        $this->assertStringNotContainsString('German Page Title', $htmlSite1);

        $this->assertStringContainsString('German Page Title | Marke', $htmlSite2);
        $this->assertStringNotContainsString('English Page Title', $htmlSite2);
    }

    public function testRendersCanonicalAndSocialTags(): void
    {
        $cache = [];
        $data = [
            'elementId' => 20,
            'siteId' => 1,
            'siteName' => 'Surrey Steels',
            'seoTitle' => 'Expert Steel Fabrication | Surrey Steels',
            'metaDescription' => 'Leading structural steel and architectural metalwork experts.',
            'canonicalUrl' => 'https://surreysteels.co.uk/services/fabrication',
            'ogImage' => 'https://surreysteels.co.uk/assets/og-fabrication.jpg',
        ];

        $html = $this->render($data, [], $cache);

        // Standard tags
        $this->assertStringContainsString('<title>Expert Steel Fabrication | Surrey Steels</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Leading structural steel and architectural metalwork experts.">', $html);

        // Canonical link
        $this->assertStringContainsString('<link rel="canonical" href="https://surreysteels.co.uk/services/fabrication">', $html);

        // OpenGraph tags
        $this->assertStringContainsString('<meta property="og:title" content="Expert Steel Fabrication | Surrey Steels">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Leading structural steel and architectural metalwork experts.">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://surreysteels.co.uk/services/fabrication">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:site_name" content="Surrey Steels">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://surreysteels.co.uk/assets/og-fabrication.jpg">', $html);

        // Twitter Card tags
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="Expert Steel Fabrication | Surrey Steels">', $html);
        $this->assertStringContainsString('<meta name="twitter:description" content="Leading structural steel and architectural metalwork experts.">', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="https://surreysteels.co.uk/assets/og-fabrication.jpg">', $html);
    }
}

