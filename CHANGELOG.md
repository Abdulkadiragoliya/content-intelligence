# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
