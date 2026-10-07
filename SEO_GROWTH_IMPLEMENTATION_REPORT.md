# SEO Growth Implementation Report

**Site:** https://edexcel.college  
**Completed:** 11 September 2026  
**Objective:** Long-term SEO growth & competitive strategy — visibility, useful content, trust, enquiries (not tool scores).

---

## What changed (this phase)

### Content & IA

| Asset | Purpose |
| --- | --- |
| `/resources` | Topical authority hub (exams, qualifications, countries, help) |
| `/resources/search` | Internal search; `noindex,follow` + robots Disallow |
| `/glossary` | Educational terminology with links to deeper pages |
| `/faq` | Categorised FAQ knowledge base + FAQPage schema |
| `/locations/kandy` | Only city page — real campus NAP and process |
| `/guides/private-candidates` | Long-tail commercial/informational intent |
| `/guides/results-and-certificates` | Results/certificate PAA intent |
| `/about` | Stronger brand differentiation |
| `/pearson-exams` | Links into hub, Kandy, new guides |

### Platform

- `includes/seo_content.php` — FAQ categories, glossary, resource index, freshness helper  
- `includes/seo_page.php` — nav/footer/related links; glossary CSS  
- `.htaccess` — pretty routes for new URLs  
- `sitemap.xml` / `robots.txt` — discovery + search noindex  

### Strategy documentation

- `SEO_GROWTH_STRATEGY.md` — competitor + SERP + PR + funnel  
- `SEO_KEYWORD_URL_MAP.md` — cannibalization map  
- `SEO_CHANGELOG.md` — measurable change log  
- `SEO_6_MONTH_ROADMAP.md` — 6-month plan  

---

## Why these changes

Competitors own **centre registration** and **marketplace tutoring** SERPs.  
`edexcel.college` wins by being the clearest **independent tuition + exam-process education** site for Kandy campus and online/hybrid markets — without claiming to be Pearson or inventing overseas offices.

---

## Highest-priority opportunities discovered

1. **Private-candidate + registration long-tails** (Sri Lanka & Gulf) — already started; keep linking to British Council/Pearson, never scrape fees.  
2. **Kandy local / brand pack** — NAP consistency + GBP.  
3. **PAA / FAQ expansion** from real admissions questions.  
4. **GSC positions 4–20** once data exists — fastest CTR wins.  
5. **Ethical PR** to schools/counsellors using the private-candidate guide.  
6. **Avoid** doorway city pages and “we are Pearson” language (trust + YMYL risk).

---

## Conversion funnel notes

**Search → landing → information → trust → enquiry → service**

- Trust: affiliation disclaimer, About, FAQ, freshness notices  
- CTA: Enquire / Register / Contact / WhatsApp on SEO chrome  
- Remaining risk: class fee transparency (quote via office — do not invent numbers)

---

## What remains

- Search Console / Bing verification and sitemap submission  
- Google Business Profile alignment  
- Quarterly FAQ refresh from real tickets  
- Optional subject pages only for taught subjects  
- Month 5 digital PR execution  
- Analytics/GSC-driven title/CTR optimisation  
- Optional campus photography / short videos for image & video search  

---

## Quality controls applied

- No copied competitor content  
- No fake city/office pages  
- No unverified exam fees or registration deadline tables  
- FAQ schema only on genuine Q&A  
- Internal search not indexed  

---

## Backup

Create a local/remote backup before/after deploy (see deploy notes). Prefer `/home/edexcel.college/private_backups/` on server — never leave zips in docroot.
