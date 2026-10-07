# SEO monitoring checklist — edexcel.college

Run monthly (or after any major deploy).

## Indexing & crawl
- [ ] Google Search Console → Coverage / Pages: no unexpected exclusions
- [ ] Submit/refresh `https://edexcel.college/sitemap.xml` and `sitemap-images.xml`
- [ ] Request indexing for `/resources`, `/glossary`, `/locations/kandy`, `/guides/private-candidates`, `/guides/results-and-certificates`
- [ ] Review growth strategy docs: `SEO_GROWTH_STRATEGY.md`, `SEO_6_MONTH_ROADMAP.md`, `SEO_KEYWORD_URL_MAP.md`
- [ ] Spot-check that `/about`, `/pearson-exams`, `/faq`, country pages are **Indexed**
- [ ] Confirm private areas remain out of index (`/admin`, `/student/dashboard`, `/api`)
- [ ] Review 404 report; fix internal links or add targeted 301s (never blanket homepage redirects)

## Search performance
- [ ] Impressions, CTR, average position for hub + country + guide queries
- [ ] Cannibalization check: `edexcel-classes` vs `pearson-exams/sri-lanka` (should serve different intents)
- [ ] Device split (mobile vs desktop) and Core Web Vitals where available

## Content freshness
- [ ] Re-read guides for outdated exam procedures; update **Last reviewed** only when content actually changes
- [ ] Verify contact NAP matches campus reality
- [ ] Confirm affiliation disclaimer still accurate

## Technical
- [ ] HTTPS, www→apex, no mixed content
- [ ] `.env` lives at `/home/edexcel.college/.env` (not under `public_html`)
- [ ] Backup zips not in docroot
- [ ] robots.txt still allows marketing URLs and blocks tools/config

## Conversions
- [ ] Enquire / register / WhatsApp CTAs work on mobile
- [ ] International enquiry path clear (country page → enquire)

## Security / trust
- [ ] No public backups, no debug output
- [ ] Privacy/terms/refund pages reachable from footer
