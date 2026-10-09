<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;

class RagServiceTest extends TestCase
{
    public function testCitationFormattingRegex(): void
    {
        $text = "Our company provides craft CMS development [1] and custom integrations [2].";
        $matches = [];
        preg_match_all('/\[(\d+)\]/', $text, $matches);

        $this->assertSame(['[1]', '[2]'], $matches[0]);
        $this->assertSame(['1', '2'], $matches[1]);
    }

    public function testConfidenceCalculation(): void
    {
        $calculateConfidence = function(float $topScore): string {
            return $topScore >= 5.0 ? 'high' : ($topScore >= 2.0 ? 'medium' : 'low');
        };

        $this->assertSame('high', $calculateConfidence(6.5));
        $this->assertSame('medium', $calculateConfidence(3.2));
        $this->assertSame('low', $calculateConfidence(1.1));
    }
}
