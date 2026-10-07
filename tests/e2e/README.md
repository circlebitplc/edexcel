# Browser E2E tests

These tests are intentionally separate from PHPUnit and are safe-by-default:

```powershell
$env:E2E_BASE_URL = "http://localhost:8080"
$env:E2E_ALLOW_MUTATIONS = "1"
npx playwright test
```

The suite refuses to run when `E2E_BASE_URL` points at the production hostname.
Use a disposable staging database and test accounts only. Credentials are
provided through environment variables; none are stored in the repository.

Required variables for the authenticated flows:

- `E2E_ADMIN_USER`, `E2E_ADMIN_PASSWORD`
- `E2E_TEACHER_USER`, `E2E_TEACHER_PASSWORD`
- `E2E_STUDENT_USER`, `E2E_STUDENT_PASSWORD`
- `E2E_PARENT_PHONE` and the staging OTP test hook, if enabled
