# EDEXCEL COLLEGE — STUDENT USER EXPERIENCE (UX) SPECIFICATION

This document outlines the student interface across desktop, tablet, and mobile devices, focusing on clarity, low distraction, and interactive responsiveness.

---

## 1. Desktop & Laptop Layout

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ TOP:  Edexcel College | Maths 101 · A-Level | 👨‍🏫 Mr. Perera | 🔴 Live 00:32:15 | 📶 Excellent   │
├──────────────────────────────────────────────────────────────────────────────────┬───────────────┤
│                                                                                  │ 📹 TEACHER    │
│                           CENTRAL LEARNING STAGE                                 │ [Video Tile]  │
│                                                                                  ├───────────────┤
│   • Synchronized PDF Slides (with Teacher Pen Markings)                          │ 💬 CLASS CHAT │
│   • Or Whiteboard, Screen Stream, or Interactive Quiz                            │ • Kasun: Done │
│                                                                                  │ • You: Yes sir│
│   [Floating Pill: 🔄 SYNC WITH TEACHER] (Visible only when scrolled back)        │               │
│                                                                                  │               │
├──────────────────────────────────────────────────────────────────────────────────┴───────────────┤
│ BOTTOM BAR:  [🎤 Mute/Unmute]  [✋ Raise Hand]  [👍 Reactions]  [⚙️ Settings]    [🚪 Leave Class] │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Responsive Mobile & Tablet Layout

### Tablet (iPad & Android Tablets)
* Preserves side-by-side desktop layout with touch-optimized controls.
* Stylus support (Apple Pencil / S-Pen) enabled on the whiteboard during student collaboration periods with palm rejection.

### Mobile Phone (Portrait & Landscape)
* **Portrait Mode:** 
  * Top 45% of screen: Primary stage content (Teacher screen/PDF/Board).
  * Bottom 55% of screen: Tabbed interface switching between `Chat`, `Activity/Quiz`, and `Participants`.
  * Controls minimize to a bottom floating action pill to maximize viewing area.
* **Landscape Mode:**
  * Full-bleed stage display with collapsible translucent overlay icons for Chat and Raise Hand.

---

## 3. Interactive Engagement Features

1. **Independent PDF Review & "Sync with Teacher":**
   * Students can freely scroll back to read an earlier question or definition from slide 2 while the teacher is currently explaining slide 5.
   * A floating banner appears: **`You are on Page 2 (Teacher is on Page 5) [Sync with Teacher]`**.
   * Clicking the banner snaps the student immediately back to the teacher's active page and zoom.
2. **One-Tap Formative Activities:**
   * When the teacher launches a 45-second Quick Poll, an interactive card slides up over the bottom third of the screen without obscuring the teacher's voice.
   * Large, touch-friendly option buttons (A, B, C, D).
   * Instant feedback upon answer submission: *"Answer submitted! Waiting for teacher to reveal results."*
3. **Adaptive Connection Health Alerts:**
   * When network bandwidth fluctuates, clear non-alarming status pills appear:
     * *"Network slow: Teacher camera disabled to keep audio crystal clear."*
