# EDEXCEL COLLEGE — LIVEKIT & WEBRTC SFU ARCHITECTURE

This document specifies the WebRTC media pipeline, Selective Forwarding Unit (SFU) track policies, and DataChannel messaging protocols for the live classroom.

---

## 1. WebRTC Track Topology & Stage-Aware Dynacast

```
┌────────────────────────────────────────────────────────────────────────┐
│                        LIVEKIT SFU MEDIA SERVER                        │
├────────────────────┬─────────────────────────────┬─────────────────────┤
│ Audio Engine       │ Video Tracks (Dynacast)     │ WebRTC DataChannel  │
├────────────────────┼─────────────────────────────┼─────────────────────┤
│ • Opus Codec       │ • Teacher Camera (720p/30)  │ • Stage Sync Packet │
│ • Priority: HIGH   │ • Screen Share (1080p/5-30) │ • Whiteboard Deltas │
│ • Bitrate: 32 kbps │ • Student Video (Simulcast: │ • Quick Poll Events │
│ • Auto-Ducking     │   180p / 360p / 720p)       │ • Screen Freeze Flag│
└────────────────────┴─────────────────────────────┴─────────────────────┘
```

### Stage-Aware Subscription Optimization
When the teacher changes the teaching stage mode, the client dynamically adjusts video subscription quality to conserve client CPU and bandwidth:

1. **Stage Mode = `SCREEN_SHARE` or `PDF`:**
   * Student cameras are automatically throttled down to **low quality** (180p @ 10 FPS) via `participant.setSubscribedQuality(VideoQuality.LOW)`.
   * Screen share track is given maximum bitrate priority.
2. **Stage Mode = `WHITEBOARD` or `QUIZ`:**
   * Screen share track is paused/unsubscribed.
   * Whiteboard vector coordinates transmit over the reliable DataChannel (consuming $< 5$ kbps).
3. **Stage Mode = `STUDENT_SPOTLIGHT`:**
   * The spotlighted student's camera switches to **high quality** (720p @ 30 FPS).
   * All other student tiles drop to thumbnail resolution.

---

## 2. WebRTC DataChannel Event Schema

All stage transitions, whiteboard strokes, and poll events flow through LiveKit's real-time DataChannel (topic: `classroom-bus`).

### Event Formats (JSON Payloads):

#### A. Stage Transition Event
```json
{
  "type": "STAGE_CHANGE",
  "mode": "pdf",
  "payload": {
    "docId": 42,
    "page": 5,
    "zoom": 1.25
  },
  "timestamp": 1727546400
}
```

#### B. Screen Freeze / Pause Flag
```json
{
  "type": "SCREEN_FREEZE_TOGGLE",
  "isPaused": true,
  "lastFrameTimestamp": 1727546412
}
```

#### C. Quick Poll Trigger
```json
{
  "type": "POLL_START",
  "pollId": 89,
  "question": "What is the derivative of sin(2x)?",
  "options": ["cos(2x)", "2cos(2x)", "-2cos(2x)", "cos^2(x)"],
  "durationSeconds": 45
}
```

#### D. Vector Whiteboard Delta
```json
{
  "type": "WB_STROKE",
  "page": 1,
  "tool": "pen",
  "color": "#4f8ef7",
  "size": 3,
  "points": [120.5, 45.0, 122.0, 46.5, 125.0, 49.0]
}
```

---

## 3. Resilience & Bandwidth Adaptation for Weak Networks

For students connecting via 3G/4G or congested domestic Wi-Fi:

1. **Audio First Policy:** LiveKit automatically prioritizes audio packets (`Content-Type: audio/opus`) with DSCP tagging (`EF` - Expedited Forwarding). If bandwidth drops below 100 kbps, video tracks are paused, but audio remains clear.
2. **Automatic Reconnect Recovery:**
   * When a student's socket disconnects, the client retains the current whiteboard canvas and PDF page in DOM memory.
   * Upon reconnecting, the client queries `api/classroom/status.php` to fetch only missing deltas, avoiding a full page reload.
3. **Dual TURN Fallback:**
   * Standard UDP TURN on port 3478.
   * TLS TURNS on port 443 to bypass restrictive corporate/school firewalls.
