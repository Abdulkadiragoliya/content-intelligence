<?php

namespace abdulkadiragoliya\contentintelligence\models;

use craft\base\Model;

/**
 * Model representing an individual audit rule evaluation result.
 */
class AuditResult extends Model
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_NOTICE = 'notice';
    public const SEVERITY_GOOD = 'good';

    public string $ruleId = '';
    public string $category = 'content';
    public string $severity = self::SEVERITY_NOTICE;
    public string $title = '';
    public string $description = '';
    public string $recommendation = '';
    public array $context = [];

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['ruleId', 'category', 'severity', 'title'], 'required'],
            [['ruleId', 'category', 'severity', 'title', 'description', 'recommendation'], 'string'],
            ['severity', 'in', 'range' => [
                self::SEVERITY_CRITICAL,
                self::SEVERITY_WARNING,
                self::SEVERITY_NOTICE,
                self::SEVERITY_GOOD,
            ]],
            ['context', 'safe'],
        ];
    }

    /**
     * Helper to check if issue requires immediate attention.
     */
    public function isIssue(): bool
    {
        return in_array($this->severity, [self::SEVERITY_CRITICAL, self::SEVERITY_WARNING], true);
    }
}
