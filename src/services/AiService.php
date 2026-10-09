<?php

namespace abdulkadiragoliya\contentintelligence\services;

use Craft;
use yii\base\Component;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\services\ai\AiProviderInterface;
use abdulkadiragoliya\contentintelligence\services\ai\OpenAiProvider;
use abdulkadiragoliya\contentintelligence\services\ai\PromptTemplates;

/**
 * Service orchestrating AI operations, provider abstraction, and health checks for Pro & Agency.
 */
class AiService extends Component
{
    /**
     * @var AiProviderInterface|null
     */
    private ?AiProviderInterface $_provider = null;

    /**
     * Returns the active configured AI provider (OpenAI by default).
     */
    public function getProvider(): AiProviderInterface
    {
        if ($this->_provider !== null) {
            return $this->_provider;
        }

        // Future AI providers (Anthropic, Google, Local) can be instantiated here
        $this->_provider = new OpenAiProvider();
        return $this->_provider;
    }

    /**
     * Check if AI provider has a valid key configured.
     */
    public function isConfigured(): bool
    {
        return $this->getProvider()->isConfigured();
    }

    /**
     * Test connection to the AI provider.
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);
        try {
            $result = $this->getProvider()->testConnection();
            Craft::info("AI Health Check completed with status: " . ($result['success'] ? 'OK' : 'FAIL'), __METHOD__);
            return $result;
        } catch (\Throwable $e) {
            Craft::error("AI Health Check exception: {$e->getMessage()}", __METHOD__);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latencyMs' => (int)round((microtime(true) - $startTime) * 1000),
                'model' => '',
            ];
        }
    }

    /**
     * Get provider status for dashboard health check.
     */
    public function getHealthStatus(): array
    {
        $provider = $this->getProvider();
        $configured = $provider->isConfigured();
        $settings = Plugin::getInstance()->getSettings();

        return [
            'configured' => $configured,
            'provider' => $provider->getName(),
            'model' => $settings->openaiModel ?: 'gpt-4o-mini',
            'status' => $configured ? 'Configured (BYOK)' : 'API Key Not Set',
            'maskedKey' => $settings->getMaskedKey($settings->openaiApiKey),
        ];
    }

    /**
     * Analyze content quality, intent, and readability.
     */
    public function analyzeContent(string $title, string $text): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::contentAnalysis($title, $text);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Rewrite and improve content.
     */
    public function improveContent(string $title, string $text, string $instructions = '', string $tone = 'professional'): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::improveContent($title, $text, $instructions, $tone);
        $result = $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
        
        // Augment with computed diff analysis
        if (!empty($result['improvedContent'])) {
            $diffData = $this->computeDiff($text, $result['improvedContent']);
            $result['diff'] = $diffData;
        }
        
        return $result;
    }

    /**
     * Generate meta title suggestions.
     */
    public function generateMetaTitles(string $title, string $text): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::metaTitleSuggestions($title, $text);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Generate meta description suggestions.
     */
    public function generateMetaDescriptions(string $title, string $text): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::metaDescriptionSuggestions($title, $text);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Generate contextual image alt text.
     */
    public function generateAltText(string $context, string $filename = ''): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::imageAltText($context, $filename);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Generate FAQ pairs from content.
     */
    public function generateFaqs(string $title, string $text): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::faqGeneration($title, $text);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Generate executive summary and social snippets.
     */
    public function generateSummary(string $title, string $text): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::summaryGeneration($title, $text);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Suggest internal links.
     */
    public function suggestInternalLinks(string $title, string $text, array $availablePages): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::internalLinkSuggestions($title, $text, $availablePages);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Comprehensive AI SEO Intelligence Audit.
     * Evaluates Search Intent, Topic Coverage & Gaps, Semantic Keywords, and Competitor Differentiation.
     */
    public function runAiSeoAudit(string $title, string $text, string $targetKeyword = ''): array
    {
        $this->requireProEdition();
        $prompts = PromptTemplates::aiSeoAudit($title, $text, $targetKeyword);
        return $this->getProvider()->jsonCompletion($prompts['system'], $prompts['user']);
    }

    /**
     * Extract normalized content and images from a Craft Entry.
     */
    public function extractEntryContent(\craft\elements\Entry $entry): array
    {
        $title = (string)$entry->title;
        $bodyParts = [];
        $images = [];

        foreach ($entry->getFieldValues() as $handle => $value) {
            if (is_string($value) && !empty($value)) {
                $bodyParts[] = strip_tags($value);
            } elseif ($value instanceof \craft\elements\db\ElementQueryInterface) {
                try {
                    $elements = $value->all();
                    foreach ($elements as $el) {
                        if ($el instanceof \craft\elements\Asset) {
                            $images[] = [
                                'id' => $el->id,
                                'title' => $el->title,
                                'filename' => $el->filename,
                                'alt' => $el->alt ?? '',
                                'url' => $el->getUrl(),
                            ];
                        } elseif ($el instanceof \craft\elements\Entry) {
                            // Matrix block entry in Craft 5
                            foreach ($el->getFieldValues() as $subVal) {
                                if (is_string($subVal) && !empty($subVal)) {
                                    $bodyParts[] = strip_tags($subVal);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore query resolution failures
                }
            }
        }

        $rawBody = implode("\n\n", $bodyParts);
        $cleanBody = trim(preg_replace('/\s+/', ' ', $rawBody));
        if (empty($cleanBody)) {
            $cleanBody = $title;
        }

        return [
            'id' => $entry->id,
            'title' => $title,
            'body' => $cleanBody,
            'rawText' => $cleanBody,
            'wordCount' => str_word_count($cleanBody),
            'images' => $images,
            'url' => $entry->getUrl() ?? '',
            'slug' => $entry->slug ?? '',
        ];
    }

    /**
     * Computes a structured word diff between old and new text.
     */
    public function computeDiff(string $oldText, string $newText): array
    {
        $oldWords = preg_split('/(\s+)/u', trim($oldText), -1, PREG_SPLIT_DELIM_CAPTURE);
        $newWords = preg_split('/(\s+)/u', trim($newText), -1, PREG_SPLIT_DELIM_CAPTURE);

        $oldWordCount = str_word_count($oldText);
        $newWordCount = str_word_count($newText);

        // Simple sentence / line chunking for readable side-by-side presentation
        return [
            'oldWordCount' => $oldWordCount,
            'newWordCount' => $newWordCount,
            'wordDifference' => $newWordCount - $oldWordCount,
            'percentChange' => $oldWordCount > 0 ? round((($newWordCount - $oldWordCount) / $oldWordCount) * 100, 1) : 0,
            'original' => $oldText,
            'improved' => $newText,
        ];
    }

    /**
     * Ensures active edition is Pro or Agency before executing AI features.
     */
    protected function requireProEdition(): void
    {
        if (!Plugin::getInstance()->hasPro()) {
            throw new \RuntimeException('AI capabilities require Content Intelligence Pro or Agency edition.');
        }
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OpenAI API key is not configured. Please set your API key in Settings.');
        }
    }
}
