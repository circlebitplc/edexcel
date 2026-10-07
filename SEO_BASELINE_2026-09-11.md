# SEO Baseline — edexcel.college

**Baseline date:** 11 September 2026 (Asia/Colombo)  
**Purpose:** Measure future SEO and conversion work against a fixed snapshot.  
**Rule:** Do not invent Search Console or Analytics numbers. Fields marked **PENDING** require property access.

---

## A. Measurement status

| Source | Status |
| --- | --- |
| Google Search Console | **PENDING** — connect property for `edexcel.college`, submit sitemaps |
| Google Analytics 4 | **PENDING** — set `GA_MEASUREMENT_ID` in `/home/edexcel.college/.env` if desired |
| PageSpeed Insights API | Rate-limited (HTTP 429) during baseline collection — re-run manually |
| Live HTTP crawl of SEO inventory | **Recorded** (below) |
| Sitemap URL count | **Recorded** |

### How to fill PENDING GSC metrics (last 28 days)

In Search Console → Performance → Export or screenshot:

| Metric | Value | Notes |
| --- | --- | --- |
| Indexed pages (Pages report) | _fill_ | Exclude soft 404s |
| Organic clicks | _fill_ | |
| Organic impressions | _fill_ | |
| Average position | _fill_ | |
| Average CTR | _fill_ | |
| Top queries (top 20) | _attach export_ | |
| Top landing pages | _attach export_ | |
| Top countries | _attach export_ | |
| Top devices | _attach export_ | |

Store exports under `private_backups/seo_baseline_2026-09-11/` (not in docroot).

---

## B. Crawlable SEO inventory (HTTP baseline)

Measured 11 Sep 2026 via live GET requests.

| Status | Count |
| --- | --- |
| HTTP 200 (key SEO URLs tested) | 28 |
| HTTP 404 (intentional missing URL test) | 1 (`/does-not-exist-seo-baseline-404-test`) |
| HTTP 500 on tested SEO URLs | **0** |

### Pages in active SEO cluster (sitemap + verified 200)

Home, About, Pearson hub, 9 country/regional hubs, Classes, IGCSE, A Level, Exam prep, FAQ, Glossary, Resources, Kandy location, 6 guides (+ IGCSE naming guide after deploy), Contact, Enquire, legal pages, Teachers.

**Approximate public SEO landing set:** ~30–35 indexable marketing URLs (excluding portals).

---

## C. Conversions / enquiries baseline

| Funnel step | URL | Baseline note |
| --- | --- | --- |
| Enquire form | `/admissions/enquire.php` | Live 200; count submissions in CRM/email for 28 days starting today |
| Contact | `/contact` | Live 200 |
| Student register | `/student/register.php` | Allow-listed in robots |
| WhatsApp / phone | NAP on SEO chrome | Track manually until GA events exist |

**PENDING:** 28-day enquiry count, enrolments attributed to organic (mark source in enquiry form if possible).

---

## D. Core Web Vitals

| Page | Mobile CWV | Source |
| --- | --- | --- |
| Hub + key landings | **PENDING re-test** | PageSpeed API returned 429 on baseline day |

**Action:** Run PageSpeed Insights manually for `/`, `/pearson-exams`, `/edexcel-classes`, `/locations/kandy`, `/faq` and paste LCP / INP / CLS into this table within Week 1.

---

## E. Technical error snapshot

| Check | Result |
| --- | --- |
| Soft 404 test | Hard 404 returned for missing path — good |
| Key SEO pretty URLs | 200 |
| Internal search | `/resources/search` noindex + robots Disallow |
| Sensitive paths | Blocked via `.htaccess` (prior work) |

---

## F. Content / authority snapshot (pre–execution deepen)

- Country pages: unique sections present; **hub deepen deployed in execution phase** (services table, process, FAQs, official links).
- Topic cluster: hub → programmes → guides → FAQ/glossary → enquire.
- Affiliation disclaimer: sitewide on SEO chrome.

---

## G. Baseline freeze instructions

1. Export GSC Performance (28 days) the day the property shows data.  
2. Do not change URLs of baseline pages without changelog entry.  
3. Compare Month+1 against this file + GSC export.  
4. Log every material SEO change in `SEO_CHANGELOG.md`.
