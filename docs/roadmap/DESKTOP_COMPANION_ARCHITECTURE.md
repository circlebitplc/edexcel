# EDEXCEL COLLEGE — DESKTOP COMPANION ARCHITECTURE

This document details the native Windows C++ engine (`edexcel_server.exe`), its OS-level hooks, screen capture pipelines, and freeze-frame privacy mechanisms.

---

## 1. Core Native Subsystems & Source Files

```
┌────────────────────────────────────────────────────────────────────────┐
│                        EDEXCEL_SERVER.EXE (C++)                        │
├────────────────────┬─────────────────────────────┬─────────────────────┤
│ Capture & Frame    │ Window & Area Management    │ IPC & Web Bridge    │
├────────────────────┼─────────────────────────────┼─────────────────────┤
│ • GDI Desktop &    │ • WindowEnumerator.cpp      │ • HttpRequestHandler│
│   DXGI Duplication │   (HWND Enumeration)        │ • MonitorServer.cpp │
│ • UpdateSender.cpp │ • AreaSelectorWindow.cpp    │ • Loopback API      │
│   (Frame Loop)     │   (Layered Drag Overlay)    │   (Port 5800)       │
│ • ViewPort.cpp     │ • RegisterHotKey Hook       │ • RFB/VNC Lab Core  │
│   (Crop/Multi-Win) │   (Alt + P System-wide)     │   (Port 5900)       │
└────────────────────┴─────────────────────────────┴─────────────────────┘
```

---

## 2. Freeze-Frame Implementation Blueprint (Fixing Pause Behavior)

### Current Problem
In `fb-update-sender/UpdateSender.cpp` (lines 967-975), entering pause currently executes:
```cpp
m_frameBuffer.setColor(20, 24, 32); // Erases the screen with a dark slate!
```
This contradicts the educational requirement where students must **continue seeing the last live lecture slide** while the teacher switches to private notes or emails.

### Target Implementation
1. **Hold Frame in Buffer:**
   When `m_senderControlInformation->isViewPortPaused()` becomes true:
   * Do **NOT** clear `m_frameBuffer` with `setColor()`.
   * Retain the existing bitmap pixels in `m_frameBuffer`.
   * Clear dirty regions (`updCont->changedRegion.clear()`, `updCont->copiedRegion.clear()`).
   * Suppress the desktop grabber loop from writing new screen changes into `m_frameBuffer`.
2. **Handle Student Refresh Requests:**
   If a newly joined student requests a full frame update while paused, respond using the **cached frozen bitmap**, so late joiners also see the frozen lecture frame.
3. **Resumption:**
   When `isViewPortPaused()` becomes false:
   * Set `updCont->screenSizeChanged = true;`
   * Trigger `m_updateKeeper->dazzleChangedReg();` to immediately capture and blast the fresh live desktop state to all students.

---

## 3. Global Alt + P Hotkey System Service

Web browsers cannot receive hotkeys when they are minimized or running in the background. The native C++ Companion solves this using the Win32 API:

```cpp
// Registered during server startup:
#define ID_HOTKEY_SCREEN_PAUSE 0xE001

BOOL RegisterClassroomHotkeys(HWND hwnd) {
    return ::RegisterHotKey(hwnd, ID_HOTKEY_SCREEN_PAUSE, MOD_ALT, 'P');
}

// In the Windows Message Dispatcher:
case WM_HOTKEY:
    if (wParam == ID_HOTKEY_SCREEN_PAUSE) {
        MonitorServer *ms = MonitorServer::getInstance();
        if (ms) {
            if (ms->isPaused()) {
                ms->resumeSharing();
                ShowTrayBalloon("Screen Resumed", "Students are now seeing your live screen.");
            } else {
                ms->pauseSharing();
                ShowTrayBalloon("Screen Paused (Frozen)", "Students are seeing the frozen frame. Private work enabled.");
            }
        }
    }
    break;
```

---

## 4. Multi-Window & Area Capture Mechanics

1. **Layered Selection Window (`AreaSelectorWindow.cpp`):**
   * Uses `WS_EX_LAYERED | WS_EX_TOPMOST` with semi-transparent cyan fill (`RGB(79, 142, 247)` at 30% alpha).
   * Teacher clicks and drags on screen to define rectangular coordinates `(left, top, right, bottom)`.
   * Immediately updates `ViewPortState::setArbitraryRect()` and closes the overlay.
2. **Selective Window Enumeration (`WindowEnumerator.cpp`):**
   * Calls `EnumWindows`, filtering out invisible, minimized, or tool windows.
   * Returns JSON array with `hwnd`, `title`, `processName`, `x`, `y`, `width`, `height`.
   * Allows single-window or composite multi-window tracking even as windows move across the desktop.

---

## 5. Loopback HTTP & WebSocket Bridge (`127.0.0.1:5800`)

The web classroom communicates with the companion via local loopback endpoints:
* `GET /monitor/share/status` -> `{ "mode": "area", "isPaused": true, "rect": { ... } }`
* `POST /monitor/share/pause` -> Freezes student frame.
* `POST /monitor/share/resume` -> Unfreezes student frame.
* `POST /monitor/share/select_area` -> Launches the native overlay selector.
* `POST /monitor/share/set?mode=window&hwnd=0x1234` -> Locks capture to selected application.
