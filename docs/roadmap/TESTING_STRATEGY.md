# EDEXCEL COLLEGE — TESTING STRATEGY & VERIFICATION GATES

This document details the multi-tiered verification framework ensuring every phase leaves the system 100% operational, backward compatible, and risk-free.

---

## 1. Testing Framework Integration

The project already possesses structured PHP test gates in `public_html/tests/`:
* `Step51IntegrationGate.php`
* `Step67ArchitectureTest.php`
* `Step68ValidationTest.php`
* `Step69DeploymentHealthCheck.php`
* `Step78RegressionTest.php`

All new classroom features must integrate into these established gates before code deployment.

---

## 2. Test Verification Matrix by Subsystem

### A. Native C++ Desktop Companion (`edexcel_server.exe`)
1. **Freeze-Frame Integrity Test:**
   * *Procedure:* Initiate screen capture; send pause command via HTTP `/monitor/share/pause`; verify that subsequent desktop redraws (moving a window) do not change the MD5 hash of the served JPEG thumbnail on port 5800.
2. **Alt + P Hotkey Registration Test:**
   * *Procedure:* Verify `RegisterHotKey` returns `TRUE` on startup; simulate `WM_HOTKEY` and verify `m_isPaused` toggles between `true` and `false`.
3. **Window Enumeration & Area Selection Test:**
   * *Procedure:* Confirm `WindowEnumerator::getWindowsJson()` returns valid `HWND` handles and non-zero dimensions without crashing when windows are closed mid-call.

### B. Web Classroom Stage State Machine (`classroom/room.php`)
1. **Stage Transition Stability Test:**
   * *Procedure:* Rapidly cycle stage modes (`PDF` -> `WHITEBOARD` -> `SCREEN_SHARE` -> `VIDEO` -> `QUIZ` -> `PDF`) at 500 ms intervals.
   * *Assertion:* LiveKit audio remains uninterrupted; no WebGL context loss; DOM nodes clean up without memory leaks.
2. **Synchronized PDF Coordinate Test:**
   * *Procedure:* Host changes page to 5 and zooms to 150%; student client receives `PDF_STATE` via DataChannel; verifies student rendered canvas displays page 5 at corresponding zoom within 300 ms.
3. **Quick Poll Concurrency Test:**
   * *Procedure:* Host triggers a 4-choice poll; simulate 50 concurrent student responses via REST/DataChannel; verify `classroom_poll_responses` records exactly 50 rows with correct totals and no deadlocks.

### C. LiveKit & Audio/Video Resiliency
1. **Bandwidth Degradation Simulation:**
   * *Procedure:* Throttle student network to 64 kbps packet rate; verify Dynacast automatically unsubscribes video tracks while maintaining Opus audio continuity.
2. **Disconnection & Reconnection Test:**
   * *Procedure:* Simulate socket drop for 10 seconds; verify client auto-reconnects, fetches `api/classroom/status.php`, and restores stage state without full browser refresh.

---

## 3. Rollback & Safety Gates

Prior to each architectural change:
1. **Database Safety:** All schema modifications must use idempotent `ADD COLUMN IF NOT EXISTS` and `CREATE TABLE IF NOT EXISTS` syntax.
2. **Binary Restore Points:** Compiled `edexcel_server.exe` binaries are version-tagged in `Release/x64/` with timestamps (e.g., `edexcel_server_backup_YYYYMMDD.exe`).
3. **Automated Rollback Trigger:** If any deployment gate (`Step69DeploymentHealthCheck.php`) fails with exit code $\neq 0$, the deploy script immediately reverts symlinks to the previous stable release.
