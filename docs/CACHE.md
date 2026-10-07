# Cache

## Implementation
- Low-level: `config/cache.php` (`Cache` class, file-based under `cache/`).
- Wrapper: `Edexcel\Services\CacheService` with `remember` / `get` / `forget` / `invalidatePrefix`.

## Safe public keys (helpers)
| Helper | Key pattern | Use |
|--------|-------------|-----|
| `keySubjects()` | `catalog:subjects:v1` | Subject catalog |
| `keyTeachers()` | `catalog:teachers:v1` | Teacher list |
| `keyRooms()` | `catalog:rooms:v1` | Rooms |
| `keyHomepage()` | `public:homepage:v1` | Public homepage fragments |
| `keyPublicTimetable($week)` | `public:timetable:{week}` | Public timetable |
| `keyOfficialExams($seriesId)` | `public:official_exams:...` | Official exam catalog |

## Rules
- **Never** cache personalized sensitive data (fees, attendance for a student, tokens, OTPs) via these helpers.
- Prefer short TTLs (5–60 minutes) for catalogs that change in admin UI; call `forget` / `invalidatePrefix('catalog:')` after edits.
- Service worker caches static CSS only; HTML/API remain network-first.
