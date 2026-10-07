# Advanced SEO Audit Report — edexcel.college

**Date:** 11 September 2026  
**Site:** https://edexcel.college  
**Method:** Live URL inspection + codebase review + targeted fixes (no tool-score chasing)

---

## 1. Current SEO health

| Area | Status |
| --- | --- |
| HTTPS / apex canonical | Healthy |
| Marketing crawl surface | Healthy and expanded |
| Entity clarity (About) | **Added** |
| Independent-college trust | Healthy (disclaimer + policies) |
| International IA | Healthy (unique countries + Gulf regional) |
| Guides / FAQ freshness signals | **Improved** (bylines, Article schema) |
| Analytics | Optional via `GA_MEASUREMENT_ID` env (not forced) |
| Old domain | Still 403 at host; apex links clean |

**Overall:** Solid foundation after prior SEO work; this pass closes entity, cannibalization, AI-readable answers, a11y, and monitoring gaps.

---

## 2. Technical issues found

| Priority | Issue |
| --- | --- |
| Critical | Missing About / entity page |
| High | Guide pages lacked author/date/Article schema |
| High | `/edexcel-classes` vs `/pearson-exams/sri-lanka` keyword overlap risk |
| High | Contact/legal nav still pointed at `.php` policy URLs in places |
| Medium | No image sitemap; weak answer-first formatting for AI/search |
| Medium | Saudi/Bahrain/Kuwait only mentioned briefly (thin coverage) |
| Medium | Privacy lacked cookie clarity |
| Medium | SEO pages lacked skip-link / focus styles |
| Low | No GA wired (acceptable until ID configured) |
| Low | No public PDFs/videos to optimize |
| Info | `kandy.edexcel.college` still 403 (CyberPanel-level redirect still recommended) |

---

## 3. Issues fixed

- Created `/about` with factual org/services/contact/disclaimer
- Added Article schema + bylines + sources on all three guides
- Differentiated campus classes vs Sri Lanka country page intents
- Added Gulf regional page (SA/BH/KW) with unique sections
- Contact page: international enquiry section, IST timezone, pretty policy links
- Privacy: cookies section; last-updated stamp
- FAQ: direct-answer block for AI/search extraction
- Accessibility: skip link, `:focus-visible`, `<main>` landmark on SEO chrome
- Optional GA4 bootstrap when `GA_MEASUREMENT_ID` is set in env
- Image sitemap + robots reference
- Monitoring checklist document

---

## 4. Content improvements

- Answer-first blocks on About, FAQ, guides, Sri Lanka/classes
- Guide step lists / definitions for snippet-friendly structure
- Sources sections pointing to Pearson official site (no fake affiliation)
- Contact conversion path for international families

---

## 5. International SEO improvements

- Existing unique country pages retained
- New `/pearson-exams/gulf` for Saudi Arabia, Bahrain, Kuwait
- Explicit “no local office” language reinforced
- Time zone guidance (IST / AST) where useful

---

## 6. Schema / structured-data changes

- `Article` on guides (`datePublished`, `dateModified`, org author)
- Existing Org / Service / FAQ / Course / Breadcrumb retained
- LocalBusiness address remains Kandy-only
- No review/rating spam; no fake Pearson `sameAs`

---

## 7. Internal-linking changes

- Nav/footer: About added sitewide (SEO chrome + homepage footer + legal nav)
- Classes ↔ Sri Lanka cross-links to reduce cannibalization
- Hub points to Gulf guide
- Contact links to About, country hub, enquire

---

## 8. Performance improvements

- No heavy scripts added by default
- GA loads only if env ID present
- Marketing pages remain lightweight HTML/CSS
- Lazy-loading retained on maps/images elsewhere

---

## 9. Accessibility improvements

- Skip to main content
- Focus-visible outlines
- Main landmark wrapping SEO content
- FAQ `<details>`/`<summary>` retained for keyboard use

---

## 10. Indexing / crawling changes

- Sitemap: `/about`, `/pearson-exams/gulf`
- robots: Allow `/about`; image sitemap declared
- ppt/admin/api remain disallowed
- Portal default `noindex` unchanged for authenticated chrome

---

## 11. Old-domain migration issues

- Public apex pages: no bare `kandy.edexcel.college` references in verified SEO URLs
- LiveKit hosts intentionally keep `live.kandy…`
- Remaining host-level task: 301 legacy vhost → apex if CyberPanel allows

---

## 12. New content opportunities (next)

1. Subject-level pages only when real timetable subjects exist  
2. Short “how online class works” guide with screenshots  
3. Teacher profile JSON-LD (`Person`) once profiles are consistently public  
4. Genuine social profile URLs in `sameAs` when official accounts exist  
5. Optional short explainer video + VideoObject (only if real)

---

## 13. Remaining risks

| Risk | Priority |
| --- | --- |
| Legacy domain still 403 (equity not transferred via 301) | High |
| Secrets previously exposed — rotate credentials if not done | Critical |
| Brand name “Edexcel College” can still confuse users — disclaimer must stay visible | Medium |
| Without GSC/GA, measurement is incomplete | Medium |
| Over-expanding country pages later could reintroduce doorway risk | Medium |

---

## 14. Recommended priorities (3–6 months)

### Critical
1. Rotate any credentials exposed during migration  
2. Add Search Console + Bing; submit sitemaps  

### High
3. Configure CyberPanel 301 from `kandy.edexcel.college` → apex (see `deploy/kandy-to-apex-redirect.md`)  
4. Set `GA_MEASUREMENT_ID` in `/home/edexcel.college/.env` if analytics desired  
5. Monthly run of `SEO_MONITORING_CHECKLIST.md`  

### Medium
6. Deepen 2–3 highest-impression subjects from GSC  
7. Add official social URLs to Organization `sameAs` when verified  
8. Improve LCP of homepage hero imagery (compress/responsive)  

### Low
9. Teacher Person schema  
10. Optional video explainers  

---

## Search-intent map (important pages)

| URL | Intent |
| --- | --- |
| `/` | Navigational + commercial |
| `/about` | Navigational / trust |
| `/pearson-exams` | Commercial investigation |
| `/edexcel-classes` | Transactional (campus enrol) |
| `/pearson-exams/sri-lanka` | Informational + local commercial |
| Country / gulf pages | Informational + commercial (online) |
| Guides | Informational |
| `/faq` | Informational |
| `/contact`, enquire, register | Transactional |
| Policies | Trust / compliance |

---

## Validation snapshot (this pass)

Deployed and intended live checks: `/about`, guides with Article JSON-LD, differentiated classes page, `/pearson-exams/gulf`, updated sitemap/robots, contact/privacy updates.
