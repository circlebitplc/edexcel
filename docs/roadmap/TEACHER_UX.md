# EDEXCEL COLLEGE — TEACHER USER EXPERIENCE (UX) SPECIFICATION

This document outlines the professional interface layout, unified teaching stage, and interaction ergonomics designed to minimize teacher cognitive load during live classes.

---

## 1. Unified Interface Wireframe

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ TOP BAR:  [Edexcel College]  Maths 101 · A-Level | 👥 34 Students | 📶 18ms | 🔴 REC | [END CLASS]│
├───────────────┬──────────────────────────────────────────────────────────────────┬───────────────┤
│ LEFT DRAWER   │                    CENTER: MAIN TEACHING STAGE                   │ RIGHT DRAWER  │
│ (Collapsible) │                                                                  │ (Collapsible) │
│               │  ┌────────────────────────────────────────────────────────────┐  │               │
│ 📋 LESSON PLAN│  │                                                            │  │ 👥 ROSTER     │
│  00-10 Intro  │  │                                                            │  │ • Kasun (Mic) │
│  10-30 PDF ◄  │  │                 [ACTIVE TEACHING STAGE]                    │  │ • Dilshan     │
│  30-45 Board  │  │            (PDF / Board / Screen / Video / Quiz)           │  │               │
│  45-55 Quiz   │  │                                                            │  │ ✋ HANDS (1)  │
│               │  │                                                            │  │ • Amara [Mic] │
│ 📁 RESOURCES  │  │                                                            │  │               │
│ • PastPaper.pdf│  └────────────────────────────────────────────────────────────┘  │ 💬 LIVE CHAT  │
│ • Unit3.mp4   │                                                                  │ "Sir, Q3?"    │
│               │  STAGE TOOLBAR: [PDF] [Whiteboard] [Screen] [Video] [Quiz] [Poll]│ [Send to All] │
├───────────────┴──────────────────────────────────────────────────────────────────┴───────────────┤
│ BOTTOM DOCK: Screen Share Controls                                                               │
│ [🖥️ Entire Screen] [🪟 App Window] [✂️ Select Area] [🗔 Multi-App] | [⏸️ FREEZE (Alt+P)] [🛑 Stop] │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Dynamic Teaching Stage Modes

The teacher never leaves the single classroom window. Switching modes occurs instantly via the Stage Toolbar:

1. **Document / PDF Mode:**
   * Embedded high-DPI PDF renderer (PDF.js).
   * Synchronized pagination: Teacher clicks *Next Page*; all student screens turn simultaneously.
   * Floating tools: Pen, Highlighter, Eraser, and *"Sync Students to My View"*.
2. **STEM Digital Whiteboard Mode:**
   * Infinite/multi-page drawing surface with coordinate grid backgrounds.
   * Direct LaTeX equation rendering tool ($f(x) = \int x^2 dx$).
   * Mathematical tools: Snapping line ruler, protractor, vector shapes.
   * Non-persistent laser pointer with 1.5-second decay trail.
3. **Screen Sharing & Freeze State:**
   * When live: Top bar displays **`● SCREEN SHARING LIVE`** in vibrant green.
   * When paused (Alt + P): Top bar shifts to **`⏸️ SCREEN FROZEN (STUDENTS SEE FROZEN FRAME)`** in amber.
   * The teacher can minimize the browser, open grade sheets or WhatsApp, and verify at a glance that privacy is preserved.
4. **Interactive Video Mode:**
   * Edge CDN video player with teacher controls (Play, Pause, Scrub, 1.25x Speed).
   * Video checkpoints that pop up questions at exact timestamps.
5. **Live Formative Activity / Quiz Mode:**
   * Instant overlay displaying live student response distribution bar charts in real time.
   * One-click *"Reveal Solution"* button.

---

## 3. The 1-Click Pedagogical Workflow

```
Teacher Logs in & Opens "Today"
             │
             ▼
[Start Class] ── Pre-flight check loads webcam, mic, and pre-selected Lesson Plan
             │
             ▼
[Go Live] ── Unlocks waiting room, starts background recording, and sets Stage to PDF
             │
             ├─► Follows interactive Lesson Plan side-drawer
             ├─► Launches 30-sec Quick Poll to verify comprehension
             ├─► Uses Alt+P to freeze screen while checking mark scheme
             │
             ▼
[End Class] ── One single click executes automated wrap-up:
             • Saves student attendance and duration percentages
             • Exports annotated PDF slides to student dashboard
             • Auto-publishes recording to CDN
             • Sends WhatsApp attendance digests to parents
```
