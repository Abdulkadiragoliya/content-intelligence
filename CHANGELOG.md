# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.5] - 2026-10-11

### Fixed
- **Multi-site Settings Isolation**: Fixed per-site settings overrides so saving thresholds (e.g. minimum content word count, content stale threshold, SEO title/description lengths) for one site no longer mutates or leaks into other sites. Added clean static defaults via `Settings::getForSite()`.
- **Navigation Sidebar Auto-Expansion & Active Item Highlight**: Normalized Control Panel subnav URLs to clean paths, enabling native Craft CMS navigation matching (`item.sel = true`), automatic disclosure expansion (`<craft-disclosure state="expanded">`), and proper `.sel` active highlighting on all plugin pages while preserving active site context via session persistence.
- **Site Dropdown & URL Normalization**: Resolved duplicate `?site=` parameter collisions (`?site=default?site=gravesendSteel`) by building URLs with Craft's parameter dictionary (`cpUrl(path, { site: handle })`). Explicitly bound `selectedSiteId` in `_elements/sitemenu` so the active site title displays accurately with the checkmark.
- **Audit Rules Multi-site Scoping**: Updated `ThinContentRule`, `StaleContentRule`, `MetaTitleRule`, and `MetaDescriptionRule` to evaluate entries using their specific site's configured thresholds (`$settings->getForSite($prop, $entry->siteId)`).
- **Semantic Knowledge Base Batch Indexing**: Fixed "Index Entire Website" to target the active selected site rather than falling back to the primary site. Added direct in-request batch processing for sites with up to 50 entries so status updates immediately to 100% indexed without waiting on a detached queue worker.
- **Site Parameter Preservation Across All Views**: Updated inspect, re-index, and scan action links and forms across Dashboard, Content Audit, SEO Audit, AI Assistant, Semantic Search, and Ask Your Website (RAG) to seamlessly preserve the active site context.

## [1.0.4] - 2026-10-11

### Changed
- Reordered plugin editions to **Lite**, **Plus**, and **Pro** in standard Craft CMS 3-edition ascending hierarchy (`Lite` < `Plus` < `Pro`):
  - **Lite** (Free): Deterministic offline content rules, SEO health audits, live Google SERP preview, and 0–100 health scoring.
  - **Plus** ($99): Everything in Lite + Bring-Your-Own-Key OpenAI assistant, Native Craft 5 Entry Slideout, 1-click meta generator, side-by-side visual diff, Schema FAQ generator, alt text, and executive summaries.
  - **Pro** ($249): Everything in Plus + Qdrant Vector DB, heading-aware chunking, hash-based incremental indexing, "Ask Your Website" RAG assistant, hybrid search (Vector + Lexical RRF), and multi-site CSV audit exports.
- Updated `Plugin::editions()` order to `['lite', 'plus', 'pro']`.
- Updated feature access gates: `hasPlus()` unlocks AI Content Assistant features across both Plus and Pro tiers; `hasPro()` gates Qdrant Vector DB, semantic indexing, and conversational RAG features.
- Maintained backwards-compatibility aliases (`hasAi()`, `hasVector()`, `hasRag()`, `hasAgency()`).
- Updated Control Panel subnav, entry sidebar badge, upgrade banners, dashboard tables, and settings labels.
- Synchronized all documentation, configuration comments, and unit tests (25/25 passing).

## [1.0.3] - 2026-10-10

### Fixed
- Fixed `Undefined variable $site` in `AuditController::actionAuditEntry()` when re-auditing single entries.
- Enhanced multi-site query handling across audit, SEO, and semantic controllers to seamlessly resolve `?site=<handle>` query parameters.

## [1.0.2] - 2026-10-10

### Changed
- Standardized commercial edition names to **Lite**, **Pro**, and **Plus** in alignment with official Craft CMS Plugin Store guidelines.
- Added `EDITION_PLUS` and `hasPlus()` methods with full backwards-compatibility aliases for `EDITION_AGENCY` and `hasAgency()`.
- Updated Control Panel templates, navigation labels, and upgrade banners to reference the Plus edition.
- Hardened edition security: `.env` edition overrides are strictly confined to local development environments (`localhost`, `local.*`, `*.test`, `devMode`), preventing production license bypass. Production environments strictly enforce official Craft Plugin Store licenses.

## [1.0.1] - 2026-10-09

### Fixed
- Enforce Craft Plugin Store commercial licensing on production domains.
- Restrict `CONTENT_INTELLIGENCE_EDITION` environment variable override to `devMode` and local development.
- Updated documentation URLs and README guides.

## [1.0.0] - 2026-10-08

### Added
- **Core Architecture & Foundation**:
  - Three-tier edition system: Lite (deterministic free), Pro (AI assistant), Agency (semantic knowledge base & RAG).
  - Environment variable resolution (`App::parseEnv()`) for OpenAI keys and Qdrant clusters.
  - Multi-site scoping and Craft Cloud ephemeral filesystem compatibility.
  - Granular Craft user permissions for dashboard, audits, AI assistant, semantic index, RAG, and settings.
- **Lite Content & SEO Audits**:
  - Deterministic content rules: thin content, missing titles, empty bodies, broken heading hierarchies, and stale content.
  - Deterministic SEO rules: title/description length limits, missing image alt text, internal linking health, and single-H1 validation.
  - Interactive SERP preview card with desktop and mobile views and live character counters.
  - Background asynchronous queue execution (`RunContentAuditJob`) and instant single-entry re-audits.
- **Pro AI Content Assistant**:
  - Bring-Your-Own-Key OpenAI integration with latency testing and connection diagnostics.
  - Native `Craft.Slideout` entry sidebar integration for instant access during drafting.
  - AI Meta Title & Description generation with character count limits.
  - Content improvement tool with green/red visual diff comparison and one-click copy.
  - Automatic FAQ extraction and Schema-ready question/answer generation.
  - Executive summary and accessible image alt text generators.
  - Deep AI SEO Intelligence: search intent analysis, content depth gap identification, and semantic entity discovery.
- **Agency Semantic Knowledge Base & RAG**:
  - Heading-aware intelligent content chunking with word count sizing and sliding window overlap.
  - Incremental SHA-256 hash indexing: skips unchanged content with zero token cost.
  - REST client for Qdrant Vector Database with automatic Cosine collection provisioning and SSRF metadata endpoint protection.
  - Hybrid Search combining vector cosine similarity and MySQL fulltext with Reciprocal Rank Fusion (RRF).
  - Interactive Hybrid Search Tester playground in the Control Panel.
  - "Ask Your Website" conversational RAG workspace with inline citations (`[1]`, `[2]`), confidence scoring, live audit metrics grounding, and local fallback mode.
  - Content recommendation engine for related entry suggestions.
  - Multi-site Agency CSV Audit Report exporter with UTF-8 BOM Excel compatibility.
- **Developer API & CLI Automation**:
  - Public Twig variable `craft.contentIntelligence` with `getEntryScore()`, `getRelatedEntries()`, `search()`, and `getSiteStats()`.
  - Twig filters `|ciScoreBadge` and `|ciStatusBadge`.
  - Console commands: `content-intelligence/audit/all`, `content-intelligence/semantic/index-all`, `content-intelligence/semantic/prune`, and `content-intelligence/health/check`.
  - Self-contained automated test suite covering unit scoring, rules, edition gates, security, and RAG formatting (`tests/run.php`).
