<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\services\ScoreService;
use abdulkadiragoliya\contentintelligence\models\AuditResult;

class ScoreServiceTest extends TestCase
{
    public function testPerfectScoreWithZeroPenalties(): void
    {
        $service = new ScoreService();
        $this->assertSame(100, $service->calculateScore(0, 0, 0));
    }

    public function testCriticalPenaltyDeduction(): void
    {
        $service = new ScoreService();
        // 1 critical = 25 pts penalty -> 75
        $this->assertSame(75, $service->calculateScore(1, 0, 0));
        // 2 critical = 50 pts penalty -> 50
        $this->assertSame(50, $service->calculateScore(2, 0, 0));
    }

    public function testWarningAndNoticePenalties(): void
    {
        $service = new ScoreService();
        // 1 warning (10) + 2 notices (6) = 16 pts penalty -> 84
        $this->assertSame(84, $service->calculateScore(0, 1, 2));
    }

    public function testFloorAtZero(): void
    {
        $service = new ScoreService();
        // 5 critical = 125 pts penalty -> capped at 0
        $this->assertSame(0, $service->calculateScore(5, 0, 0));
    }

    public function testCalculateFromResults(): void
    {
        $service = new ScoreService();

        $results = [
            new AuditResult([
                'category' => 'content',
                'severity' => AuditResult::SEVERITY_CRITICAL,
                'ruleId' => 'empty-body',
            ]),
            new AuditResult([
                'category' => 'seo',
                'severity' => AuditResult::SEVERITY_WARNING,
                'ruleId' => 'meta-title',
            ]),
            new AuditResult([
                'category' => 'content',
                'severity' => AuditResult::SEVERITY_GOOD,
                'ruleId' => 'heading-structure',
            ]),
        ];

        $scores = $service->calculateFromResults($results);

        $this->assertSame(75, $scores['contentScore']); // 1 critical in content
        $this->assertSame(90, $scores['seoScore']);     // 1 warning in SEO
        $this->assertSame(65, $scores['overallScore']); // 1 critical + 1 warning overall
        $this->assertSame(1, $scores['criticalCount']);
        $this->assertSame(1, $scores['warningCount']);
        $this->assertSame(1, $scores['goodCount']);
    }
}
