<?php

namespace abdulkadiragoliya\contentintelligence\events;

use yii\base\Event;
use abdulkadiragoliya\contentintelligence\services\audit\AuditRuleInterface;

/**
 * Event for registering custom audit rules.
 */
class RegisterAuditRulesEvent extends Event
{
    /**
     * @var AuditRuleInterface[]
     */
    public array $rules = [];
}
