<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\models\Settings;
use abdulkadiragoliya\contentintelligence\services\vector\QdrantClient;

class SecurityTest extends TestCase
{
    public function testSsrfEndpointValidation(): void
    {
        // Valid endpoints
        $this->assertTrue(QdrantClient::isValidEndpoint('http://localhost:6333'));
        $this->assertTrue(QdrantClient::isValidEndpoint('https://qdrant.cluster.cloud.io:6333'));
        $this->assertTrue(QdrantClient::isValidEndpoint('http://127.0.0.1:6333'));

        // Dangerous / SSRF endpoints blocked
        $this->assertFalse(QdrantClient::isValidEndpoint('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(QdrantClient::isValidEndpoint('http://metadata.google.internal/computeMetadata/v1/'));
        $this->assertFalse(QdrantClient::isValidEndpoint('file:///etc/passwd'));
        $this->assertFalse(QdrantClient::isValidEndpoint('ftp://malicious.host/data'));
        $this->assertFalse(QdrantClient::isValidEndpoint('gopher://internal.network/'));
    }

    public function testApiKeyMaskingSecurity(): void
    {
        $settings = new Settings();

        $secret = 'sk-proj-abc123xyz456secret999key';
        $masked = $settings->getMaskedKey($secret);

        $this->assertStringNotContainsString('abc123xyz456secret', $masked);
        $this->assertStringStartsWith('sk-', $masked);
        $this->assertStringEndsWith('9key', $masked);
        $this->assertStringContainsString('***', $masked);

        // Short or empty keys
        $this->assertSame('*****', $settings->getMaskedKey('short'));
        $this->assertSame('', $settings->getMaskedKey(''));
    }
}
