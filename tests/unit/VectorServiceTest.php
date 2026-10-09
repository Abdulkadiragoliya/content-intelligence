<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\services\vector\QdrantClient;

class VectorServiceTest extends TestCase
{
    public function testHashToUuidDeterministicFormat(): void
    {
        $id1 = QdrantClient::hashToUuid('entry_42_chunk_0');
        $id2 = QdrantClient::hashToUuid('entry_42_chunk_0');
        $id3 = QdrantClient::hashToUuid('entry_42_chunk_1');

        // Deterministic: same input = same UUID
        $this->assertSame($id1, $id2);
        // Different input = different UUID
        $this->assertNotSame($id1, $id3);

        // Valid RFC 4122 UUID v4 regex
        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($uuidRegex, $id1);
    }

    public function testContentHashConsistency(): void
    {
        $text1 = "Content Intelligence provides deep SEO and RAG knowledge.";
        $text2 = "Content Intelligence provides deep SEO and RAG knowledge.";
        $text3 = "Modified text.";

        $hash1 = hash('sha256', $text1);
        $hash2 = hash('sha256', $text2);
        $hash3 = hash('sha256', $text3);

        $this->assertSame($hash1, $hash2);
        $this->assertNotSame($hash1, $hash3);
    }
}
