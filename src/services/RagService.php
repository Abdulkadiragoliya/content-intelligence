<?php

namespace abdulkadiragoliya\contentintelligence\services;

use Craft;
use craft\elements\Entry;
use yii\base\Component;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\records\AuditRecord;
use abdulkadiragoliya\contentintelligence\records\AuditResultRecord;

/**
 * Service orchestrating RAG (Retrieval-Augmented Generation) & Content Recommendations
 * for Content Intelligence Plus Edition.
 */
class RagService extends Component
{
    /**
     * Check if RAG pipeline is fully operational.
     */
    public function isAvailable(): bool
    {
        $plugin = Plugin::getInstance();
        return $plugin->hasPlus() && $plugin->ai->isConfigured();
    }

    /**
     * Query website knowledge base using Retrieval-Augmented Generation (RAG).
     *
     * @param string $question User question
     * @param int $siteId Craft site ID
     * @param array $options Additional options
     * @return array RAG answer, citations, and metadata
     */
    public function askWebsite(string $question, int $siteId, array $options = []): array
    {
        $this->requirePlusEdition();

        $cleanQuestion = trim($question);
        if (empty($cleanQuestion)) {
            return [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Please enter a valid question.'),
            ];
        }

        $plugin = Plugin::getInstance();
        $limit = max(3, min(8, (int)($options['limit'] ?? 5)));

        // Step 1: Semantic & Lexical Hybrid Retrieval
        $hits = $plugin->vector->hybridSearch($cleanQuestion, $siteId, $limit);

        if (empty($hits)) {
            return [
                'success' => true,
                'question' => $cleanQuestion,
                'answer' => Craft::t('content-intelligence', 'I could not find any relevant content in your website\'s knowledge base for this question. Make sure your entries are indexed under Semantic Search.'),
                'citations' => [],
                'confidence' => 'low',
                'chunksUsed' => 0,
            ];
        }

        // Build citations list
        $citations = [];
        $contextBlocks = [];
        $seenEntryChunk = [];

        foreach ($hits as $i => $hit) {
            $citeIndex = $i + 1;
            $citations[] = [
                'index' => $citeIndex,
                'entryId' => $hit['entryId'],
                'entryTitle' => $hit['entryTitle'],
                'entryUrl' => $hit['entryUrl'],
                'sectionName' => $hit['sectionName'],
                'heading' => $hit['heading'],
                'score' => $hit['score'],
                'snippet' => $hit['snippet'],
            ];

            $text = $hit['chunkText'] ?? strip_tags($hit['snippet']);
            $contextBlocks[] = "[Source {$citeIndex}] Entry: \"{$hit['entryTitle']}\" | Section: \"{$hit['heading']}\"\n{$text}";
        }

        // If OpenAI key is not configured, return grounded snippets gracefully
        if (!$plugin->ai->isConfigured()) {
            $fallbackAnswer = Craft::t('content-intelligence', "OpenAI API Key is not configured in Plugin Settings.\n\nHere are the top relevant sections found on your website:\n");
            foreach ($citations as $c) {
                $fallbackAnswer .= "\n* **[{$c['index']}] {$c['entryTitle']}** ({$c['heading']})\n  " . strip_tags($c['snippet']);
            }

            return [
                'success' => true,
                'question' => $cleanQuestion,
                'answer' => $fallbackAnswer,
                'citations' => $citations,
                'confidence' => 'medium',
                'chunksUsed' => count($hits),
                'notice' => 'Generated via local hybrid retrieval (OpenAI key not configured)',
            ];
        }

        // Step 2: Augment Context with Knowledge Chunks and Live Audit Metrics
        $auditStats = $plugin->audit->getAuditStats($siteId);
        $totalEntriesOnSite = $auditStats['totalEntries'] ?? 0;
        $auditedCount = $auditStats['analyzedCount'] ?? 0;

        $entryAudits = AuditRecord::find()
            ->where(['siteId' => $siteId])
            ->limit(50)
            ->all();

        $entryBreakdown = [];
        $missingTitles = [];
        $missingDescriptions = [];
        $missingAltTags = [];
        $lowScoreEntries = [];

        foreach ($entryAudits as $audit) {
            $entry = Entry::find()->id($audit->entryId)->siteId($siteId)->status(null)->one();
            $title = $entry ? $entry->title : "Entry #{$audit->entryId}";
            $findings = AuditResultRecord::find()
                ->where(['auditId' => $audit->id])
                ->all();

            $issues = [];
            foreach ($findings as $f) {
                if (in_array($f->severity, ['critical', 'warning'])) {
                    $issues[] = "{$f->title} ({$f->category})";
                    if (stripos($f->ruleId, 'title') !== false || stripos($f->title, 'title') !== false) {
                        $missingTitles[] = "\"{$title}\" (ID: {$audit->entryId})";
                    }
                    if (stripos($f->ruleId, 'description') !== false || stripos($f->title, 'description') !== false) {
                        $missingDescriptions[] = "\"{$title}\" (ID: {$audit->entryId})";
                    }
                    if (stripos($f->ruleId, 'alt') !== false || stripos($f->title, 'alt') !== false) {
                        $missingAltTags[] = "\"{$title}\" (ID: {$audit->entryId})";
                    }
                }
            }

            if ($audit->contentScore < 60 || $audit->seoScore < 60) {
                $lowScoreEntries[] = "\"{$title}\" (Content: {$audit->contentScore}/100, SEO: {$audit->seoScore}/100)";
            }

            $entryBreakdown[] = "- \"{$title}\" (ID: {$audit->entryId}, Content Score: {$audit->contentScore}/100, SEO Score: {$audit->seoScore}/100)" .
                (!empty($issues) ? ": Issues detected -> " . implode(', ', $issues) : ": Clean / No major issues");
        }

        $missingTitlesStr = !empty($missingTitles) ? implode(', ', array_unique($missingTitles)) : 'None (all audited entries have valid titles)';
        $missingDescriptionsStr = !empty($missingDescriptions) ? implode(', ', array_unique($missingDescriptions)) : 'None (all audited entries have meta descriptions)';
        $missingAltTagsStr = !empty($missingAltTags) ? implode(', ', array_unique($missingAltTags)) : 'None (all audited images have alt tags)';
        $lowScoreEntriesStr = !empty($lowScoreEntries) ? implode(', ', array_unique($lowScoreEntries)) : 'None';
        $entryDetailsStr = !empty($entryBreakdown) ? implode("\n", $entryBreakdown) : 'No detailed audit records available.';

        $siteAuditContext = "LIVE SITE AUDIT & INVENTORY METRICS (from Content Intelligence):\n" .
            "- Total Published Entries on Website: {$totalEntriesOnSite}\n" .
            "- Total Entries Audited by Content Intelligence: {$auditedCount} of {$totalEntriesOnSite}\n" .
            "- Average Content Score: {$auditStats['avgContentScore']}/100\n" .
            "- Average SEO Score: {$auditStats['avgSeoScore']}/100\n" .
            "- Critical Issues Detected Across Site: {$auditStats['criticalIssues']}\n" .
            "- Warning Notices: {$auditStats['warningIssues']}\n\n" .
            "SPECIFIC ISSUE BREAKDOWN BY ENTRY:\n" .
            "- Entries with Missing/Problematic Titles: {$missingTitlesStr}\n" .
            "- Entries with Missing/Problematic Meta Descriptions: {$missingDescriptionsStr}\n" .
            "- Entries with Missing Image Alt Tags: {$missingAltTagsStr}\n" .
            "- Low-Scoring Entries (under 60): {$lowScoreEntriesStr}\n\n" .
            "DETAILED LIST OF AUDITED ENTRIES & STATUS:\n" .
            "{$entryDetailsStr}\n";

        $contextPrompt = implode("\n\n---\n\n", $contextBlocks);

        $systemPrompt = "You are an intelligent Content Intelligence assistant for this Craft CMS website. " .
            "Your objective is to answer the user's question accurately, professionally, and concisely using the provided website context and live site audit metrics.\n\n" .
            "STRICT RULES:\n" .
            "1. Base your answer strictly on the provided context sources and site audit metrics. Do not make up facts or extrapolate beyond the text.\n" .
            "2. Cite your sources inline using [1], [2], etc., corresponding directly to the [Source X] references.\n" .
            "3. If the user asks about the site's SEO health, content score, or how to improve, cite the live Site Audit Metrics and provide actionable advice based on Content Intelligence guidelines.\n" .
            "4. If the context does not provide sufficient information for general questions, clearly state what information is available and what is missing.\n" .
            "5. Format your answer using clean Markdown paragraphs and bullet points.";

        $userPrompt = "{$siteAuditContext}\n\nWEBSITE CONTENT SOURCES:\n{$contextPrompt}\n\nQUESTION: {$cleanQuestion}\n\nANSWER:";

        try {
            $response = $plugin->ai->getProvider()->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], [
                'temperature' => 0.2,
                'max_tokens' => 800,
            ]);

