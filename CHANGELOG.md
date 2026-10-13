# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-13

### Added
- **Standalone SEO Metadata Management**: Full, production-ready SEO metadata management without requiring SEOmatic, Retour, or any third-party SEO plugin.
- **Dedicated Persistent Storage**: Added `{{%contentintelligence_seo_metadata}}` table (`m261013_000000_create_seo_metadata_table` and `Install.php`) to store approved SEO titles and meta descriptions with foreign keys cascading on element and site deletion.
- **Craft Cloud & Multisite Support**: Fully agnostic database schema (PostgreSQL & MySQL) adhering to Craft Cloud ephemeral container guidelines. Strict site isolation ensures metadata on one site never leaks to another site.
- **Independent Content Architecture**: Clearly decouples the visible Craft entry title (H1) and body fields from the SEO `<title>` and `<meta name="description">`. AI generation or manual edits never overwrite the entry title or page content.
- **Entry Editor Slideout Integration**: Redesigned SEO Meta tab featuring live character counters (Title: 30–60 chars, Description: 120–160 chars), advisory status indicators, current saved values indicator, and non-destructive "Apply" / "Save Approved SEO Metadata" actions.
- **Entry Editor Sidebar Badge**: Quick status indicators showing whether an SEO title and description are set for the active site, plus a "Manage SEO Metadata" button available across all editions (Lite, Pro, Plus).
- **Targeted AI Generation**: One-click actions to generate both, title only, or description only with full preview and approval workflow before database persistence.
- **Frontend Twig Output**:
  - `{{ craft.contentIntelligence.renderSeoMeta(entry) }}` and `{{ ciSeoMeta(entry) }}`: Emits valid, HTML-escaped `<title>` and `<meta name="description">` tags.
  - `{{ craft.contentIntelligence.getSeo(entry) }}`: Returns structured metadata array for custom or headless templates.
  - Request-level deduplication cache to prevent duplicate tags when called multiple times in templates.
  - Graceful fallback handling: falls back to entry title when no custom SEO title is defined; omits empty tags.
  - Zero external API dependencies during frontend requests (0 OpenAI or Qdrant calls).
- **Comprehensive Automated Test Suite**: Expanded automated test suite to 45 passing tests across storage, frontend HTML output, non-destructive AI generation, multisite isolation, Craft Cloud detection, and edition enforcement.

### Fixed
- **Twig Auto-escaping for HTML Output**: Wrapped `renderSeoMeta()` and `ciSeoMeta()` in `\craft\helpers\Template::raw()` (`\Twig\Markup`) so `<title>` and `<meta name="description">` tags render as live, raw HTML without requiring manual `|raw` filters.
- **Slideout Modal Scrolling**: Added `overflow-y: auto !important;` to `.ci-slideout-container` and bounded viewport height (`max-height: calc(100vh - 48px);`) with bottom padding to ensure the AI generation section and all suggestion items can be scrolled into view smoothly.
- **Slideout DOM Collision & Pre-selected Tabs**: Ensured existing slideouts in the DOM are destroyed before opening a new slideout, scoped all jQuery lookups to the active wrapper instance, and added server-side active tab evaluation (`initialTab`) to prevent blank tabs.
- **Defensive Database Queries**: Wrapped `getMetadata()` in a try/catch block with fallback to `null` to prevent CP 500 errors if tables or schema migrations are in transition.

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
