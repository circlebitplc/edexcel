# EDEXCEL COLLEGE — TARGET ARCHITECTURE SPECIFICATION

This document outlines the two-layer architecture uniting the high-performance native Windows engine (`edexcel_server.exe`) with the browser-based LiveKit WebRTC LMS platform (`classroom/room.php`).

---

## 1. High-Level Architectural Schema

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 TEACHER WORKSTATION                                    │
│                                                                                        │
│   ┌────────────────────────────────────────┐  Local IPC Loopback   ┌────────────────┐  │
│   │      LAYER 1: DESKTOP COMPANION        │  HTTP / WS 127.0.0.1  │ LAYER 2: WEB   │  │
│   │         (edexcel_server.exe)           ├──────────────────────►│ CLASSROOM      │  │
│   │                                        │   Token-Auth Bridge   │ (room.php)     │  │
│   │  • Win32 Global Hook (Alt+P)           │                       │                │  │
│   │  • Area Selection Overlay              │                       │ • LiveKit Room │  │
│   │  • HWND Window Capture & Masking       │                       │ • WebRTC Video │  │
│   │  • Freeze-Frame Buffer VRAM            │                       │ • Whiteboard   │  │
│   │  • System Tray Controller              │                       │ • PDF Sync     │  │
│   │  • LAN RFB/VNC Lab Server (Port 5900)  │                       │ • Quizzes/Polls│  │
│   └───────────────────┬────────────────────┘                       └────────┬───────┘  │
└───────────────────────┼─────────────────────────────────────────────────────┼──────────┘
                        │                                                     │
         Local LAN RFB  │                                    WebRTC / TLS     │
         Zero-Internet  │                                    Internet / WAN   │
                        ▼                                                     ▼
     ┌──────────────────────────────────────┐             ┌───────────────────────────────┐
     │  LOCAL COMPUTER LAB / SMART PROJECTOR│             │ REMOTE STUDENTS & LIVEKIT SFU │
     │  • Direct LAN Stream (Port 5800/5900)│             │ • Cloud/Server Video & Audio  │
     │  • No internet consumption           │             │ • Interactive Activity Sync   │
     └──────────────────────────────────────┘             └───────────────────────────────┘
```

---

## 2. Layer 1: Edexcel Desktop Companion (`edexcel_server.exe`)

### Purpose
To provide native operating system capabilities that web browsers are forbidden from executing due to browser sandbox security models:
1. **System-Wide Global Hotkeys:** Detecting `Alt + P` even when the browser is minimized or in the background.
2. **Selective Window & Area Capture:** Cropping arbitrary screen rectangles and capturing specific `HWND` windows without browser capture prompts.
3. **True Freeze Frame (Privacy Pause):** Holding the last active frame in a dedicated framebuffer while the teacher opens private windows (WhatsApp, gradebook, email) without student visibility.
4. **Local LAN Streaming Engine:** Serving low-latency desktop streams directly over LAN to lab computers on port 5800/5900 without internet data usage.
5. **System Tray Integration:** Floating mini-bar showing:
   * Sharing Status: `[● LIVE]` or `[⏸ PAUSED]`
   * Current Source: `Entire Screen`, `Area (1280x720)`, `App (Visual Studio Code)`
   * One-click Pause / Resume toggle.

---

## 3. Layer 2: Unified Web Classroom (`classroom/room.php` + LiveKit SFU)

### Purpose
To deliver the collaborative pedagogy, audio/video synchronization, and academic tracking:
1. **LiveKit WebRTC Pipeline:** Manages multi-party audio, webcam feeds, adaptive simulcasting, and network reconnection.
2. **Pedagogical Stage Management:** The central viewport switching between PDF, Whiteboard, Video, Screen Share, and Quizzes.
3. **DataChannel Activity Bus:** Low-latency transmission of whiteboard vector deltas, laser pointer trails, poll prompts, and student answers.
4. **Database-Backed Session Ledger:** Records timestamps, durations, marks, and lesson history in MySQL.

---

## 4. Inter-Layer Communication Protocol (Bridge)

The Web Classroom communicates with the Desktop Companion via a secure loopback connection:
* **Protocol:** `http://127.0.0.1:5800/api/companion/*` (HTTP GET/POST) and `ws://127.0.0.1:5800/companion/events` (WebSocket).
* **Security:** Authenticated using the teacher's active session token passed from PHP (`window.CK_CONFIG.companionToken`).
* **Bidirectional Events:**
  1. *Desktop -> Web:* Emits `SCREEN_PAUSED`, `SCREEN_RESUMED`, `SOURCE_CHANGED`, `HOTKEY_TRIGGERED`.
  2. *Web -> Desktop:* Commands `SET_SOURCE {mode, hwnd, rect}`, `PAUSE`, `RESUME`, `LAUNCH_AREA_SELECTOR`.