            $answer = trim($response['content'] ?? '');
            $topScore = $hits[0]['score'] ?? 0;
            $confidence = $topScore >= 5.0 ? 'high' : ($topScore >= 2.0 ? 'medium' : 'low');

            return [
                'success' => true,
                'question' => $cleanQuestion,
                'answer' => $answer,
                'citations' => $citations,
                'confidence' => $confidence,
                'chunksUsed' => count($hits),
            ];
        } catch (\Throwable $e) {
            Craft::error("RAG completion failed: {$e->getMessage()}", __METHOD__);
            return [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'AI completion error: {error}', ['error' => $e->getMessage()]),
                'citations' => $citations,
            ];
        }
    }

    /**
     * Get semantically related content recommendations for an entry.
     *
     * @param int $entryId Craft entry ID
     * @param int $siteId Site ID
     * @param int $limit Number of recommendations
     * @return array Ranked related entries
     */
    public function getRecommendations(int $entryId, int $siteId, int $limit = 4): array
    {
        $this->requirePlusEdition();

        $entry = Entry::find()->id($entryId)->siteId($siteId)->status(null)->one();
        if (!$entry) {
            return [];
        }

        $plugin = Plugin::getInstance();
        $extracted = $plugin->ai->extractEntryContent($entry);
        $searchTerms = (string)$entry->title;

        if (!empty($extracted['body'])) {
            $words = preg_split('/\s+/u', strip_tags($extracted['body']), -1, PREG_SPLIT_NO_EMPTY);
            $sampleWords = array_slice($words, 0, 30);
            $searchTerms .= ' ' . implode(' ', $sampleWords);
        }

        $hits = $plugin->vector->hybridSearch($searchTerms, $siteId, $limit + 6);
        $recommendations = [];
        $seenEntryIds = [$entryId]; // exclude current entry

        foreach ($hits as $hit) {
            $candId = (int)$hit['entryId'];
            if (in_array($candId, $seenEntryIds, true)) {
                continue;
            }

            $seenEntryIds[] = $candId;
            $recommendations[] = [
                'entryId' => $candId,
                'title' => $hit['entryTitle'],
                'url' => $hit['entryUrl'],
                'section' => $hit['sectionName'],
                'score' => $hit['score'],
                'heading' => $hit['heading'],
                'snippet' => $hit['snippet'],
            ];

            if (count($recommendations) >= $limit) {
                break;
            }
        }

        return $recommendations;
    }

    /**
     * Enforce Plus edition requirement.
     */
    protected function requirePlusEdition(): void
    {
        if (!Plugin::getInstance()->hasPlus()) {
            throw new \RuntimeException('Ask Your Website RAG requires Content Intelligence Plus edition.');
        }
    }

    /**
     * Compatibility alias for requirePlusEdition.
     */
    protected function requireAgencyEdition(): void
    {
        $this->requirePlusEdition();
    }
}
