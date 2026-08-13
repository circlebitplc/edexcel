# Steps 61–70 Release Notes

Completed:
- Step 61: service factory construction fix
- Step 62: canonical application bootstrap
- Step 63: role-scoped timetable API
- Step 64: baseline response security headers
- Step 65: centralized timetable input validation
- Step 66: recurring schedule row locking for concurrent generation
- Step 67: architecture regression tests
- Step 68: validation regression tests
- Step 69: deployment health check
- Step 70: final static release gate

Important environment limitation:
`pdo_mysql` is not installed in the build/runtime environment used for this audit.
Therefore real MySQL transaction, locking, and integration tests must be run on
the target/test server.

Step 70 final static gate:
- PHP syntax failures: 0
- Direct timetable mutations outside persistence layer: 0
- Required architecture files: present
- Architecture regression test: PASS
- Validation regression test: PASS
