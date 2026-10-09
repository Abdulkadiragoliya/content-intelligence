# Content Intelligence for Craft CMS 5

> **AI-powered Content & SEO Intelligence for Craft CMS**

Content Intelligence is a production-grade, commercial Craft CMS 5 plugin designed to help site owners, marketers, developers, and digital agencies audit, score, optimize, semantically index, and converse with their website content.

---

## 🌟 Key Capabilities by Edition

### 🟢 Lite Edition (Free)
* **Deterministic Rules Engine**: 100% offline, zero API keys required, lightning-fast.
* **Content Audit**: Detects thin content (<300 words), missing titles, empty bodies, broken heading structures (missing H1, skipped levels), and stale content (>180 days).
* **SEO Audit Engine**: Evaluates meta title and description lengths (Google pixel limits), missing image alt text, internal linking health, and single-H1 compliance.
* **Interactive SERP Preview**: Live desktop and mobile Google search snippet preview cards with character counters and truncation warnings.
* **Automated Scoring (0–100)**: Transparent weighting and health badges (Good, Needs Review, Critical).

### 🔵 Pro Edition ($79/yr)
* *Everything in Lite, plus:*
* **AI Content Assistant (BYOK OpenAI)**: Bring Your Own Key architecture (supports `$OPENAI_API_KEY` in `.env`).
* **Entry Editor Slideout**: Integrated seamlessly with Craft 5's native `Craft.Slideout` UI directly inside the entry sidebar.
* **Smart Meta Generator**: One-click generation of click-worthy, SEO-optimized title tags and meta descriptions.
* **Content Improvement with Side-by-Side Diff**: AI-assisted rewrites, tone optimization, clarity improvements, and HTML-preserving additions with green/red visual diff comparison.
* **FAQ Generation**: Extracts common questions and generates Schema-ready FAQ pairs from entry text.
* **Executive Summaries & Alt Text**: Generates concise abstracts and descriptive, accessible image alt attributes.
* **Deep AI SEO Intelligence**: Single-pass semantic audit identifying search intent (informational, commercial, navigational, transactional), content depth gaps, semantic entities, and competitive differentiation opportunities.
* **Strict Human-in-the-Loop**: All AI suggestions require explicit human approval and staging before being applied to Craft entries.

### 🟣 Agency Edition ($149/yr)
* *Everything in Pro, plus:*
* **Intelligent Heading-Aware Chunking**: Hierarchically partitions entry content into coherent ~400-word sections under document heading contexts.
* **Incremental Hash-Based Indexing**: SHA-256 fingerprinting skips unchanged chunks during bulk scans (zero API cost and instant re-indexes).
* **Qdrant Vector DB Integration**: REST client connecting to self-hosted Qdrant instances (`http://localhost:6333`) or Qdrant Cloud clusters with automatic Cosine distance collection provisioning.
* **Hybrid Search (Vector + Lexical RRF)**: Reciprocal Rank Fusion combining high-dimensional OpenAI embeddings (`text-embedding-3-small`) with MySQL fulltext keyword matching.
* **"Ask Your Website" RAG Assistant**: Conversational knowledge assistant grounded strictly in your published entries with inline citations (`[1]`, `[2]`), confidence scoring, and zero-hallucination guardrails.
* **Semantic Content Recommendations**: Suggests related entries based on vector topic similarity for related post widgets.

---

## 📋 Requirements

* Craft CMS `^5.0.0`
* PHP `^8.2.0`
* MySQL 8.0+ or PostgreSQL 13+
* Optional (Pro/Agency): OpenAI API key (`gpt-4o-mini`, `gpt-4o`, `text-embedding-3-small`)
* Optional (Agency): Qdrant cluster (Local Docker or Qdrant Cloud)

---

## 🚀 Installation

```bash
composer require abdulkadiragoliya/content-intelligence
php craft plugin/install content-intelligence
```

---

## ⚙️ Configuration (`.env`)

Add any of the following optional environment variables to your `.env` file:

```env
# Plugin Edition: lite | pro | agency
CONTENT_INTELLIGENCE_EDITION=agency

# OpenAI API Key (Pro & Agency)
OPENAI_API_KEY=sk-...

# Qdrant Vector DB (Agency)
QDRANT_URL=http://localhost:6333
QDRANT_API_KEY=
QDRANT_COLLECTION=content_intelligence
```

---

## 💻 CLI & Scheduled Console Commands

Content Intelligence provides first-class CLI commands for automation, cron jobs, and CI/CD pipelines:

