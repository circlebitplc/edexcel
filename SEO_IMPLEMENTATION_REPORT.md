# SEO Implementation Report — edexcel.college

**Date:** 11 September 2026  
**Domain:** https://edexcel.college  
**Scope:** Technical SEO, information architecture, international country pages, E-E-A-T, schema, sitemap/robots, migration consistency

---

## 1. Problems found (audit)

| Area | Issue |
| --- | --- |
| Content depth | Programme landings were thin and templated |
| International SEO | No country/service hub; `areaServed` = Sri Lanka only |
| FAQ | FAQ schema on homepage without a dedicated `/faq` URL |
| E-E-A-T | No clear “independent college / not Pearson” disclaimer |
| Sitemap | Missing hub, countries, guides, FAQ |
| robots.txt | Allowed `.php` legal paths inconsistently vs pretty URLs |
| Internal links | Footer/nav lacked Pearson hub, FAQ, guides |
| Schema | Limited service/international signals |
| Migration | Apex already clean of bare `kandy.edexcel.college` in public pages; legacy host still 403 (server-level) |
| PWA icons | Previously missing (fixed in earlier session) |
| Analytics | No Search Console/analytics API access in this environment |

---

## 2. Problems fixed

- Added affiliation disclaimer on programme, hub, FAQ, guide, and country pages
- Expanded organization `areaServed` to real enquiry markets
- Dedicated FAQ page with visible Q&A matching FAQPage schema
- Created Pearson exams hub + unique country pages (not doorway clones)
- Added three verified-intent guide pages
- Updated sitemap.xml and robots.txt
- Strengthened internal linking (nav, footer, related blocks)
- Pretty URL rewrites for new routes
- Homepage footer trust copy + links

---

## 3. Pages created or modified

### Created
- `/pearson-exams` — service hub
- `/pearson-exams/{sri-lanka,italy,qatar,oman,uae,maldives,united-kingdom,india}`
- `/faq`
- `/guides/how-pearson-edexcel-exams-work`
- `/guides/how-to-register-for-pearson-exams`
- `/guides/what-to-bring-to-a-pearson-exam`
- `includes/seo_content.php`

### Modified
- `includes/seo.php`, `seo_page.php`, `college_contact.php`, `homepage-components.php`
- `edexcel-classes.php`, `edexcel-o-level.php`, `edexcel-a-level.php`, `exam-preparation.php`
- `.htaccess`, `sitemap.xml`, `robots.txt`

---

## 4. Technical SEO changes

- HTTPS + www→apex already in place; retained
- New rewrite routes for hub/countries/FAQ/guides
- Canonicals via `seo_absolute_url()` on all new pages
- No hreflang (single English site — correct; country pages are intent pages, not language alternates)
- Old domain `kandy.edexcel.college` remains retired at host level (403); cannot 301 from apex vhost alone — configure CyberPanel redirect on the old vhost if desired

---

## 5. Schema changes

- Organization: multi-country `areaServed`, richer `knowsAbout`, clearer independent description
- Service schema on hub + country pages
- FAQPage on `/faq` (and homepage FAQs still available)
- Course + Breadcrumb + WebSite retained on programme pages
- LocalBusiness address remains **Kandy only** (no fake offices)

---

## 6. Keyword strategy

**Primary intents:** Pearson exams, Pearson Edexcel exams, Edexcel IGCSE/O Level, Edexcel A Level/IAL, exam preparation, exam registration guidance  
**Modifiers:** Sri Lanka / Kandy (campus), Italy, Qatar, Oman, UAE, Maldives, UK, India (online tuition)  
**Approach:** One strong hub + unique country copy + guides; no keyword stuffing; clear independent-college positioning

---

## 7. Country targeting strategy

| Market | Approach |
| --- | --- |
| Sri Lanka | Campus + local tuition page |
| Italy, Qatar, Oman, UAE, Maldives, UK, India | Unique online-support pages; explicitly no local office claim |
| Saudi Arabia, Bahrain, Kuwait | Mentioned on hub with enquire CTA (no thin clone pages) |

---

## 8. Internal linking

Homepage footer → hub, programmes, FAQ, legal  
SEO nav → hub, programmes, FAQ, contact  
Related blocks → cross-link programmes, hub, Sri Lanka, FAQ  
Guides ↔ registration ↔ exam-day checklist

---

## 9. Performance

- Marketing pages remain lightweight HTML/CSS (no heavy JS frameworks)
- Existing lazy-loading on homepage images retained
- Icons already present for OG/PWA

---

## 10. Remaining issues / next steps

1. **Old vhost 301:** Apply `deploy/kandy-to-apex-redirect.md` in CyberPanel so `kandy.edexcel.college/*` → `https://edexcel.college/$1` (host may still return 403 until configured)
2. **Google Search Console / Bing Webmaster:** Add `edexcel.college`, submit `sitemap.xml`, request indexing for hub + country URLs
3. **Credential rotation:** Still recommended after prior `.env` exposure; keep live secrets at `/home/edexcel.college/.env`
4. **Crontab paths:** Ensure `/home/edexcel.college/public_html/tools/...`
5. **Meta / OnePay / Bunny:** Confirm webhook URLs use apex domain
6. **Content depth over time:** Add subject-level pages only when real timetable subjects warrant them
7. **GBP / brand:** Public brand is **Edexcel College**; keep Google Business Profile NAP identical to the Kandy campus address only
8. **Measure:** After 2–4 weeks, review GSC queries/CTR for hub and country pages
9. **FTP deploy:** Use `.vscode/sftp.json` profile `edexcel.college` → `/home/edexcel.college/public_html`
10. **Settings:** Admin → Settings → Institute name should be `Edexcel College` (`APP_NAME` in `/home/edexcel.college/.env` likewise)

---

## 11. Black-hat / quality safeguards applied

- No doorway country clones  
- No fake reviews/ratings/offices  
- No hreflang spam  
- Explicit non-affiliation with Pearson  
- Official Pearson link used as external reference only  
