# Qiangvei_SeoAi — Magento 2.4.9 SEO + AEO + GEO Module

A single, self-contained Magento Open Source / Adobe Commerce 2.4.x module that delivers **complete Google SEO**, **Answer Engine Optimization (AEO)**, and **Generative Engine Optimization (GEO)** for the modern AI search landscape.

It is designed to **replace** the incomplete default SEO output of the Luma/Blank themes entirely — when enabled, this module takes over titles, meta descriptions, canonical URLs, and structured data.

---

## What it does

### 🔍 SEO — Search Engine Optimization (Google / Bing)
- Template-driven meta **title** & **description** for products & categories (with `{{name}}`, `{{price}}`, `{{sku}}`, `{{brand}}`, `{{category}}`, `{{short_description}}`… variables).
- Global **title suffix**.
- **Canonical URL** normalization — strips tracking (`utm_*`), sort, dir, limit, and pagination params to consolidate ranking signals.
- **hreflang** alternate tags for multi-store / multi-locale.
- **Sitemap enhancements** — product image tags, optional Google News tags.
- Removes Luma's weak default meta generation.

### 🤖 AEO — Answer Engine Optimization
Optimizes content so AI answer engines (Google AI Overview, Perplexity, Bing Copilot) can **extract and cite** it:
- **`FAQPage` schema (JSON-LD)** from the `seoai_faq` product/category attribute.
- Visible **FAQ HTML block** on the product page (so the rendered content matches the structured data — critical for AEO).
- **`HowTo` schema** from the `seoai_howto` attribute.
- **`/llms.txt`** and **`/llms-full.txt`** endpoints — AI-friendly markdown site indexes.
- **`?format=md`** on any product URL returns clean Markdown for headless / AI consumption.
- Content "fact blocks" — short, extractable Q&A paragraphs.

### 🌐 GEO — Generative Engine Optimization
Makes your brand **recognizable and citable** inside AI-generated answers:
- **AI crawler control** — block/allow each major AI bot individually (default = **blocked**):
  `GPTBot`, `Google-Extended`, `ClaudeBot`, `Claude-Web`, `PerplexityBot`, `CCBot`, `Bytespider`, `ImagesiftBot`, `omgilibot`, `Diffbot`.
- **Brand entity consistency** — Organization/LocalBusiness schema, NAP (name/address/phone), `sameAs` social profiles for entity disambiguation.
- **`WebSite` + `SearchAction`** schema.

### 🖼 Open Graph & Social
- Auto OG + Twitter Card tags.
- **Auto-generated OG images** with optional **watermark** (logo overlay, position & opacity configurable) — requires PHP GD.

### ⚙️ Admin
- Config under **Stores → Configuration → SEO & AI (AEO/GEO)**, organized into SEO / Schema / AEO / GEO / OG / Sitemap groups. Global default + per-website/per-store overrides.
- **Batch Meta Generator** — apply templates to many products at once (with optional overwrite).
- **SEO/AEO Audit** — one-glance report of products missing meta / FAQ.

---

## Installation

```bash
# 1. Copy the module into app/code
#    (this repo already sits at app/code/Qiangvei/SeoAi)

# 2. Enable & install
bin/magento module:enable Qiangvei_SeoAi
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

The data patch `InstallAeoAttributes` creates the custom attributes:
- `seoai_faq` (product + category) — JSON: `[{"q":"Question?","a":"Answer."}]`
- `seoai_howto` (product) — JSON: `[{"name":"Step name","text":"Details","url":""}]`

### Recommended: serve /llms.txt and /seoai/* at the web root
These are normal Magento frontend routes (`/seoai/llms/index`, `/seoai/llms/full`), so no rewrite is strictly required. For a clean `/llms.txt` alias, add to your web-server config:

```nginx
# nginx
location = /llms.txt      { rewrite ^ /seoai/llms/index last; }
location = /llms-full.txt { rewrite ^ /seoai/llms/full last; }
```

```apache
# Apache (.htaccess, before Magento's rules)
RewriteRule ^llms\.txt$ seoai/llms/index [L]
RewriteRule ^llms-full\.txt$ seoai/llms/full [L]
```

---

## Configuration

Go to **Stores → Configuration → SEO & AI (AEO/GEO)**.

| Group | Key settings |
|---|---|
| **SEO** | Enable module, title suffix, canonical + param stripping, hreflang, product/category meta templates |
| **Schema** | Product / Breadcrumb / Organization / WebSite / FAQ / HowTo toggles, default brand, org name/logo/sameAs |
| **AEO** | FAQ HTML block, `/llms.txt`, `/llms-full.txt`, `?format=md` |
| **GEO** | Dynamic robots.txt, **AI bot block toggles (default blocked)**, brand entity NAP + sameAs |
| **OG** | OG/Twitter tags, OG image + **watermark** (image, opacity, position) |
| **Sitemap** | Image tags, news tags, llms.txt link in robots |

### Replacing Luma's default SEO
When **SEO → Enable SEO Module = Yes**, the module's head plugin overrides Luma's title/description generation. Luma's low-quality defaults (e.g. product-name-only descriptions) are replaced by your templates. Keep the module enabled to keep Luma SEO fully suppressed.

---

## Endpoint reference

| URL | Purpose |
|---|---|
| `/seoai/llms/index` (or `/llms.txt`) | Markdown index of site for AI engines |
| `/seoai/llms/full` (or `/llms-full.txt`) | Full-content version with product descriptions |
| `/product-url?format=md` | Clean Markdown view of a product |

---

## Requirements
- Magento Open Source / Adobe Commerce **2.4.x** (built & tested against **2.4.9**).
- PHP **8.4 / 8.5**.
- PHP extension **ext-gd** (for OG image watermarking; gracefully degrades without it).

---

## Architecture notes
- Follows Magento best practices: constructor injection (no ObjectManager in business logic), declarative-schema data patch (no installer scripts), config via a single `Helper\Config`, templates kept stateless with logic in ViewModels.
- Stores AEO content as **EAV attributes** — **no extra database tables**.
- Structure:
  - `Helper/Config.php` — central config reader.
  - `Block/Schema/*` + `ViewModel/JsonLd/*` — JSON-LD output.
  - `Model/RobotsBuilder`, `Model/LlmsGenerator` — GEO/AEO endpoints.
  - `Model/Meta/*` — template engine + batch generator.
  - `Model/Watermark/*` — OG watermarking.
  - `Plugin/*` — head/meta/OG/sitemap interception.
  - `Controller/Llms/*`, `Controller/Product/*` — AEO endpoints.
  - `Controller/Adminhtml/*` — batch + audit.

---

## License
MIT