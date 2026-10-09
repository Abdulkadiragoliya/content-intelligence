<?php

namespace abdulkadiragoliya\contentintelligence\services\ai;

/**
 * Centralized, maintainable AI prompt definitions.
 * Separated cleanly from controllers, services, and business logic.
 */
class PromptTemplates
{
    /**
     * Standard system prompt enforcing factual integrity and safety.
     */
    public static function baseSystemPrompt(): string
    {
        return <<<PROMPT
You are Content Intelligence, an expert AI content architect and SEO specialist for Craft CMS.
Your instructions:
1. Ground all recommendations strictly in the provided content. Never hallucinate facts, metrics, or claims.
2. Clearly separate objective analysis from creative suggestions.
3. Never make unsubstantiated claims such as guaranteeing top search rankings.
4. Maintain a professional, constructive, and actionable tone.
PROMPT;
    }

    /**
     * System & User prompt for comprehensive content quality & intent analysis.
     */
    public static function contentAnalysis(string $title, string $content): array
    {
        $system = self::baseSystemPrompt() . "\nAnalyze the content for substance, clarity, search intent, and structural completeness.";
        $user = <<<USER
Entry Title: {$title}

Content:
{$content}

Analyze this entry and return a JSON object with this exact structure:
{
    "summary": "2-3 sentence overview of the content topic",
    "detectedIntent": "informational" | "transactional" | "navigational" | "commercial",
    "readingLevel": "beginner" | "intermediate" | "advanced",
    "strengths": ["point 1", "point 2"],
    "weaknesses": ["point 1", "point 2"],
    "actionableRecommendations": ["recommendation 1", "recommendation 2"]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * System & User prompt for rewriting and improving content with diff preservation.
     */
    public static function improveContent(string $title, string $content, string $instructions = '', string $tone = 'professional'): array
    {
        $system = self::baseSystemPrompt() . "\nRewrite and polish the provided content according to best practices while preserving all factual information.";
        $toneDescriptions = [
            'professional' => 'Professional, polished, and authoritative',
            'engaging' => 'Conversational, active voice, and reader-focused',
            'concise' => 'Ultra-concise, streamlined, removing filler and passive voice',
            'persuasive' => 'Persuasive, marketing-oriented with clear value propositions',
            'technical' => 'Technical, precise, structured, and informative',
        ];
        $selectedTone = $toneDescriptions[$tone] ?? $toneDescriptions['professional'];
        $instructionSnippet = !empty($instructions) ? "Specific user instructions: {$instructions}" : "Improve overall flow, clarity, and readability.";

        $user = <<<USER
Entry Title: {$title}
Target Tone: {$selectedTone}
{$instructionSnippet}

Original Content:
{$content}

Return a JSON object with this structure:
{
    "improvedContent": "The rewritten, refined content text",
    "improvementsMade": ["Summary of change 1", "Summary of change 2"],
    "wordCountChange": "comparison note"
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Prompts for generating SEO Meta Title suggestions.
     */
    public static function metaTitleSuggestions(string $title, string $content): array
    {
        $system = self::baseSystemPrompt() . "\nGenerate high-CTR, search-optimized meta title suggestions between 30 and 60 characters.";
        $user = <<<USER
Current Title: {$title}

Content Excerpt:
{$content}

Provide 3 diverse meta title suggestions (under 60 characters each). Return a JSON object:
{
    "suggestions": [
        {"title": "Title 1", "length": 48, "rationale": "focus on primary benefit"},
        {"title": "Title 2", "length": 52, "rationale": "focus on keyword clarity"},
        {"title": "Title 3", "length": 58, "rationale": "question or action-oriented"}
    ]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Prompts for generating SEO Meta Description suggestions.
     */
    public static function metaDescriptionSuggestions(string $title, string $content): array
    {
        $system = self::baseSystemPrompt() . "\nGenerate compelling meta description suggestions strictly between 120 and 155 characters.";
        $user = <<<USER
Page Title: {$title}

Content Excerpt:
{$content}

Provide 3 meta description options including a clear call-to-action. Return JSON:
{
    "suggestions": [
        {"description": "Description 1", "length": 145, "focus": "engaging summary with call to action"},
        {"description": "Description 2", "length": 150, "focus": "problem/solution framing"},
        {"description": "Description 3", "length": 140, "focus": "concise benefit-driven"}
    ]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Prompts for generating descriptive, accessible image alt-text.
     */
    public static function imageAltText(string $context, string $filename = ''): array
    {
        $system = self::baseSystemPrompt() . "\nGenerate concise, accurate alt-text (under 125 characters) that describes the image context for accessibility and SEO.";
        $user = <<<USER
Surrounding Content Context:
{$context}

Image Filename: {$filename}

Return JSON:
{
    "altText": "Descriptive, concise alt text without saying 'image of' or 'picture of'",
    "explanation": "Why this alt text fits the context"
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Prompts for generating relevant FAQs from article content.
     */
    public static function faqGeneration(string $title, string $content): array
    {
        $system = self::baseSystemPrompt() . "\nExtract common questions that readers would have based exclusively on the facts provided in the text.";
        $user = <<<USER
Article Title: {$title}

Content:
{$content}

Generate 3-5 relevant FAQ question-and-answer pairs based on this content. Return JSON:
{
    "faqs": [
        {"question": "Question text?", "answer": "Direct, factual answer grounded in the content."},
        {"question": "Question text?", "answer": "Direct, factual answer grounded in the content."}
    ]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Prompts for internal link recommendations.
     */
    public static function internalLinkSuggestions(string $title, string $content, array $availablePages): array
    {
        $system = self::baseSystemPrompt() . "\nAnalyze the content and identify logical opportunities to link to other pages in the website.";
        $pagesList = implode("\n", array_map(fn($p) => "- {$p['title']} ({$p['url']})", $availablePages));

        $user = <<<USER
Current Page: {$title}

Content:
{$content}

Available Website Pages:
{$pagesList}

Suggest up to 4 internal links where phrases in the current content naturally connect with available pages. Return JSON:
{
    "suggestions": [
        {
            "targetPageTitle": "Page Title",
            "targetUrl": "/path",
            "suggestedAnchorText": "Exact text snippet to turn into a link",
            "reason": "Why this link aids the user"
        }
    ]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Prompts for generating summaries, key takeaways, and teasers.
     */
    public static function summaryGeneration(string $title, string $content): array
    {
        $system = self::baseSystemPrompt() . "\nGenerate executive summaries, key takeaways, and social excerpts based on the provided content.";
        $user = <<<USER
Article Title: {$title}

Content:
{$content}

Generate structured summaries for different contexts. Return a JSON object with this exact structure:
{
    "tldr": "One sentence summary (maximum 30 words)",
    "executiveSummary": "1-2 paragraphs summarizing main points and conclusions",
    "keyTakeaways": ["Key takeaway 1", "Key takeaway 2", "Key takeaway 3"],
    "socialSnippet": "Engaging 1-2 sentence teaser suitable for LinkedIn or Twitter"
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Comprehensive AI SEO Intelligence Audit prompt.
     * Evaluates Search Intent, Topic Depth & Gaps, Semantic Keywords, and Competitor Differentiation.
     */
    public static function aiSeoAudit(string $title, string $content, string $targetKeyword = ''): array
    {
        $system = self::baseSystemPrompt() . "\nYou are an elite Search Quality Evaluator and Semantic SEO Strategist. Analyze the provided content objectively for search intent alignment, topical completeness, semantic entities, and content differentiation.";
        $keywordLine = !empty($targetKeyword) ? "Target Focus Keyword: {$targetKeyword}\n" : "";

        $user = <<<USER
Page Title: {$title}
{$keywordLine}
Page Content:
{$content}

Perform a rigorous SEO intelligence evaluation and return a JSON object with this exact structure:
{
    "searchIntent": {
        "primaryIntent": "informational" | "commercial" | "transactional" | "navigational",
        "intentMatchRating": "Strong" | "Moderate" | "Weak",
        "explanation": "Clear explanation of how well the page fulfills the searcher's goal"
    },
    "topicCoverage": {
        "coverageScore": 85,
        "primaryTopic": "Primary entity or subject of the page",
        "coveredSubtopics": ["Covered aspect 1", "Covered aspect 2"],
        "missingSubtopics": ["Missing aspect that searchers expect 1", "Missing aspect 2", "Missing aspect 3"],
        "suggestedHeadings": ["Suggested H2/H3 1", "Suggested H2/H3 2"]
    },
    "semanticKeywords": [
        {"term": "Semantic Entity 1", "context": "How or where to include naturally"},
        {"term": "Semantic Entity 2", "context": "How or where to include naturally"},
        {"term": "Semantic Entity 3", "context": "How or where to include naturally"}
    ],
    "differentiation": {
        "uniquenessScore": 75,
        "competitiveAngle": "Specific recommendation to make this content stand out from generic search competitors (e.g. original insights, practical examples, actionable checklists)"
    },
    "prioritizedActions": [
        "Highest priority actionable SEO recommendation 1",
        "Actionable SEO recommendation 2",
        "Actionable SEO recommendation 3"
    ]
}
USER;

        return ['system' => $system, 'user' => $user];
    }
}


