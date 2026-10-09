<?php

namespace abdulkadiragoliya\contentintelligence\web\twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Custom Twig extension for Content Intelligence.
 */
class ContentIntelligenceTwigExtension extends AbstractExtension
{
    /**
     * @inheritdoc
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('ciScoreBadge', [$this, 'renderScoreBadge'], ['is_safe' => ['html']]),
            new TwigFilter('ciStatusBadge', [$this, 'renderStatusBadge'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ciEdition', fn() => Plugin::getInstance()->getActiveEdition()),
            new TwigFunction('ciHasPro', fn() => Plugin::getInstance()->hasPro()),
            new TwigFunction('ciHasAgency', fn() => Plugin::getInstance()->hasAgency()),
        ];
    }

    /**
     * Render HTML score badge.
     */
    public function renderScoreBadge(?int $score): string
    {
        if ($score === null) {
            return '<span class="ci-score-badge ci-score-na" style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:0.8rem;background:#e2e8f0;color:#64748b;font-weight:600;">N/A</span>';
        }

        $bg = '#ef4444';
        $color = '#ffffff';
        if ($score >= 80) {
            $bg = '#10b981';
        } elseif ($score >= 50) {
            $bg = '#f59e0b';
        }

        return sprintf(
            '<span class="ci-score-badge" style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:0.8rem;background:%s;color:%s;font-weight:600;">%d/100</span>',
            $bg,
            $color,
            $score
        );
    }

    /**
     * Render status badge.
     */
    public function renderStatusBadge(?string $status): string
    {
        $statusClean = strtolower($status ?: 'unscored');
        $labels = [
            'good' => ['label' => 'Good', 'bg' => '#dcfce7', 'color' => '#166534'],
            'warning' => ['label' => 'Needs Review', 'bg' => '#fef3c7', 'color' => '#92400e'],
            'danger' => ['label' => 'Critical', 'bg' => '#fee2e2', 'color' => '#991b1b'],
        ];

        $config = $labels[$statusClean] ?? ['label' => 'Unscored', 'bg' => '#f1f5f9', 'color' => '#475569'];

        return sprintf(
            '<span class="ci-status-badge" style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.75rem;background:%s;color:%s;font-weight:600;text-transform:uppercase;">%s</span>',
            $config['bg'],
            $config['color'],
            htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8')
        );
    }
}