### System Diagnostics
```bash
php craft content-intelligence/health/check
```
*Outputs active edition, OpenAI connectivity latency, Qdrant cluster reachability, knowledge base chunk coverage, and audit scores.*

### Run Content & SEO Audits
```bash
# Audit all entries across the primary site
php craft content-intelligence/audit/all

# Audit entries for a specific site or section
php craft content-intelligence/audit/all --site=default --section=articles
```

### Semantic Knowledge Base Indexing
```bash
# Re-chunk and index all entries (incremental hashing skips unchanged content)
php craft content-intelligence/semantic/index-all

# Prune orphaned embeddings from deleted entries
php craft content-intelligence/semantic/prune
```

---

## 🎨 Twig Template API

Access Content Intelligence metrics and recommendations directly in your frontend Twig templates:

### Entry Scores
```twig
{% set score = craft.contentIntelligence.getEntryScore(entry.id) %}
{% if score %}
    <p>Content Health: {{ score.overall }}/100 {{ score.overall|ciScoreBadge }}</p>
    <p>Status: {{ score.status|ciStatusBadge }}</p>
{% endif %}
```

### Semantic Related Entries (Agency)
```twig
{# Recommend 3 semantically related entries based on vector embeddings #}
{% set related = craft.contentIntelligence.getRelatedEntries(entry.id, 3) %}

<div class="related-posts">
    <h3>Recommended Reading</h3>
    <ul>
        {% for item in related %}
            <li><a href="{{ item.url }}">{{ item.title }}</a></li>
        {% endfor %}
    </ul>
</div>
```

### Hybrid Frontend Search (Agency)
```twig
{% set results = craft.contentIntelligence.search(craft.app.request.getQueryParam('q'), 5) %}

{% for result in results %}
    <div class="search-result">
        <h4><a href="{{ result.entryUrl }}">{{ result.entryTitle }}</a></h4>
        <p>{{ result.snippet|raw }}</p>
        <small>Match Score: {{ result.score }} ({{ result.source }})</small>
    </div>
{% endfor %}
```

---

### Export Audit Reports (Agency)
```bash
# Export CSV directly from the Control Panel or visit:
# admin/content-intelligence/audit/export
```

---

## 🧪 Automated Testing Suite

Content Intelligence includes a self-contained automated test suite covering unit rules, scoring formulas, edition gating, security/SSRF defenses, and RAG formatting:

```bash
php plugins/content-intelligence/tests/run.php
```

Or via PHPUnit:
```bash
vendor/bin/phpunit --configuration plugins/content-intelligence/phpunit.xml.dist
```

---

## ☁️ Craft Cloud & Ephemeral Architecture

Content Intelligence is architected from the ground up for **Craft Cloud** and containerized production environments:
* **Stateless Execution**: Never writes to the local filesystem; all persistent models live in MySQL/PostgreSQL and remote vector clusters.
* **Environment Configuration**: Secrets and endpoints (`OPENAI_API_KEY`, `QDRANT_URL`, etc.) are resolved natively via `craft\helpers\App::parseEnv()`.
* **Asynchronous Queue Jobs**: Background batch scans run via Craft's native worker queue (`RunContentAuditJob`).
* **Asset Bundling**: Clean `AssetBundle` integration (`CpAsset::class`) compatible with Cloud CDN asset publishing.

---

## 🔒 Privacy & Architecture Guiding Principles

1. **Strict Bring-Your-Own-Key (BYOK)**: No third-party SaaS middleman. Your data is sent directly from your server to your OpenAI and Qdrant instances.
2. **Strict Human-in-the-Loop**: The plugin never silently mutates or publishes entry revisions without human approval.
3. **Unpublished Content Protection**: Public front-end semantic queries strictly enforce `live` entry status by default.
4. **Craft Cloud Compatible**: Zero persistent local disk dependencies.
5. **Multi-Site Scoped**: Every audit record, knowledge chunk, and RAG session is isolated by `siteId`.

---

## 🏷️ Plugin Store Metadata

* **Name**: Content Intelligence
* **Handle**: `content-intelligence`
* **Developer**: Abdulkadir Agoliya
* **Categories**: Content Management, SEO, AI & Machine Learning
* **Editions & Pricing**:
  * **Lite**: Free (Deterministic Content & SEO Audits, Scoring)
  * **Pro**: $79 / year (BYOK AI Assistant, Native Slideout, SERP & SEO Intelligence)
  * **Agency**: $149 / year (Qdrant Vector DB, Hybrid Search, "Ask Your Website" RAG, Agency CSV Export)

---

## 📄 License

Commercial Craft CMS Plugin License. See [LICENSE.md](LICENSE.md).
