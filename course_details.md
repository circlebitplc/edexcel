# Pearson Edexcel Pathway — Course Details

> **Institute:** Edexcel College  
> **Pathway:** Pearson Edexcel IGCSE & International A Level (IAL)  
> **Location:** No 83 Katugatota Road, Kandy, Sri Lanka  
> **Live portal:** https://edexcel.college  
> **Document source:** Project data in `database/seeds/official_exams.json`, homepage copy, Courso practice bank, and system documentation.  
> **Generated note:** Lesson-by-lesson teaching scripts, per-topic learning objectives, and formal assignment briefs are **not stored** in this repository. Those items are marked **Not specified** rather than invented.

---

## Table of Contents

1. [Course Overview](#1-course-overview)
2. [Complete Course Structure](#2-complete-course-structure)
3. [Detailed Content](#3-detailed-content)
4. [Practical Work](#4-practical-work)
5. [Resources](#5-resources)
6. [Assessment](#6-assessment)
7. [Learning Roadmap](#7-learning-roadmap)
8. [Cheat Sheets / Quick Reference](#8-cheat-sheets--quick-reference)
9. [Final Project / Capstone](#9-final-project--capstone)
10. [FAQ](#10-faq)
11. [Appendix A — Full Official Exam Series Timetables](#11-appendix-a--full-official-exam-series-timetables)
12. [Appendix B — Data Provenance](#12-appendix-b--data-provenance)

---

## 1. Course Overview

### Course name

**Pearson Edexcel IGCSE & International A Level (IAL) pathway** — taught at **Edexcel College**.

Homepage tagline (from `includes/homepage-data.php`):

> Pearson Edexcel IGCSE & International A Level

### Description

Edexcel College is a Pearson/Edexcel-oriented tuition college in Sri Lanka. Teaching is aligned with Pearson Edexcel syllabus and examination requirements for:

| Qualification | Code (portal default) | Level |
| --- | --- | --- |
| International General Certificate of Secondary Education | IGCSE | Secondary / GCSE-equivalent |
| International Advanced Level | IAL | Post-16 / A Level-equivalent |

The college portal (https://edexcel.college) supports timetable, live/hybrid classes, recordings, homework, fees, parent updates, WhatsApp guidance, and an official Pearson exam planner. It is **not** a generic Moodle-style LMS with full packaged courseware for every topic.

College contact (from `includes/college_contact.php` defaults):

| Field | Value |
| --- | --- |
| Name | Edexcel College |
| Email | info@edexcel.college |
| Phone / WhatsApp | +94 78 585 8585 (default hotline) |
| Address | No 83 Katugatota Road, Kandy, Sri Lanka |
| Hours | Monday–Friday 8:00–18:00 · Saturday 8:00–14:00 |
| Timezone | Asia/Colombo |
| Default class fee | Rs 500 / student (overridable per lesson) |

### Objectives

Stated institutionally (homepage / system messaging):

- Deliver **Edexcel-focused teaching** aligned with Pearson syllabuses and exam requirements.
- Provide **exam-focused preparation** using official paper structures and college timetable/exam planner tools.
- Support students with **timetable, recordings, live classes, homework, fees, and parent visibility**.
- Offer **Talk with AI / Courso** practice quizzes styled for Pearson Edexcel secondary students in Sri Lanka.

Formal numbered academic learning outcomes for a single packaged course: **Not specified** in repository content.

### Prerequisites

| Pathway | Prerequisites in repo |
| --- | --- |
| IGCSE | **Not specified** beyond general secondary enrolment |
| IAL | **Not specified**; typically follows IGCSE / O Level or equivalent (not coded as a hard gate) |
| Student portal access | Student registration / login; device confirmation may apply for live class & recordings |
| Paid lessons / recordings | Fee payment (OnePay, cash, bank slip) where configured |

### Target audience

- Secondary students preparing for **Pearson Edexcel IGCSE** papers.
- Post-16 students preparing for **Pearson Edexcel International A Level (IAL)** units.
- Parents supporting IAL / IGCSE learners (parent portal testimonials reference IAL Mathematics and IGCSE Science).
- Teachers and campus staff delivering the pathway at Edexcel College.

### Expected outcomes

| Outcome | Availability |
| --- | --- |
| Sit Pearson Edexcel IGCSE / IAL papers in the imported official series | Supported via official exam planner + student paper selection |
| Track classes, attendance, fees, homework, recordings | Supported in student / parent / campus portals |
| Practice MCQs offline / via Courso | Supported (`CoursoQuizBank`, `CoursoQuizService`) |
| Guaranteed grade / published pass-rate SLA | Homepage shows dynamic `success_rate` from published progress when available; otherwise marketing default — **not a contractual grade promise** |
| Detailed competency map per unit | **Not specified** in repository |

---

## 2. Complete Course Structure

Structure below is taken from **official Pearson examination unit titles** seeded in this project. Teaching at the college maps onto these units; classroom lesson titles may differ and are stored in live DB timetable rows (**not exported here**).

### 2.1 Qualifications (modules at pathway level)

1. **IGCSE** — modular and linear paper sets (May/June 2027 seed series).
2. **IAL (International A Level)** — unitised AS/A2-style papers (October 2026 and January / May–June 2027 seed series).

### 2.2 Official exam series available in the seed

| Series | Qualification | Exam count | Source |
| --- | --- | --- | --- |
| October 2026 IAL | IAL | 36 | [Pearson Edexcel International Advanced Levels October 2026 Examination Timetable - FINAL](https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-october2026-final.pdf) |
| January 2027 IAL | IAL | 82 | [Pearson Edexcel International Advanced Levels January 2027 Examination Timetable - FINAL](https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-january-2027-final.pdf) |
| May/June 2027 IAL | IAL | 92 | [Pearson Edexcel International Advanced Levels Summer 2027 Examination Timetable - FINAL](https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-summer-2027-final.pdf) |
| May/June 2027 IGCSE | IGCSE | 119 | [Pearson Edexcel International GCSE Summer 2027 Examination Timetable - FINAL](https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-Edexcel-International-GCSE/int-gcse-summer-2027-final.pdf) |

Sri Lanka local session starts (from seed metadata):

| Series | Timezone | Morning start | Afternoon start |
| --- | --- | --- | --- |
| October 2026 IAL | Asia/Colombo | 10:30:00 | 13:30:00 |
| January 2027 IAL | Asia/Colombo | 11:30:00 | 14:30:00 |
| May/June 2027 IAL | Asia/Colombo | 10:30:00 | 13:30:00 |
| May/June 2027 IGCSE | Asia/Colombo | 13:30:00 | 18:00:00 |

### 2.3 International A Level (IAL) — subjects and units

Unique units/papers across seeded IAL series (titles preserved from Pearson timetable data):

#### Business (`WBS`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WBS11` | Unit 1: Marketing and People | `01` | 120 |
| `WBS12` | Unit 2: Managing Business Activities | `01` | 120 |
| `WBS13` | Unit 3: Business Decisions and Strategy | `01` | 120 |
| `WBS14` | Unit 4: Global Business | `01` | 120 |
| `WBS11` | Unit 1: Marketing And People | `01` | 120 |
| `WBS13` | Unit 3: Business Decisions And Strategy | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Physics (`WPH`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WPH11` | Unit 1: Mechanics and Materials | `01` | 90 |
| `WPH12` | Unit 2: Waves and Electricity | `01` | 90 |
| `WPH13` | Unit 3: Practical Skills in Physics I | `01` | 80 |
| `WPH14` | Unit 4: Further Mechanics, Fields and Particles | `01` | 105 |
| `WPH15` | Unit 5: Thermodynamics, Radiation, Oscillations and Cosmology | `01` | 105 |
| `WPH16` | Unit 6: Practical Skills in Physics II | `01` | 80 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Mathematics (`WMA`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WMA11` | Pure Mathematics 1 | `01` | 90 |
| `WMA12` | Pure Mathematics 2 | `01` | 90 |
| `WMA13` | Pure Mathematics 3 | `01` | 90 |
| `WMA14` | Pure Mathematics 4 | `01` | 90 |
| `WMA11` | P1: Pure Mathematics 1 | `01` | 90 |
| `WMA12` | P2: Pure Mathematics 2 | `01` | 90 |
| `WMA13` | P3: Pure Mathematics 3 | `01` | 90 |
| `WMA14` | P4: Pure Mathematics 4 | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Chemistry (`WCH`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WCH11` | Unit 1: Structure, Bonding and Introduction to Organic Chemistry | `01` | 90 |
| `WCH12` | Unit 2: Energetics, Group Chemistry, Halogenoalkanes and Alcohol | `01` | 90 |
| `WCH13` | Unit 3: Practical Skills in Chemistry I | `01` | 80 |
| `WCH14` | Unit 4: Rates, Equilibria and Further Organic Chemistry | `01` | 105 |
| `WCH15` | Unit 5: Transition Metals and Organic Nitrogen Chemistry | `01` | 105 |
| `WCH16` | Unit 6: Practical Skills in Chemistry II | `01` | 80 |
| `WCH12` | Unit 2: Energetics, Group Chemistry, Halogenoalkanes and Alcohols | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Economics (`WEC`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WEC11` | Unit 1: Markets In Action | `01` | 105 |
| `WEC12` | Unit 2: Macroeconomic Performance and Policy | `01` | 105 |
| `WEC13` | Unit 3: Business Behaviour | `01` | 120 |
| `WEC14` | Unit 4: Developments In The Global Economy | `01` | 120 |
| `WEC11` | Unit 1: Markets in Action | `01` | 105 |
| `WEC14` | Unit 4: Developments in the Global Economy | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Biology (`WBI`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WBI11` | Unit 1: Molecules, Diet, Transport and Health | `01` | 90 |
| `WBI12` | Unit 2: Cells, Development, Biodiversity and Conservation | `01` | 90 |
| `WBI13` | Unit 3: Practical Skills in Biology I | `01` | 80 |
| `WBI14` | Unit 4: Energy, Environment, Microbiology and Immunity | `01` | 105 |
| `WBI15` | Unit 5: Respiration, Internal Environment, Coordination and Gene Technology | `01` | 105 |
| `WBI16` | Unit 6: Practical Skills in Biology II | `01` | 80 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Mathematics (`WME`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WME01` | Mechanics M1 | `01` | 90 |
| `WME02` | Mechanics M2 | `01` | 90 |
| `WME01` | M1: Mechanics 1 | `01` | 90 |
| `WME02` | M2: Mechanics 2 | `01` | 90 |
| `WME03` | M3: Mechanics 3 | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Mathematics (`WST`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WST01` | Statistics S1 | `01` | 90 |
| `WST02` | Statistics S2 | `01` | 90 |
| `WST01` | S1: Statistics 1 | `01` | 90 |
| `WST02` | S2: Statistics 2 | `01` | 90 |
| `WST03` | S3: Statistics 3 | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Accounting (`WAC`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WAC11` | Unit 1: The Accounting System and Costing | `01` | 180 |
| `WAC12` | Unit 2: Corporate and Management Accounting | `01` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### English Language (`WEN`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WEN01` | Unit 1: Language: Context and Identity | `01` | 105 |
| `WEN02` | Unit 2: Language in Transition | `01` | 105 |
| `WEN03` | Unit 3: Crafting Language (Writing) | `01` | 120 |
| `WEN04` | Unit 4: Investigating Language | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Geography (`WGE`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WGE01` | Unit 1: Global Challenges | `01` | 105 |
| `WGE02` | Unit 2: Geographical Investigations | `01` | 90 |
| `WGE03` | Unit 3: Contested Planet | `01` | 120 |
| `WGE04` | Unit 4: Researching Geography | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### French (`WFR`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WFR02` | Unit 2: Understanding and Written Response | `01` | 150 |
| `WFR04` | Unit 4: Research, Understanding and Written Response | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Psychology (`WPS`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WPS01` | Unit 1: Social and cognitive psychology | `01` | 90 |
| `WPS02` | Unit 2: Biological psychology, learning theories and development | `01` | 120 |
| `WPS03` | Unit 3: Applications of psychology | `01` | 90 |
| `WPS04` | Unit 4: Clinical psychology and psychological skills | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Arabic (`WAA`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WAA01` | Unit 1: Understanding and Written Response | `01` | 150 |
| `WAA02` | Unit 2: Writing and Research | `01` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### English Literature (`WET`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WET01` | Unit 1: Post-2000 Poetry and Prose | `01` | 120 |
| `WET02` | Unit 2: Drama | `01` | 120 |
| `WET03` | Unit 3: Poetry and Prose | `01` | 120 |
| `WET04` | Unit 4: Shakespeare and Pre-1900 Poetry | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### History (`WHI`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WHI01` | Unit 1 - Option 1A: France in Revolution, 1774-99 | `1A` | 120 |
| `WHI01` | Unit 1 - Option 1B: Russia in Revolution, 1881-1917 | `1B` | 120 |
| `WHI01` | Unit 1 - Option 1C: Germany, 1918-45 | `1C` | 120 |
| `WHI01` | Unit 1 - Option 1D: Britain, 1964-90 | `1D` | 120 |
| `WHI02` | Unit 2 - Option 1A: India, 1857-1948: The Raj to Partition | `1A` | 120 |
| `WHI02` | Unit 2 - Option 1B: China, 1900-76 | `1B` | 120 |
| `WHI02` | Unit 2 - Option 1C: Russia, 1917-91: From Lenin to Yeltsin | `1C` | 120 |
| `WHI02` | Unit 2 - Option 1D: South Africa, 1948-2014 | `1D` | 120 |
| `WHI03` | Unit 3 - Option 1A: The USA, Independence to Civil War, 1763-1865 | `1A` | 120 |
| `WHI03` | Unit 3 - Option 1B: The British Experience of Warfare, 1803-1945 | `1B` | 120 |
| `WHI03` | Unit 3 - Option 1C: Germany: United, Divided and Reunited, 1870-1990 | `1C` | 120 |
| `WHI03` | Unit 3 - Option 1D: Civil Rights and Race Relations in the USA, 1865-2009 | `1D` | 120 |
| `WHI04` | Unit 4 - Option 1A: The Making of Modern Europe, 1805-71 | `1A` | 120 |
| `WHI04` | Unit 4 - Option 1B: The World in Crisis, 1879-1945 | `1B` | 120 |
| `WHI04` | Unit 4 - Option 1C: The World Divided: Superpower Relations, 1943-90 | `1C` | 120 |
| `WHI04` | Unit 4 - Option 1D: The Cold War and Hot War in Asia, 1945-90 | `1D` | 120 |
| `WHI03` | Unit 3 - Option 1C: Germany: United, Divided and Reunited,
1870-1990 | `1C` | 120 |
| `WHI03` | Unit 3 - Option 1D: Civil Rights and Race Relations in the USA,
1865-2009 | `1D` | 120 |
| `WHI04` | Unit 4 - Option 1C: The World Divided: Superpower Relations, 
1943-90 | `1C` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### German (`WGN`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WGN02` | Unit 2: Understanding and Written Response | `01` | 150 |
| `WGN04` | Unit 4: Research, Understanding and Written Response | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Mathematics (`WFM`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WFM01` | FP1: Further Pure Mathematics 1 | `01` | 90 |
| `WFM02` | FP2: Further Pure Mathematics 2 | `01` | 90 |
| `WFM03` | FP3: Further Pure Mathematics 3 | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Spanish (`WSP`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WSP02` | Unit 2: Understanding and Written Response | `01` | 150 |
| `WSP04` | Unit 4: Research, Understanding and Written Response | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Mathematics (`WDM`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WDM11` | D1: Decision Mathematics 1 | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Information Technology (IT) (`WIT`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WIT11` | Unit 1 | `01` | 120 |
| `WIT12` | Unit 2 | `01` | 180 |
| `WIT13` | Unit 3 | `01` | 120 |
| `WIT14` | Unit 4 | `01` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Greek (`WGK`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WGK01` | Unit 1: Understanding and Written Response | `01` | 150 |
| `WGK02` | Unit 2: Writing and Research | `01` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Computer Science (`WCP`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `WCP01` | Unit 1: Principles of Computer Science | `01` | 90 |
| `WCP02` | Unit 2: Practical Programming and Problem-solving | `01` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

#### Law (`YLA`)

| Unit / paper code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `YLA1` | Paper 1: Underlying Principles of Law and the English Legal System | `01` | 180 |
| `YLA1` | Paper 2: The Law in Action | `02` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository (live lessons appear on the college timetable).

### 2.4 IGCSE — subjects and papers

Unique papers/units across the seeded IGCSE May/June 2027 series:

#### Computer Science (`4CP0`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4CP0` | Paper 1: Principles of Computer Science | `01` | 120 |
| `4CP0` | Paper 2: Application of Computational Thinking | `02` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Greek (First Language) (`4GK1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4GK1` | Paper 1: Reading, Summary and Grammar | `01` | 135 |
| `4GK1` | Paper 2: Writing | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Human Biology (`4HB1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4HB1` | Paper 01 | `01` | 105 |
| `4HB1` | Paper 02 | `02` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Arabic (`4AA1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4AA1` | Paper 1: Reading, Summary and Grammar | `01` | 135 |
| `4AA1` | Paper 2: Writing | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### German (`4GN1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4GN1` | Paper 1: Listening | `01` | 35 |
| `4GN1` | Paper 2: Reading and Writing | `02` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Biology (Linear) (`4BI1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4BI1` | Paper: 1B | `1B` | 120 |
| `4BI1` | Paper: 2B | `2B` | 75 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Double Award) (Linear) (`4SD0`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4SD0` | Paper: 1B | `1B` | 120 |
| `4SD0` | Paper: 1C | `1C` | 120 |
| `4SD0` | Paper: 1P | `1P` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Biology (Modular) (`4WBI1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WBI1` | Unit 1 | `1B` | 100 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Double Award) (Modular) (`4WSD1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WSD1` | Unit 1 | `1B` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English as a Second Language (`4WES1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WES1` | Unit 1: Reading | `01` | 60 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English as a Second Language (`4WES2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WES2` | Unit 2: Listening | `01` | 45 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English Literature (Linear) (`4ET1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4ET1` | Paper 1: Poetry and Modern Prose | `01` | 120 |
| `4ET1` | Paper 2: Modern Drama and Literary Heritage Texts | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English Literature (Modular) (`4WET1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WET1` | Unit 1: Poetry and Modern Prose | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Business (`4BS1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4BS1` | Paper 1: Investigating small businesses | `01` | 90 |
| `4BS1` | Paper 2: Investigating large businesses | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Information And Communication Technology (ICT) (`4IT1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4IT1` | Paper 1: Written Paper | `01` | 90 |
| `4IT1` | Paper 2: Practical Exam | `02` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Religious Studies (Linear) (`4RS1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4RS1` | Paper 1: Beliefs and Values | `01` | 105 |
| `4RS1` | Paper 2A: Buddhism | `2A` | 90 |
| `4RS1` | Paper 2B: Christianity | `2B` | 90 |
| `4RS1` | Paper 2C: Hinduism | `2C` | 90 |
| `4RS1` | Paper 2D: Islam | `2D` | 90 |
| `4RS1` | Paper 2E: Judaism | `2E` | 90 |
| `4RS1` | Paper 2F: Sikhism | `2F` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Religious Studies (Modular) (`4WRS1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WRS1` | Unit 1: Beliefs and Values | `01` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Economics (Linear) (`4EC1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4EC1` | Paper 1: Microeconomics and Business Economics | `01` | 90 |
| `4EC1` | Paper 2: Macroeconomics and the Global Economy | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Economics (Modular) (`4WEC1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WEC1` | Unit 1: Microeconomics and Business Economics | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Mathematics A (Linear) (`4MA1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4MA1` | Paper 1F Foundation Tier | `1F` | 120 |
| `4MA1` | Paper 1H Higher Tier | `1H` | 120 |
| `4MA1` | Paper 2F Foundation Tier | `2F` | 120 |
| `4MA1` | Paper 2H Higher Tier | `2H` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Mathematics A (Modular) (`4WM1F`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WM1F` | Unit 1F Foundation Tier | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Mathematics A (Modular) (`4WM1H`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WM1H` | Unit 1H Higher Tier | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Bangla (`4BA0`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4BA0` | Paper 1: Reading, Writing and Translation | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Geography (Linear) (`4GE1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4GE1` | Paper 1: Physical geography | `01` | 70 |
| `4GE1` | Paper 2: Human geography | `02` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Geography (Modular) (`4WGE1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WGE1` | Unit 1: Physical geography | `01` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Chemistry (Linear) (`4CH1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4CH1` | Paper: 1C | `1C` | 120 |
| `4CH1` | Paper: 2C | `2C` | 75 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Chemistry (Modular) (`4WCH1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WCH1` | Unit 1 | `1C` | 100 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Double Award) (Modular) (`4WSD3`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WSD3` | Unit 3 | `1C` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English Literature (Modular) (`4WET2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WET2` | Unit 2: Modern Drama and Literary Heritage Texts | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Further Pure Mathematics (`4PM1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4PM1` | Paper 1 | `01` | 120 |
| `4PM1` | Paper 2 | `02` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Accounting (Linear) (`4AC1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4AC1` | Paper 1: Introduction to Bookkeeping and Accounting | `01` | 120 |
| `4AC1` | Paper 2: Financial Statements | `02` | 75 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### History (Linear) (`4HI1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4HI1` | Paper 1: Depth Studies | `01` | 90 |
| `4HI1` | Paper 2: Investigation and Breadth Studies | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Accounting (Modular) (`4WAC1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WAC1` | Unit 1: Introduction to Bookkeeping and Accounting | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### History (Modular) (`4WHI1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WHI1` | Unit 1: Depth Studies | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Religious Studies (Modular) (`4WRS2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WRS2` | Unit 2A: Buddhism | `1A` | 90 |
| `4WRS2` | Unit 2B: Christianity | `1B` | 90 |
| `4WRS2` | Unit 2C: Hinduism | `1C` | 90 |
| `4WRS2` | Unit 2D: Islam | `1D` | 90 |
| `4WRS2` | Unit 2E: Judaism | `1E` | 90 |
| `4WRS2` | Unit 2F: Sikhism | `1F` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English Language A (Linear) (`4EA1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4EA1` | Paper 1: Non-fiction Texts and Transactional Writing | `01` | 135 |
| `4EA1` | Paper 2: Poetry and Prose Texts and Imaginative Writing | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English Language B (`4EB1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4EB1` | Paper 1 | `01` | 180 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English Language A (Modular) (`4WEA1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WEA1` | Unit 1: Non-fiction Texts and Transactional Writing | `01` | 135 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Commerce (Linear) (`4CM1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4CM1` | Paper 1: Commercial operations and associated risks | `01` | 90 |
| `4CM1` | Paper 2: Facilitating commercial operations | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Commerce (Modular) (`4WCM1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WCM1` | Unit 1: Commercial operations and associated risks | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Physics (Linear) (`4PH1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4PH1` | Paper: 1P | `1P` | 120 |
| `4PH1` | Paper: 2P | `2P` | 75 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Physics (Modular) (`4WPH1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WPH1` | Unit 1 | `1P` | 100 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Double Award) (Modular) (`4WSD5`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WSD5` | Unit 5 | `1P` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Economics (Modular) (`4WEC2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WEC2` | Unit 2: Macroeconomics and the Global Economy | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### French (`4FR1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4FR1` | Paper 1: Listening | `01` | 35 |
| `4FR1` | Paper 2: Reading and Writing | `02` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Islamic Studies (`4IS1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4IS1` | Paper 1: Islamic Studies | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Geography (Modular) (`4WGE2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WGE2` | Unit 2: Human geography | `01` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Mathematics A (Modular) (`4WM2F`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WM2F` | Unit 2F Foundation Tier | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Mathematics A (Modular) (`4WM2H`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WM2H` | Unit 2H Higher Tier | `01` | 120 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### History (Modular) (`4WHI2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WHI2` | Unit 2: Investigation and Breadth Studies | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Bangladesh Studies (`4BN1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4BN1` | Paper 1: History and culture of Bangladesh | `01` | 90 |
| `4BN1` | Paper 2: The landscape, people and economy of Bangladesh | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Pakistan Studies (`4PA1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4PA1` | Paper 1: History and culture of Pakistan | `01` | 90 |
| `4PA1` | Paper 2: The landscape, people and economy of Pakistan | `02` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Sinhala (`4SI1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4SI1` | Paper 1: Reading, Writing and Translation | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Swahili (`4SW1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4SW1` | Paper 1: Reading, Writing and Translation | `01` | 135 |
| `4SW1` | Paper 2: Listening | `02` | 35 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Accounting (Modular) (`4WAC2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WAC2` | Unit 2: Financial Statements | `01` | 75 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Tamil (`4TA1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4TA1` | Paper 1: Reading, Writing and Translation | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English as a Second Language (`4WES3`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WES3` | Unit 3: Writing | `01` | 75 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Chinese (`4CN1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4CN1` | Paper 1: Listening | `01` | 35 |
| `4CN1` | Paper 2: Reading and Writing | `02` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Mathematics B (`4MB1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4MB1` | Paper 1 | `01` | 90 |
| `4MB1` | Paper 2 | `02` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### English Language A (Modular) (`4WEA2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WEA2` | Unit 2: Poetry and Prose Texts and Imaginative Writing | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Global Citizenship (`4GL1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4GL1` | Paper 1 | `01` | 150 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Spanish (`4SP1`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4SP1` | Paper 1: Listening | `01` | 35 |
| `4SP1` | Paper 2: Reading and Writing | `02` | 105 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Single Award) (`4SS0`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4SS0` | Paper: 1B | `1B` | 70 |
| `4SS0` | Paper: 1C | `1C` | 70 |
| `4SS0` | Paper: 1P | `1P` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Biology (Modular) (`4WBI2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WBI2` | Unit 2 | `1B` | 100 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Double Award) (Modular) (`4WSD2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WSD2` | Unit 2 | `1B` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Commerce (Modular) (`4WCM2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WCM2` | Unit 2: Facilitating commercial operations | `01` | 90 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Chemistry (Modular) (`4WCH2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WCH2` | Unit 2 | `1C` | 100 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Double Award) (Modular) (`4WSD4`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WSD4` | Unit 4 | `1C` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Physics (Modular) (`4WPH2`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WPH2` | Unit 2 | `1P` | 100 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

#### Science (Double Award) (Modular) (`4WSD6`)

| Unit / subject code | Title | Paper code | Duration (minutes) |
| --- | --- | --- | --- |
| `4WSD6` | Unit 6 | `1P` | 70 |

**Learning objectives for this subject (college-authored):** Not specified.

**Lesson-by-lesson breakdown:** Not specified in repository.

---

## 3. Detailed Content

### 3.1 Important concepts and definitions (from available materials)

| Term | Definition / meaning in this pathway |
| --- | --- |
| **Pearson Edexcel** | Awarding organisation whose IGCSE and International A Level qualifications the college teaches toward |
| **IGCSE** | International General Certificate of Secondary Education |
| **IAL** | International Advanced Level (unitised International A Level) |
| **Unit code** | Pearson paper/unit identifier (e.g. `WMA11`, `4BI1`) |
| **Paper code** | Component within a specification (e.g. `01`, `1H`, `2B`) |
| **Linear** | Assessment model with end-of-course papers (IGCSE linear codes such as `4MA1`) |
| **Modular** | Assessment model with separate unit codes (e.g. `4WBI1` / `4WBI2`) |
| **Official exam series** | Imported Pearson timetable batch (e.g. October 2026 IAL) stored in `exam_series` / seed JSON |
| **Courso / Talk with AI** | Student study assistant + practice quiz feature on the student dashboard |
| **Study days / study hour** | Courso learner preference for revision density (DB: learner profile) |

### 3.2 Topic explanations available in-repo

Full syllabus prose for every Pearson unit is **not** embedded in this codebase. The richest topic-level teaching content available here is the **offline Courso practice bank** (`src/Services/CoursoQuizBank.php`), summarised below with explanations and local examples.

#### Practice topic 1: Mathematics / Pure / IAL (foundation)

**Question:** A taxi charges a Rs 200 flag fall plus Rs 40 per kilometre. What is the cost of a 7 km journey?

**Correct answer:** Rs 480

**Explanation:** Cost = 200 + 40×7 = 200 + 280 = 480. Common mistake: forgetting the flag fall (Rs 280) or multiplying 200 by 7.

**Practical example:** Kandy three-wheelers often use a standing charge plus a per-km rate — same linear model as y = mx + c.

**Key points to remember**

- See explanation above; fuller syllabus notes: **Not specified** in repository.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Cost = 200 + 40×7 = 200 + 280 = 480.

#### Practice topic 2: Mathematics / Algebra (core)

**Question:** Solve 3(x − 2) = 2x + 5.

**Correct answer:** x = 11

**Explanation:** Expand: 3x − 6 = 2x + 5. Subtract 2x: x − 6 = 5. Add 6: x = 11. Check: 3(9) = 27 and 22+5 = 27.

**Practical example:** If three identical revision packs cost the same as two packs plus a Rs 5 booklet, you are solving this kind of equation.

**Key points to remember**

- See explanation above; fuller syllabus notes: **Not specified** in repository.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Expand: 3x − 6 = 2x + 5.

#### Practice topic 3: Mathematics / Quadratic (stretch)

**Question:** The graph of y = x² − 6x + 8 crosses the x-axis at

**Correct answer:** x = 2 and x = 4

**Explanation:** Factor: (x−2)(x−4)=0 so roots are 2 and 4. Completing the square: (x−3)² − 1 = 0.

**Practical example:** Projectile height against time is a downward parabola; the roots are launch and landing times.

**Key points to remember**

- Factor or complete the square to find roots.
- Roots are x-intercepts of the parabola.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Factor: (x−2)(x−4)=0 so roots are 2 and 4.

#### Practice topic 4: Physics (foundation)

**Question:** A bus travels 12 km in 15 minutes. Its average speed is

**Correct answer:** 48 km/h

**Explanation:** 15 minutes = 0.25 h. Speed = distance/time = 12 / 0.25 = 48 km/h. Do not divide by 15 minutes as if it were hours.

**Practical example:** The Kandy–Peradeniya run is often quoted in minutes; convert to hours before using v = s/t.

**Key points to remember**

- See explanation above; fuller syllabus notes: **Not specified** in repository.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: 15 minutes = 0.

#### Practice topic 5: Physics (core)

**Question:** Which pair is a Newton third-law interaction while you sit on a chair?

**Correct answer:** The Earth pulling you down and you pulling the Earth up

**Explanation:** Third-law pairs act on different objects. Weight (Earth on you) pairs with you attracting the Earth. The normal force is a different interaction with the chair.

**Practical example:** When you jump, you push the floor down; the floor pushes you up — that pair is why you leave the ground.

**Key points to remember**

- Newton-3 pairs act on **different** objects.
- Weight and normal force are **not** a third-law pair.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Third-law pairs act on different objects.

#### Practice topic 6: Chemistry (core)

**Question:** Which is the best description of a covalent bond?

**Correct answer:** Shared pair of electrons between atoms

**Explanation:** Covalent = shared pair. Ionic is transfer/attraction of ions. Metallic is delocalised electrons. Mixing these up is the most common exam slip.

**Practical example:** The O–H bonds in water are covalent; that is why water is a molecule, not a lattice of H⁺ and O²⁻ in the liquid.

**Key points to remember**

- Covalent = share; ionic = transfer/attract; metallic = delocalised sea.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Covalent = shared pair.

#### Practice topic 7: Biology (core)

**Question:** In the human breathing system, gas exchange happens mainly in the

**Correct answer:** alveoli

**Explanation:** Alveoli give a large surface area, thin walls, and a moist surface next to capillaries. The diaphragm is a muscle that changes volume, not the exchange surface.

**Practical example:** Asthma narrows airways before air reaches alveoli, so less oxygen reaches blood even if you are trying to breathe harder.

**Key points to remember**

- See explanation above; fuller syllabus notes: **Not specified** in repository.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Alveoli give a large surface area, thin walls, and a moist surface next to capillaries.

#### Practice topic 8: ICT / Computing (foundation)

**Question:** Which is the most appropriate backup for a student laptop before an exam week?

**Correct answer:** Cloud or an external drive kept in a different place

**Explanation:** A backup must survive loss or failure of the original device. Same-disk copies and Recycle Bin fail together with the laptop.

**Practical example:** If a Kandy boarding-house laptop is stolen, a Google Drive or USB at home still holds the coursework.

**Key points to remember**

- Backups must be off the same failing device.
- Password hashing is one-way; it is not reversible encryption.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: A backup must survive loss or failure of the original device.

#### Practice topic 9: ICT / Computing (stretch)

**Question:** A hashing algorithm is used when storing passwords mainly because

**Correct answer:** It produces a one-way value that can be compared without storing the password

**Explanation:** Hashing is one-way. On login, the typed password is hashed and compared. Encryption would be reversible and is the wrong model for password storage.

**Practical example:** This college portal stores password hashes, not the password itself — staff cannot “look up” what you typed.

**Key points to remember**

- See explanation above; fuller syllabus notes: **Not specified** in repository.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Hashing is one-way.

#### Practice topic 10: English / Literature (core)

**Question:** In analytical writing, the most useful next sentence after a quotation is usually

**Correct answer:** A comment on method and effect (how the language works)

**Explanation:** PEE/PEEL: after Evidence, Explain the writer’s method and the effect on the reader. Plot retell and extra quotes without analysis score lower.

**Practical example:** If a poem uses a storm image, say what feeling it creates — do not only repeat “there is a storm”.

**Key points to remember**

- After a quote: analyse method + effect (PEE/PEEL), do not only retell plot.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: PEE/PEEL: after Evidence, Explain the writer’s method and the effect on the reader.

#### Practice topic 11: Economics (core)

**Question:** A rise in the market price of rice, other things equal, is most likely to

**Correct answer:** Decrease quantity demanded

**Explanation:** Movement along the demand curve: higher price → lower quantity demanded. A shift needs a change in income, tastes, or related goods — not the good’s own price.

**Practical example:** When imported rice becomes dearer in Colombo, households buy less of that grade or switch to another staple.

**Key points to remember**

- Own-price change → movement **along** demand, not a demand-curve shift.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Movement along the demand curve: higher price → lower quantity demanded.

#### Practice topic 12: Accounting (foundation)

**Question:** The accounting equation is

**Correct answer:** Assets = Liabilities + Capital

**Explanation:** Assets are financed by what the business owes (liabilities) and what the owner has invested (capital).

**Practical example:** A shop van (asset) might be partly a bank loan (liability) and partly the owner’s savings (capital).

**Key points to remember**

- Always keep Assets = Liabilities + Capital.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Assets are financed by what the business owes (liabilities) and what the owner has invested (capital).

#### Practice topic 13: Business / Commerce (core)

**Question:** Market research that uses existing published data is called

**Correct answer:** Secondary research

**Explanation:** Secondary = already collected (census, news, past sales). Primary = you collect it (surveys, interviews). Sampling is a method inside primary research.

**Practical example:** A Kandy café checking TripAdvisor reviews before changing the menu is using secondary research.

**Key points to remember**

- See explanation above; fuller syllabus notes: **Not specified** in repository.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: Secondary = already collected (census, news, past sales).

#### Practice topic 14: Study / Exam / Revision (foundation)

**Question:** The most effective last 20 minutes before a paper is usually

**Correct answer:** A short recap of formulas/quotes you already practised, then calm breathing

**Explanation:** New material right before an exam raises anxiety and rarely sticks. Retrieval of practised items plus settling your nerves is higher yield.

**Practical example:** Athletes warm up skills they already have; they do not learn a new serve in the tunnel.

**Key points to remember**

- Last minutes: retrieve practised material; avoid brand-new topics.

**Common mistakes / misconceptions**

- Highlighted in bank explanation: New material right before an exam raises anxiety and rarely sticks.

### 3.3 Unit titles as content anchors

For each IAL/IGCSE unit listed in §2, Pearson’s official specification (external PDF) is the authoritative detailed content. This repository stores **unit titles, codes, durations, and exam dates**, not full specification text.

Example content anchors (IAL sciences & maths):

| Subject | Example unit titles (from seed) |
| --- | --- |
| Biology | Molecules, Diet, Transport and Health; Cells, Development, Biodiversity and Conservation; Practical Skills I & II; Energy, Environment, Microbiology and Immunity; Respiration, Internal Environment, Coordination and Gene Technology |
| Chemistry | Structure, Bonding and Introduction to Organic Chemistry; Energetics, Group Chemistry, Halogenoalkanes and Alcohol; Practical Skills I & II; Rates, Equilibria and Further Organic Chemistry; Transition Metals and Organic Nitrogen Chemistry |
| Physics | Mechanics and Materials; Waves and Electricity; Practical Skills I & II; Further Mechanics, Fields and Particles; Thermodynamics, Radiation, Oscillations and Cosmology |
| Mathematics | Pure Mathematics 1–4; Mechanics M1–M3; Statistics S1–S3; Further Pure FP1–FP3; Decision Mathematics D1 |
| Business | Marketing and People; Managing Business Activities; Business Decisions and Strategy; Global Business |
| Economics | Markets In Action; Macroeconomic Performance and Policy; Business Behaviour; Developments In The Global Economy |
| Accounting | The Accounting System and Costing; Corporate and Management Accounting |

Deep prose for each bullet: **Not specified** (use Pearson specifications).

---

## 4. Practical Work

### 4.1 Exercises (available)

Built-in Courso MCQ bank (see §3.2) — used when AI quiz generation is unavailable.

Difficulty labels used in code: `foundation`, `core`, `stretch`.

### 4.2 Assignments

| Item | Status |
| --- | --- |
| Homework upload by teachers (`campus/homework.php`) | Supported operationally |
| Student submission (`student/homework_submit.php`) | Supported operationally |
| Standard written assignment briefs per unit | **Not specified** in repository |

### 4.3 Projects

**Not specified** as formal course projects in repository content. IAL English / Geography / language units that Pearson labels as research/writing components appear only as **exam unit titles** (e.g. Investigating Language; Researching Geography; Writing and Research).

### 4.4 Practical examples

Courso bank uses Sri Lanka–local examples (Kandy three-wheelers, Kandy–Peradeniya journey, Colombo rice price, TripAdvisor café research). See §3.2.

### 4.5 Step-by-step: start a practice quiz (student portal)

```text
1. Sign in at https://edexcel.college (student login).
2. Open Student Dashboard → Courso / Talk with AI tab.
3. Ask for a quiz, or use practice links (#practice).
4. If AI keys are unavailable, the system falls back to CoursoQuizBank items.
5. Review explanations after each item.
```

### 4.6 Step-by-step: pick official exam papers

```text
1. Staff/admin import or load an official Pearson series (campus official exams).
2. Student opens Exams on the student portal.
3. Select papers/units for the target series.
4. Use countdown / study-day tools as offered by the planner.
```

Exact UI labels may vary with layout; implementation lives under campus official exam pages and student exam features.

---

## 5. Resources

### 5.1 Recommended books

**Not specified** in repository.

### 5.2 Documentation (project / Pearson)

| Resource | Link / path |
| --- | --- |
| College system overview | `SYSTEM.md` |
| Deployment notes | `DEPLOYMENT_NOTES.md` |
| WhatsApp Cloud API notes | `WHATSAPP_CLOUD_API.md` |
| Official exams seed | `database/seeds/official_exams.json` |
| October 2026 IAL timetable (Pearson) | https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-october2026-final.pdf |
| January 2027 IAL timetable (Pearson) | https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-january-2027-final.pdf |
| October 2026 IAL source PDF | https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-october2026-final.pdf |
| January 2027 IAL source PDF | https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-january-2027-final.pdf |
| May/June 2027 IAL source PDF | https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-summer-2027-final.pdf |
| May/June 2027 IGCSE source PDF | https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-Edexcel-International-GCSE/int-gcse-summer-2027-final.pdf |

### 5.3 Articles

**Not specified** in repository.

### 5.4 Videos

| Resource | Status |
| --- | --- |
| Class recordings (Bunny Stream) | Available to enrolled/paid students via student portal |
| Public curated YouTube playlist | **Not specified** |

### 5.5 Tools / software

| Tool | Role |
| --- | --- |
| https://edexcel.college | College portal (timetable, classes, fees, exams, AI) |
| LiveKit | Live / hybrid online classroom |
| Bunny Stream | Recording delivery |
| OnePay | Online fee payment |
| WhatsApp (Cloud API / Evolution) | Bot, reminders, OTP |
| Courso / Talk with AI | Study chat + practice quizzes (Groq/Gemini when configured) |
| Pearson qualifications site | Official specs & timetables |

### 5.6 Useful links

- College homepage: https://edexcel.college
- Student dashboard: https://edexcel.college/student/dashboard.php
- Teachers directory: https://edexcel.college/teachers/index.php
- Pearson qualifications: https://qualifications.pearson.com/
- Contact email: info@edexcel.college

---

## 6. Assessment

### 6.1 Quizzes

| Type | Details |
| --- | --- |
| Courso practice MCQs | Offline bank + optional AI-generated Pearson-style items |
| In-class / online question banks | `online_question_banks` (teacher-owned; content not in seed) |
| Formal college weekly quiz schedule | **Not specified** |

### 6.2 Assignments

Teacher-assigned homework with optional student submission and marking workflow. Standard rubrics: **Not specified**.

### 6.3 Projects

**Not specified** as internal graded projects beyond Pearson unit assessments.

### 6.4 Exams / tests

Primary high-stakes assessment: **Pearson Edexcel external examinations** for the units/papers listed in §2 and Appendix A.

College-side progress scores may be stored in `student_progress` (used for homepage success-rate averages when published).

### 6.5 Evaluation criteria

| Assessment | Criteria in repository |
| --- | --- |
| Pearson external exams | Follow Pearson mark schemes / grade boundaries (external; **not stored here**) |
| Courso MCQ | Correct choice index + explanation in bank |
| Homework | Teacher mark/done status via HomeworkSubmissionService |
| Attendance | present / late / other statuses in `student_attendance` |
| College internal grading policy document | **Not specified** |

---

## 7. Learning Roadmap

### 7.1 Recommended order of study (pathway level)

```text
Beginner / secondary foundation
  → IGCSE subject papers (Mathematics, Sciences, English, Business, ICT, …)
  → Sit IGCSE series (e.g. May/June)

Intermediate / advanced
  → IAL AS-style units (typically Unit 1–2 / P1–P2 / S1 / M1 as applicable)
  → IAL A2-style units (Unit 3–6 / P3–P4 / further options)
  → Sit IAL series (October / January / May–June as offered)
```

Exact unit combinations for a full A Level award follow Pearson rules for each subject; college-specific combination advice: **Not specified** beyond exam planner selection.

### 7.2 Dependencies between topics

| Dependency | Notes |
| --- | --- |
| IGCSE → IAL | Logical progression; not enforced in software |
| Maths Pure before Further Pure / Decision | Implied by Pearson structure; college gate: **Not specified** |
| Science Units 1–2 before practical / later units | Suggested by unit numbering; teaching order: **Not specified** |
| Fee unlock → live lesson / recording | Operational dependency in portal |
| Device confirmation → some student media features | Operational dependency |

### 7.3 Suggested progression beginner → advanced

| Stage | Focus | Portal support |
| --- | --- | --- |
| 1 | Enrol, set WhatsApp, join classes | Student register / dashboard |
| 2 | Attend lessons + homework | Timetable, homework, attendance |
| 3 | Practice with Courso + past-paper style practice | Courso practice |
| 4 | Select official papers; plan study days | Official exam planner |
| 5 | Sit Pearson series; review recordings | Exams + recordings |

---

## 8. Cheat Sheets / Quick Reference

### 8.1 Qualification codes (defaults)

```text
IGCSE  — International GCSE
IAL    — International Advanced Level
```

### 8.2 Common IAL subject code prefixes (from seed)

| Prefix | Subject area |
| --- | --- |
| WAC | Accounting |
| WBI | Biology |
| WBS | Business |
| WCH | Chemistry |
| WEC | Economics |
| WEN | English Language |
| WET | English Literature |
| WPH | Physics |
| WMA | Pure Mathematics |
| WME | Mechanics |
| WST | Statistics |
| WFM | Further Pure Mathematics |
| WDM | Decision Mathematics |
| WGE | Geography |
| WHI | History |
| WFR / WGN | French / German |
| WAA | Arabic |

### 8.3 Common IGCSE code patterns (from seed)

```text
4xx1 / 4xx0     Linear International GCSE specs (e.g. 4MA1, 4BI1, 4IT1)
4Wxxx          Modular International GCSE units (e.g. 4WBI1, 4WCH2)
1F / 1H / 2F / 2H   Mathematics A foundation/higher papers
1B / 1C / 1P        Biology / Chemistry / Physics paper tags
```

### 8.4 Core formulas / definitions (from Courso bank)

**Linear cost model**

```text
total = fixed + (rate × quantity)
# Example: 200 + 40×7 = 480
```

**Average speed**

```text
v = s / t
# Convert minutes to hours first: 15 min = 0.25 h
```

**Accounting equation**

```text
Assets = Liabilities + Capital
```

**Quadratic roots (example)**

```text
y = x² − 6x + 8 = (x − 2)(x − 4)
# roots: x = 2, x = 4
```

### 8.5 Study / exam short summary

- Prefer retrieval of practised material in the last 20 minutes before a paper.
- Demand: own-price change moves **along** the curve; non-price factors **shift** it.
- Newton 3: pairs on different bodies.
- Covalent ≠ ionic ≠ metallic.
- Analytical English: quote → method/effect, not plot dump.

### 8.6 Portal quick commands (WhatsApp bot — high level)

Bot supports menu-style requests for today’s classes, tomorrow’s classes, enrolled classes, teachers, weekly timetable, and admissions (see `README.md` / WhatsApp docs). Exact phrase list: consult live bot menu — full script dump **Not specified** here.

---

## 9. Final Project / Capstone

| Field | Value |
| --- | --- |
| Capstone project title | **Not specified** (no formal capstone in repository) |
| Requirements | **Not specified** |
| Recommended approach | Sit chosen Pearson units; use planner + recordings + Courso practice |
| Expected deliverables | Successful completion of selected Pearson papers / college progress records as applicable |
| Evaluation criteria | Pearson external marking; college internal policy **Not specified** |

Closest analogues in Pearson unit titles (research/writing style assessments):

- IAL English Language — Unit 4: Investigating Language
- IAL Geography — Unit 4: Researching Geography
- IAL Arabic — Unit 2: Writing and Research
- IAL French/German — Unit 4: Research, Understanding and Written Response

---

## 10. FAQ

### What course is documented here?

The **Pearson Edexcel IGCSE & International A Level pathway** as offered/supported by Edexcel College, using all course-related data available in this project.

### Is this a single packaged online course with video lessons for every chapter?

No. The system documentation states it is **not** a generic Moodle-style LMS. Content is delivered via live/hybrid classes, materials, homework, recordings, and Pearson exams.

### Where do unit titles and exam dates come from?

From `database/seeds/official_exams.json`, sourced from Pearson Edexcel official examination timetables (URLs in §5 and Appendix A).

### Are Sri Lanka start times the same as UK Pearson sessions?

Seed rows store local `Asia/Colombo` start times and notes such as `Pearson session: Morning | SL start 10:30`. Always confirm against the current Pearson PDF and college notices.

### How do I practise if AI chat is offline?

Courso falls back to the built-in `CoursoQuizBank` MCQs.

### What is the default fee?

Rs 500 per student per class by default; overridable per lesson.

### Where is the college?

No 83 Katugatota Road, Kandy, Sri Lanka.

### How do parents follow progress?

Parent portal (`/parent/login.php`) for schedule, fees, and related snapshots; WhatsApp digests may also apply when configured.

### Can you give full Pearson specification text here?

No — full specifications are Pearson copyrighted documents. This file lists unit titles/codes/dates present in the college seed and points to official PDFs.

### Why are many learning-objective fields “Not specified”?

Because this repository stores operational college software and exam timetable seeds, not a complete authored curriculum manuscript for every topic.

---

## 11. Appendix A — Full Official Exam Series Timetables

Complete paper-by-paper listing from the seed file (all series).

### October 2026 IAL

- **Qualification:** IAL
- **Year / session:** 2026 / October
- **Exam count:** 36
- **Timezone:** Asia/Colombo
- **Morning start:** 10:30:00
- **Afternoon start:** 13:30:00
- **Source:** Pearson Edexcel International Advanced Levels October 2026 Examination Timetable - FINAL
- **Source URL:** https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-october2026-final.pdf

| Date | Session | Subject | Subject code | Unit code | Paper | Unit / paper title | Duration (min) | Local start | Local end | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2026-10-08 | Morning | Business | `WBS` | `WBS11` | `01` | Unit 1: Marketing and People | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-08 | Afternoon | Physics | `WPH` | `WPH11` | `01` | Unit 1: Mechanics and Materials | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-09 | Morning | Mathematics | `WMA` | `WMA11` | `01` | Pure Mathematics 1 | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-09 | Afternoon | Chemistry | `WCH` | `WCH11` | `01` | Unit 1: Structure, Bonding and Introduction to Organic Chemistry | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-12 | Morning | Economics | `WEC` | `WEC11` | `01` | Unit 1: Markets In Action | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-12 | Afternoon | Biology | `WBI` | `WBI11` | `01` | Unit 1: Molecules, Diet, Transport and Health | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-13 | Morning | Chemistry | `WCH` | `WCH12` | `01` | Unit 2: Energetics, Group Chemistry, Halogenoalkanes and Alcohol | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-13 | Afternoon | Mathematics | `WME` | `WME01` | `01` | Mechanics M1 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-14 | Morning | Business | `WBS` | `WBS12` | `01` | Unit 2: Managing Business Activities | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-14 | Afternoon | Biology | `WBI` | `WBI12` | `01` | Unit 2: Cells, Development, Biodiversity and Conservation | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-15 | Morning | Mathematics | `WMA` | `WMA12` | `01` | Pure Mathematics 2 | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-15 | Afternoon | Physics | `WPH` | `WPH12` | `01` | Unit 2: Waves and Electricity | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-16 | Morning | Economics | `WEC` | `WEC12` | `01` | Unit 2: Macroeconomic Performance and Policy | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-16 | Afternoon | Biology | `WBI` | `WBI13` | `01` | Unit 3: Practical Skills in Biology I | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-19 | Morning | Mathematics | `WST` | `WST01` | `01` | Statistics S1 | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-19 | Afternoon | Physics | `WPH` | `WPH13` | `01` | Unit 3: Practical Skills in Physics I | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-20 | Morning | Business | `WBS` | `WBS13` | `01` | Unit 3: Business Decisions and Strategy | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-20 | Morning | Chemistry | `WCH` | `WCH13` | `01` | Unit 3: Practical Skills in Chemistry I | 80 | 10:30:00 | 11:50:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-20 | Afternoon | Accounting | `WAC` | `WAC11` | `01` | Unit 1: The Accounting System and Costing | 180 | 13:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-21 | Morning | Physics | `WPH` | `WPH14` | `01` | Unit 4: Further Mechanics, Fields and Particles | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-21 | Afternoon | Mathematics | `WMA` | `WMA13` | `01` | Pure Mathematics 3 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-22 | Morning | Mathematics | `WME` | `WME02` | `01` | Mechanics M2 | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-22 | Afternoon | Chemistry | `WCH` | `WCH14` | `01` | Unit 4: Rates, Equilibria and Further Organic Chemistry | 105 | 13:30:00 | 15:15:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-23 | Morning | Biology | `WBI` | `WBI14` | `01` | Unit 4: Energy, Environment, Microbiology and Immunity | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-23 | Afternoon | Economics | `WEC` | `WEC13` | `01` | Unit 3: Business Behaviour | 120 | 13:30:00 | 15:30:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-26 | Morning | Mathematics | `WST` | `WST02` | `01` | Statistics S2 | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-26 | Afternoon | Chemistry | `WCH` | `WCH15` | `01` | Unit 5: Transition Metals and Organic Nitrogen Chemistry | 105 | 13:30:00 | 15:15:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-27 | Morning | Biology | `WBI` | `WBI15` | `01` | Unit 5: Respiration, Internal Environment, Coordination and Gene Technology | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-27 | Morning | Business | `WBS` | `WBS14` | `01` | Unit 4: Global Business | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-27 | Afternoon | Accounting | `WAC` | `WAC12` | `01` | Unit 2: Corporate and Management Accounting | 180 | 13:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-28 | Morning | Physics | `WPH` | `WPH15` | `01` | Unit 5: Thermodynamics, Radiation, Oscillations and Cosmology | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-28 | Afternoon | Mathematics | `WMA` | `WMA14` | `01` | Pure Mathematics 4 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-29 | Morning | Biology | `WBI` | `WBI16` | `01` | Unit 6: Practical Skills in Biology II | 80 | 10:30:00 | 11:50:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-29 | Afternoon | Chemistry | `WCH` | `WCH16` | `01` | Unit 6: Practical Skills in Chemistry II | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |
| 2026-10-30 | Morning | Economics | `WEC` | `WEC14` | `01` | Unit 4: Developments In The Global Economy | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2026-10-30 | Afternoon | Physics | `WPH` | `WPH16` | `01` | Unit 6: Practical Skills in Physics II | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |

### January 2027 IAL

- **Qualification:** IAL
- **Year / session:** 2027 / January
- **Exam count:** 82
- **Timezone:** Asia/Colombo
- **Morning start:** 11:30:00
- **Afternoon start:** 14:30:00
- **Source:** Pearson Edexcel International Advanced Levels January 2027 Examination Timetable - FINAL
- **Source URL:** https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-january-2027-final.pdf

| Date | Session | Subject | Subject code | Unit code | Paper | Unit / paper title | Duration (min) | Local start | Local end | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2027-01-08 | Morning | English Language | `WEN` | `WEN01` | `01` | Unit 1: Language: Context and Identity | 105 | 11:30:00 | 13:15:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-08 | Morning | Geography | `WGE` | `WGE01` | `01` | Unit 1: Global Challenges | 105 | 11:30:00 | 13:15:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-08 | Morning | Mathematics | `WMA` | `WMA11` | `01` | P1: Pure Mathematics 1 | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-08 | Afternoon | Biology | `WBI` | `WBI11` | `01` | Unit 1: Molecules, Diet, Transport and Health | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-08 | Afternoon | French | `WFR` | `WFR02` | `01` | Unit 2: Understanding and Written Response | 150 | 14:30:00 | 17:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-08 | Afternoon | Psychology | `WPS` | `WPS01` | `01` | Unit 1: Social and cognitive psychology | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-11 | Morning | Arabic | `WAA` | `WAA01` | `01` | Unit 1: Understanding and Written Response | 150 | 11:30:00 | 14:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-11 | Morning | Business | `WBS` | `WBS11` | `01` | Unit 1: Marketing And People | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-11 | Morning | Chemistry | `WCH` | `WCH11` | `01` | Unit 1: Structure, Bonding and Introduction to Organic Chemistry | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-11 | Afternoon | Economics | `WEC` | `WEC11` | `01` | Unit 1: Markets In Action | 105 | 14:30:00 | 16:15:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-11 | Afternoon | English Literature | `WET` | `WET01` | `01` | Unit 1: Post-2000 Poetry and Prose | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-11 | Afternoon | Physics | `WPH` | `WPH11` | `01` | Unit 1: Mechanics and Materials | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-12 | Morning | Accounting | `WAC` | `WAC11` | `01` | Unit 1: The Accounting System and Costing | 180 | 11:30:00 | 14:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-12 | Morning | Biology | `WBI` | `WBI12` | `01` | Unit 2: Cells, Development, Biodiversity and Conservation | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-12 | Morning | Psychology | `WPS` | `WPS02` | `01` | Unit 2: Biological psychology, learning theories and development | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-12 | Afternoon | Chemistry | `WCH` | `WCH12` | `01` | Unit 2: Energetics, Group Chemistry, Halogenoalkanes and Alcohol | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-12 | Afternoon | History | `WHI` | `WHI01` | `1A` | Unit 1 - Option 1A: France in Revolution, 1774-99 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-12 | Afternoon | History | `WHI` | `WHI01` | `1B` | Unit 1 - Option 1B: Russia in Revolution, 1881-1917 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-12 | Afternoon | History | `WHI` | `WHI01` | `1C` | Unit 1 - Option 1C: Germany, 1918-45 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-12 | Afternoon | History | `WHI` | `WHI01` | `1D` | Unit 1 - Option 1D: Britain, 1964-90 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-12 | Afternoon | Mathematics | `WST` | `WST01` | `01` | S1: Statistics 1 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-13 | Morning | Economics | `WEC` | `WEC12` | `01` | Unit 2: Macroeconomic Performance and Policy | 105 | 11:30:00 | 13:15:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-13 | Morning | English Language | `WEN` | `WEN02` | `01` | Unit 2: Language in Transition | 105 | 11:30:00 | 13:15:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-13 | Morning | Geography | `WGE` | `WGE02` | `01` | Unit 2: Geographical Investigations | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-13 | Afternoon | German | `WGN` | `WGN02` | `01` | Unit 2: Understanding and Written Response | 150 | 14:30:00 | 17:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-13 | Afternoon | Mathematics | `WMA` | `WMA12` | `01` | P2: Pure Mathematics 2 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-13 | Afternoon | Physics | `WPH` | `WPH12` | `01` | Unit 2: Waves and Electricity | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-14 | Morning | Arabic | `WAA` | `WAA02` | `01` | Unit 2: Writing and Research | 180 | 11:30:00 | 14:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-14 | Morning | Biology | `WBI` | `WBI13` | `01` | Unit 3: Practical Skills in Biology I | 80 | 11:30:00 | 12:50:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-14 | Morning | Mathematics | `WME` | `WME01` | `01` | M1: Mechanics 1 | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-14 | Afternoon | Chemistry | `WCH` | `WCH13` | `01` | Unit 3: Practical Skills in Chemistry I | 80 | 14:30:00 | 15:50:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-14 | Afternoon | English Literature | `WET` | `WET02` | `01` | Unit 2: Drama | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-14 | Afternoon | Mathematics | `WFM` | `WFM01` | `01` | FP1: Further Pure Mathematics 1 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-15 | Morning | Business | `WBS` | `WBS12` | `01` | Unit 2: Managing Business Activities | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-15 | Morning | Economics | `WEC` | `WEC13` | `01` | Unit 3: Business Behaviour | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-15 | Morning | English Language | `WEN` | `WEN03` | `01` | Unit 3: Crafting Language (Writing) | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-15 | Afternoon | History | `WHI` | `WHI02` | `1A` | Unit 2 - Option 1A: India, 1857-1948: The Raj to Partition | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-15 | Afternoon | History | `WHI` | `WHI02` | `1B` | Unit 2 - Option 1B: China, 1900-76 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-15 | Afternoon | History | `WHI` | `WHI02` | `1C` | Unit 2 - Option 1C: Russia, 1917-91: From Lenin to Yeltsin | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-15 | Afternoon | History | `WHI` | `WHI02` | `1D` | Unit 2 - Option 1D: South Africa, 1948-2014 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-15 | Afternoon | Mathematics | `WMA` | `WMA13` | `01` | P3: Pure Mathematics 3 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-15 | Afternoon | Physics | `WPH` | `WPH13` | `01` | Unit 3: Practical Skills in Physics I | 80 | 14:30:00 | 15:50:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-18 | Morning | Biology | `WBI` | `WBI14` | `01` | Unit 4: Energy, Environment, Microbiology and Immunity | 105 | 11:30:00 | 13:15:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-18 | Morning | Business | `WBS` | `WBS13` | `01` | Unit 3: Business Decisions And Strategy | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-18 | Morning | French | `WFR` | `WFR04` | `01` | Unit 4: Research, Understanding and Written Response | 150 | 11:30:00 | 14:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-18 | Afternoon | Chemistry | `WCH` | `WCH14` | `01` | Unit 4: Rates, Equilibria and Further Organic Chemistry | 105 | 14:30:00 | 16:15:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-18 | Afternoon | Geography | `WGE` | `WGE03` | `01` | Unit 3: Contested Planet | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-18 | Afternoon | Mathematics | `WST` | `WST02` | `01` | S2: Statistics 2 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-19 | Morning | English Literature | `WET` | `WET03` | `01` | Unit 3: Poetry and Prose | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-19 | Morning | Mathematics | `WMA` | `WMA14` | `01` | P4: Pure Mathematics 4 | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-19 | Morning | Spanish | `WSP` | `WSP02` | `01` | Unit 2: Understanding and Written Response | 150 | 11:30:00 | 14:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-19 | Afternoon | Mathematics | `WDM` | `WDM11` | `01` | D1: Decision Mathematics 1 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-19 | Afternoon | Physics | `WPH` | `WPH14` | `01` | Unit 4: Further Mechanics, Fields and Particles | 105 | 14:30:00 | 16:15:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-19 | Afternoon | Psychology | `WPS` | `WPS03` | `01` | Unit 3: Applications of psychology | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-20 | Morning | Accounting | `WAC` | `WAC12` | `01` | Unit 2: Corporate and Management Accounting | 180 | 11:30:00 | 14:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-20 | Morning | Biology | `WBI` | `WBI15` | `01` | Unit 5: Respiration, Internal Environment, Coordination and Gene Technology | 105 | 11:30:00 | 13:15:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-20 | Morning | Mathematics | `WFM` | `WFM02` | `01` | FP2: Further Pure Mathematics 2 | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-20 | Afternoon | Economics | `WEC` | `WEC14` | `01` | Unit 4: Developments In The Global Economy | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-20 | Afternoon | History | `WHI` | `WHI03` | `1A` | Unit 3 - Option 1A: The USA, Independence to Civil War, 1763-1865 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-20 | Afternoon | History | `WHI` | `WHI03` | `1B` | Unit 3 - Option 1B: The British Experience of Warfare, 1803-1945 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-20 | Afternoon | History | `WHI` | `WHI03` | `1C` | Unit 3 - Option 1C: Germany: United, Divided and Reunited, 1870-1990 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-20 | Afternoon | History | `WHI` | `WHI03` | `1D` | Unit 3 - Option 1D: Civil Rights and Race Relations in the USA, 1865-2009 | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-20 | Afternoon | Mathematics | `WME` | `WME02` | `01` | M2: Mechanics 2 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-21 | Morning | Business | `WBS` | `WBS14` | `01` | Unit 4: Global Business | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-21 | Morning | Chemistry | `WCH` | `WCH15` | `01` | Unit 5: Transition Metals and Organic Nitrogen Chemistry | 105 | 11:30:00 | 13:15:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-21 | Morning | German | `WGN` | `WGN04` | `01` | Unit 4: Research, Understanding and Written Response | 150 | 11:30:00 | 14:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-21 | Afternoon | English Language | `WEN` | `WEN04` | `01` | Unit 4: Investigating Language | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-21 | Afternoon | Mathematics | `WFM` | `WFM03` | `01` | FP3: Further Pure Mathematics 3 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-21 | Afternoon | Physics | `WPH` | `WPH15` | `01` | Unit 5: Thermodynamics, Radiation, Oscillations and Cosmology | 105 | 14:30:00 | 16:15:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-22 | Morning | Biology | `WBI` | `WBI16` | `01` | Unit 6: Practical Skills in Biology II | 80 | 11:30:00 | 12:50:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-22 | Morning | Geography | `WGE` | `WGE04` | `01` | Unit 4: Researching Geography | 90 | 11:30:00 | 13:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-22 | Morning | Spanish | `WSP` | `WSP04` | `01` | Unit 4: Research, Understanding and Written Response | 150 | 11:30:00 | 14:00:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-22 | Afternoon | Chemistry | `WCH` | `WCH16` | `01` | Unit 6: Practical Skills in Chemistry II | 80 | 14:30:00 | 15:50:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-22 | Afternoon | English Literature | `WET` | `WET04` | `01` | Unit 4: Shakespeare and Pre-1900 Poetry | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-22 | Afternoon | Mathematics | `WST` | `WST03` | `01` | S3: Statistics 3 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-25 | Morning | History | `WHI` | `WHI04` | `1A` | Unit 4 - Option 1A: The Making of Modern Europe, 1805-71 | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-25 | Morning | History | `WHI` | `WHI04` | `1B` | Unit 4 - Option 1B: The World in Crisis, 1879-1945 | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-25 | Morning | History | `WHI` | `WHI04` | `1C` | Unit 4 - Option 1C: The World Divided: Superpower Relations, 1943-90 | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-25 | Morning | History | `WHI` | `WHI04` | `1D` | Unit 4 - Option 1D: The Cold War and Hot War in Asia, 1945-90 | 120 | 11:30:00 | 13:30:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-25 | Morning | Physics | `WPH` | `WPH16` | `01` | Unit 6: Practical Skills in Physics II | 80 | 11:30:00 | 12:50:00 | Pearson session: Morning / SL start 11:30 |
| 2027-01-25 | Afternoon | Mathematics | `WME` | `WME03` | `01` | M3: Mechanics 3 | 90 | 14:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 14:30 |
| 2027-01-25 | Afternoon | Psychology | `WPS` | `WPS04` | `01` | Unit 4: Clinical psychology and psychological skills | 120 | 14:30:00 | 16:30:00 | Pearson session: Afternoon / SL start 14:30 |

### May/June 2027 IAL

- **Qualification:** IAL
- **Year / session:** 2027 / May/June
- **Exam count:** 92
- **Timezone:** Asia/Colombo
- **Morning start:** 10:30:00
- **Afternoon start:** 13:30:00
- **Source:** Pearson Edexcel International Advanced Levels Summer 2027 Examination Timetable - FINAL
- **Source URL:** https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-International-Advanced-Levels/ial-summer-2027-final.pdf

| Date | Session | Subject | Subject code | Unit code | Paper | Unit / paper title | Duration (min) | Local start | Local end | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2027-05-04 | Morning | Accounting | `WAC` | `WAC11` | `01` | Unit 1: The Accounting System and Costing | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-04 | Morning | Psychology | `WPS` | `WPS01` | `01` | Unit 1: Social and cognitive psychology | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-04 | Afternoon | Biology | `WBI` | `WBI11` | `01` | Unit 1: Molecules, Diet, Transport and Health | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-05 | Morning | Arabic | `WAA` | `WAA01` | `01` | Unit 1: Understanding and Written Response | 150 | 10:30:00 | 13:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-05 | Morning | Business | `WBS` | `WBS11` | `01` | Unit 1: Marketing and People | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-05 | Afternoon | Chemistry | `WCH` | `WCH11` | `01` | Unit 1: Structure, Bonding and Introduction to Organic Chemistry | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-06 | Morning | Economics | `WEC` | `WEC11` | `01` | Unit 1: Markets in Action | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-06 | Morning | Information Technology (IT) | `WIT` | `WIT11` | `01` | Unit 1 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-06 | Afternoon | Mathematics | `WMA` | `WMA11` | `01` | P1: Pure Mathematics 1 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-07 | Morning | English Language | `WEN` | `WEN01` | `01` | Unit 1: Language: Context and Identity | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-07 | Morning | Physics | `WPH` | `WPH11` | `01` | Unit 1: Mechanics and Materials | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-07 | Afternoon | Mathematics | `WST` | `WST01` | `01` | S1: Statistics 1 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-10 | Morning | Business | `WBS` | `WBS12` | `01` | Unit 2: Managing Business Activities | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-10 | Morning | Greek | `WGK` | `WGK01` | `01` | Unit 1: Understanding and Written Response | 150 | 10:30:00 | 13:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-10 | Afternoon | Chemistry | `WCH` | `WCH12` | `01` | Unit 2: Energetics, Group Chemistry, Halogenoalkanes and Alcohols | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-10 | Afternoon | Spanish | `WSP` | `WSP02` | `01` | Unit 2: Understanding and Written Response | 150 | 13:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-11 | Morning | Computer Science | `WCP` | `WCP01` | `01` | Unit 1: Principles of Computer Science | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-11 | Morning | English Literature | `WET` | `WET01` | `01` | Unit 1: Post-2000 Poetry and Prose | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-11 | Afternoon | Mathematics | `WMA` | `WMA12` | `01` | P2: Pure Mathematics 2 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-12 | Morning | Economics | `WEC` | `WEC12` | `01` | Unit 2: Macroeconomic Performance and Policy | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-12 | Morning | Law | `YLA` | `YLA1` | `01` | Paper 1: Underlying Principles of Law and the English Legal System | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-12 | Afternoon | Geography | `WGE` | `WGE01` | `01` | Unit 1: Global Challenges | 105 | 13:30:00 | 15:15:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-12 | Afternoon | Physics | `WPH` | `WPH12` | `01` | Unit 2: Waves and Electricity | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-13 | Morning | History | `WHI` | `WHI01` | `1A` | Unit 1 - Option 1A: France in Revolution, 1774-99 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-13 | Morning | History | `WHI` | `WHI01` | `1B` | Unit 1 - Option 1B: Russia in Revolution, 1881-1917 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-13 | Morning | History | `WHI` | `WHI01` | `1C` | Unit 1 - Option 1C: Germany, 1918-45 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-13 | Morning | History | `WHI` | `WHI01` | `1D` | Unit 1 - Option 1D: Britain, 1964-90 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-13 | Morning | Psychology | `WPS` | `WPS02` | `01` | Unit 2: Biological psychology, learning theories and development | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-13 | Afternoon | Mathematics | `WME` | `WME01` | `01` | M1: Mechanics 1 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-14 | Morning | French | `WFR` | `WFR02` | `01` | Unit 2: Understanding and Written Response | 150 | 10:30:00 | 13:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-14 | Morning | Information Technology (IT) | `WIT` | `WIT12` | `01` | Unit 2 | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-14 | Afternoon | Biology | `WBI` | `WBI12` | `01` | Unit 2: Cells, Development, Biodiversity and Conservation | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-19 | Morning | Greek | `WGK` | `WGK02` | `01` | Unit 2: Writing and Research | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-19 | Morning | History | `WHI` | `WHI02` | `1A` | Unit 2 - Option 1A: India, 1857-1948: The Raj to Partition | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-19 | Morning | History | `WHI` | `WHI02` | `1B` | Unit 2 - Option 1B: China, 1900-76 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-19 | Morning | History | `WHI` | `WHI02` | `1C` | Unit 2 - Option 1C: Russia, 1917-91: From Lenin to Yeltsin | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-19 | Morning | History | `WHI` | `WHI02` | `1D` | Unit 2 - Option 1D: South Africa, 1948-2014 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-19 | Afternoon | Physics | `WPH` | `WPH13` | `01` | Unit 3: Practical Skills in Physics I | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-20 | Morning | English Language | `WEN` | `WEN02` | `01` | Unit 2: Language in Transition | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-20 | Morning | Geography | `WGE` | `WGE02` | `01` | Unit 2: Geographical Investigations | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-20 | Afternoon | Biology | `WBI` | `WBI13` | `01` | Unit 3: Practical Skills in Biology I | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-21 | Morning | English Literature | `WET` | `WET02` | `01` | Unit 2: Drama | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-21 | Morning | German | `WGN` | `WGN02` | `01` | Unit 2: Understanding and Written Response | 150 | 10:30:00 | 13:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-21 | Afternoon | Chemistry | `WCH` | `WCH13` | `01` | Unit 3: Practical Skills in Chemistry I | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-24 | Morning | Business | `WBS` | `WBS13` | `01` | Unit 3: Business Decisions And Strategy | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-24 | Morning | Law | `YLA` | `YLA1` | `02` | Paper 2: The Law in Action | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-24 | Afternoon | Biology | `WBI` | `WBI14` | `01` | Unit 4: Energy, Environment, Microbiology and Immunity | 105 | 13:30:00 | 15:15:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-25 | Morning | Accounting | `WAC` | `WAC12` | `01` | Unit 2: Corporate and Management Accounting | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-25 | Morning | Arabic | `WAA` | `WAA02` | `01` | Unit 2: Writing and Research | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-25 | Afternoon | Mathematics | `WMA` | `WMA13` | `01` | P3: Pure Mathematics 3 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-26 | Morning | Economics | `WEC` | `WEC13` | `01` | Unit 3: Business Behaviour | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-26 | Morning | French | `WFR` | `WFR04` | `01` | Unit 4: Research, Understanding and Written Response | 150 | 10:30:00 | 13:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-26 | Afternoon | Chemistry | `WCH` | `WCH14` | `01` | Unit 4: Rates, Equilibria and Further Organic Chemistry | 105 | 13:30:00 | 15:15:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-27 | Morning | English Language | `WEN` | `WEN03` | `01` | Unit 3: Crafting Language (Writing) | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-27 | Morning | Spanish | `WSP` | `WSP04` | `01` | Unit 4: Research, Understanding and Written Response | 150 | 10:30:00 | 13:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-27 | Afternoon | Mathematics | `WFM` | `WFM01` | `01` | FP1: Further Pure Mathematics 1 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-05-28 | Morning | English Literature | `WET` | `WET03` | `01` | Unit 3: Poetry and Prose | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-28 | Morning | Information Technology (IT) | `WIT` | `WIT13` | `01` | Unit 3 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-05-28 | Afternoon | Physics | `WPH` | `WPH14` | `01` | Unit 4: Further Mechanics, Fields and Particles | 105 | 13:30:00 | 15:15:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-01 | Morning | Business | `WBS` | `WBS14` | `01` | Unit 4: Global Business | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-01 | Morning | Geography | `WGE` | `WGE03` | `01` | Unit 3: Contested Planet | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-01 | Afternoon | Mathematics | `WDM` | `WDM11` | `01` | D1: Decision Mathematics 1 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-02 | Morning | Chemistry | `WCH` | `WCH15` | `01` | Unit 5: Transition Metals and Organic Nitrogen Chemistry | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-02 | Morning | Information Technology (IT) | `WIT` | `WIT14` | `01` | Unit 4 | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-02 | Afternoon | History | `WHI` | `WHI03` | `1A` | Unit 3 - Option 1A: The USA, Independence to Civil War, 1763-1865 | 120 | 13:30:00 | 15:30:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-02 | Afternoon | History | `WHI` | `WHI03` | `1B` | Unit 3 - Option 1B: The British Experience of Warfare, 1803-1945 | 120 | 13:30:00 | 15:30:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-02 | Afternoon | History | `WHI` | `WHI03` | `1C` | Unit 3 - Option 1C: Germany: United, Divided and Reunited,
1870-1990 | 120 | 13:30:00 | 15:30:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-02 | Afternoon | History | `WHI` | `WHI03` | `1D` | Unit 3 - Option 1D: Civil Rights and Race Relations in the USA,
1865-2009 | 120 | 13:30:00 | 15:30:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-02 | Afternoon | Mathematics | `WST` | `WST02` | `01` | S2: Statistics 2 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-03 | Morning | Physics | `WPH` | `WPH15` | `01` | Unit 5: Thermodynamics, Radiation, Oscillations and Cosmology | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-03 | Morning | Psychology | `WPS` | `WPS03` | `01` | Unit 3: Applications of psychology | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-03 | Afternoon | Mathematics | `WFM` | `WFM02` | `01` | FP2: Further Pure Mathematics 2 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-04 | Morning | Biology | `WBI` | `WBI15` | `01` | Unit 5: Respiration, Internal Environment, Coordination and Gene Technology | 105 | 10:30:00 | 12:15:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-04 | Morning | Economics | `WEC` | `WEC14` | `01` | Unit 4: Developments in the Global Economy | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-04 | Afternoon | Mathematics | `WME` | `WME02` | `01` | M2: Mechanics 2 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-07 | Morning | Computer Science | `WCP` | `WCP02` | `01` | Unit 2: Practical Programming and Problem-solving | 180 | 10:30:00 | 13:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-07 | Morning | English Language | `WEN` | `WEN04` | `01` | Unit 4: Investigating Language | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-07 | Afternoon | German | `WGN` | `WGN04` | `01` | Unit 4: Research, Understanding and Written Response | 150 | 13:30:00 | 16:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-07 | Afternoon | Mathematics | `WMA` | `WMA14` | `01` | P4: Pure Mathematics 4 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-08 | Morning | English Literature | `WET` | `WET04` | `01` | Unit 4: Shakespeare and Pre-1900 Poetry | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-08 | Morning | Physics | `WPH` | `WPH16` | `01` | Unit 6: Practical Skills in Physics II | 80 | 10:30:00 | 11:50:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-08 | Afternoon | Mathematics | `WFM` | `WFM03` | `01` | FP3: Further Pure Mathematics 3 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-09 | Morning | Biology | `WBI` | `WBI16` | `01` | Unit 6: Practical Skills in Biology II | 80 | 10:30:00 | 11:50:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-09 | Morning | Geography | `WGE` | `WGE04` | `01` | Unit 4: Researching Geography | 90 | 10:30:00 | 12:00:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-09 | Afternoon | Mathematics | `WST` | `WST03` | `01` | S3: Statistics 3 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-10 | Morning | History | `WHI` | `WHI04` | `1A` | Unit 4 - Option 1A: The Making of Modern Europe, 1805-71 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-10 | Morning | History | `WHI` | `WHI04` | `1B` | Unit 4 - Option 1B: The World in Crisis, 1879-1945 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-10 | Morning | History | `WHI` | `WHI04` | `1C` | Unit 4 - Option 1C: The World Divided: Superpower Relations, 
1943-90 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-10 | Morning | History | `WHI` | `WHI04` | `1D` | Unit 4 - Option 1D: The Cold War and Hot War in Asia, 1945-90 | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-10 | Morning | Psychology | `WPS` | `WPS04` | `01` | Unit 4: Clinical psychology and psychological skills | 120 | 10:30:00 | 12:30:00 | Pearson session: Morning / SL start 10:30 |
| 2027-06-10 | Afternoon | Chemistry | `WCH` | `WCH16` | `01` | Unit 6: Practical Skills in Chemistry II | 80 | 13:30:00 | 14:50:00 | Pearson session: Afternoon / SL start 13:30 |
| 2027-06-10 | Afternoon | Mathematics | `WME` | `WME03` | `01` | M3: Mechanics 3 | 90 | 13:30:00 | 15:00:00 | Pearson session: Afternoon / SL start 13:30 |

### May/June 2027 IGCSE

- **Qualification:** IGCSE
- **Year / session:** 2027 / May/June
- **Exam count:** 119
- **Timezone:** Asia/Colombo
- **Morning start:** 13:30:00
- **Afternoon start:** 18:00:00
- **Source:** Pearson Edexcel International GCSE Summer 2027 Examination Timetable - FINAL
- **Source URL:** https://qualifications.pearson.com/content/dam/pdf/Support/Examination-timetables-for-Edexcel-International-GCSE/int-gcse-summer-2027-final.pdf

| Date | Session | Subject | Subject code | Unit code | Paper | Unit / paper title | Duration (min) | Local start | Local end | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2027-05-10 | Morning | Computer Science | `4CP0` | `4CP0` | `01` | Paper 1: Principles of Computer Science | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-10 | Morning | Greek (First Language) | `4GK1` | `4GK1` | `01` | Paper 1: Reading, Summary and Grammar | 135 | 13:30:00 | 15:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-10 | Morning | Human Biology | `4HB1` | `4HB1` | `01` | Paper 01 | 105 | 13:30:00 | 15:15:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-10 | Afternoon | Arabic | `4AA1` | `4AA1` | `01` | Paper 1: Reading, Summary and Grammar | 135 | 18:00:00 | 20:15:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-10 | Afternoon | German | `4GN1` | `4GN1` | `01` | Paper 1: Listening | 35 | 18:00:00 | 18:35:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-10 | Afternoon | German | `4GN1` | `4GN1` | `02` | Paper 2: Reading and Writing | 105 | 18:00:00 | 19:45:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-11 | Morning | Biology (Linear) | `4BI1` | `4BI1` | `1B` | Paper: 1B | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-11 | Morning | Biology (Modular) | `4WBI1` | `4WBI1` | `1B` | Unit 1 | 100 | 13:30:00 | 15:10:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-11 | Morning | Science (Double Award) (Linear) | `4SD0` | `4SD0` | `1B` | Paper: 1B | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-11 | Morning | Science (Double Award) (Modular) | `4WSD1` | `4WSD1` | `1B` | Unit 1 | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-11 | Afternoon | English as a Second Language | `4WES1` | `4WES1` | `01` | Unit 1: Reading | 60 | 18:00:00 | 19:00:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-11 | Afternoon | English as a Second Language | `4WES2` | `4WES2` | `01` | Unit 2: Listening | 45 | 18:00:00 | 18:45:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-12 | Morning | English Literature (Linear) | `4ET1` | `4ET1` | `01` | Paper 1: Poetry and Modern Prose | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-12 | Morning | English Literature (Modular) | `4WET1` | `4WET1` | `01` | Unit 1: Poetry and Modern Prose | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-12 | Afternoon | Business | `4BS1` | `4BS1` | `01` | Paper 1: Investigating small businesses | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-12 | Afternoon | Information And Communication Technology (ICT) | `4IT1` | `4IT1` | `01` | Paper 1: Written Paper | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-13 | Morning | Religious Studies (Linear) | `4RS1` | `4RS1` | `01` | Paper 1: Beliefs and Values | 105 | 13:30:00 | 15:15:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-13 | Morning | Religious Studies (Modular) | `4WRS1` | `4WRS1` | `01` | Unit 1: Beliefs and Values | 105 | 13:30:00 | 15:15:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-13 | Afternoon | Economics (Linear) | `4EC1` | `4EC1` | `01` | Paper 1: Microeconomics and Business Economics | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-13 | Afternoon | Economics (Modular) | `4WEC1` | `4WEC1` | `01` | Unit 1: Microeconomics and Business Economics | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-14 | Morning | Mathematics A (Linear) | `4MA1` | `4MA1` | `1F` | Paper 1F Foundation Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-14 | Morning | Mathematics A (Linear) | `4MA1` | `4MA1` | `1H` | Paper 1H Higher Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-14 | Morning | Mathematics A (Modular) | `4WM1F` | `4WM1F` | `01` | Unit 1F Foundation Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-14 | Morning | Mathematics A (Modular) | `4WM1H` | `4WM1H` | `01` | Unit 1H Higher Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-14 | Afternoon | Bangla | `4BA0` | `4BA0` | `01` | Paper 1: Reading, Writing and Translation | 150 | 18:00:00 | 20:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-18 | Morning | Geography (Linear) | `4GE1` | `4GE1` | `01` | Paper 1: Physical geography | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-18 | Morning | Geography (Modular) | `4WGE1` | `4WGE1` | `01` | Unit 1: Physical geography | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-18 | Afternoon | Chemistry (Linear) | `4CH1` | `4CH1` | `1C` | Paper: 1C | 120 | 18:00:00 | 20:00:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-18 | Afternoon | Chemistry (Modular) | `4WCH1` | `4WCH1` | `1C` | Unit 1 | 100 | 18:00:00 | 19:40:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-18 | Afternoon | Science (Double Award) (Linear) | `4SD0` | `4SD0` | `1C` | Paper: 1C | 120 | 18:00:00 | 20:00:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-18 | Afternoon | Science (Double Award) (Modular) | `4WSD3` | `4WSD3` | `1C` | Unit 3 | 70 | 18:00:00 | 19:10:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-19 | Morning | English Literature (Linear) | `4ET1` | `4ET1` | `02` | Paper 2: Modern Drama and Literary Heritage Texts | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-19 | Morning | English Literature (Modular) | `4WET2` | `4WET2` | `01` | Unit 2: Modern Drama and Literary Heritage Texts | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-19 | Afternoon | Further Pure Mathematics | `4PM1` | `4PM1` | `01` | Paper 1 | 120 | 18:00:00 | 20:00:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-20 | Morning | Accounting (Linear) | `4AC1` | `4AC1` | `01` | Paper 1: Introduction to Bookkeeping and Accounting | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-20 | Morning | Accounting (Modular) | `4WAC1` | `4WAC1` | `01` | Unit 1: Introduction to Bookkeeping and Accounting | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-20 | Morning | History (Linear) | `4HI1` | `4HI1` | `01` | Paper 1: Depth Studies | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-20 | Morning | History (Modular) | `4WHI1` | `4WHI1` | `01` | Unit 1: Depth Studies | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-20 | Afternoon | Arabic | `4AA1` | `4AA1` | `02` | Paper 2: Writing | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-21 | Morning | Religious Studies (Linear) | `4RS1` | `4RS1` | `2A` | Paper 2A: Buddhism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Linear) | `4RS1` | `4RS1` | `2B` | Paper 2B: Christianity | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Linear) | `4RS1` | `4RS1` | `2C` | Paper 2C: Hinduism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Linear) | `4RS1` | `4RS1` | `2D` | Paper 2D: Islam | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Linear) | `4RS1` | `4RS1` | `2E` | Paper 2E: Judaism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Linear) | `4RS1` | `4RS1` | `2F` | Paper 2F: Sikhism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Modular) | `4WRS2` | `4WRS2` | `1A` | Unit 2A: Buddhism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Modular) | `4WRS2` | `4WRS2` | `1B` | Unit 2B: Christianity | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Modular) | `4WRS2` | `4WRS2` | `1C` | Unit 2C: Hinduism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Modular) | `4WRS2` | `4WRS2` | `1D` | Unit 2D: Islam | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Modular) | `4WRS2` | `4WRS2` | `1E` | Unit 2E: Judaism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Morning | Religious Studies (Modular) | `4WRS2` | `4WRS2` | `1F` | Unit 2F: Sikhism | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-21 | Afternoon | Business | `4BS1` | `4BS1` | `02` | Paper 2: Investigating large businesses | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-24 | Morning | English Language A (Linear) | `4EA1` | `4EA1` | `01` | Paper 1: Non-fiction Texts and Transactional Writing | 135 | 13:30:00 | 15:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-24 | Morning | English Language A (Modular) | `4WEA1` | `4WEA1` | `01` | Unit 1: Non-fiction Texts and Transactional Writing | 135 | 13:30:00 | 15:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-24 | Morning | English Language B | `4EB1` | `4EB1` | `01` | Paper 1 | 180 | 13:30:00 | 16:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-24 | Afternoon | Commerce (Linear) | `4CM1` | `4CM1` | `01` | Paper 1: Commercial operations and associated risks | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-24 | Afternoon | Commerce (Modular) | `4WCM1` | `4WCM1` | `01` | Unit 1: Commercial operations and associated risks | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-25 | Morning | Physics (Linear) | `4PH1` | `4PH1` | `1P` | Paper: 1P | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-25 | Morning | Physics (Modular) | `4WPH1` | `4WPH1` | `1P` | Unit 1 | 100 | 13:30:00 | 15:10:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-25 | Morning | Science (Double Award) (Linear) | `4SD0` | `4SD0` | `1P` | Paper: 1P | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-25 | Morning | Science (Double Award) (Modular) | `4WSD5` | `4WSD5` | `1P` | Unit 5 | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-25 | Afternoon | Economics (Linear) | `4EC1` | `4EC1` | `02` | Paper 2: Macroeconomics and the Global Economy | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-25 | Afternoon | Economics (Modular) | `4WEC2` | `4WEC2` | `01` | Unit 2: Macroeconomics and the Global Economy | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-26 | Morning | French | `4FR1` | `4FR1` | `01` | Paper 1: Listening | 35 | 13:30:00 | 14:05:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-26 | Morning | French | `4FR1` | `4FR1` | `02` | Paper 2: Reading and Writing | 105 | 13:30:00 | 15:15:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-26 | Morning | Islamic Studies | `4IS1` | `4IS1` | `01` | Paper 1: Islamic Studies | 150 | 13:30:00 | 16:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-26 | Afternoon | Geography (Linear) | `4GE1` | `4GE1` | `02` | Paper 2: Human geography | 105 | 18:00:00 | 19:45:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-26 | Afternoon | Geography (Modular) | `4WGE2` | `4WGE2` | `01` | Unit 2: Human geography | 105 | 18:00:00 | 19:45:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-05-27 | Morning | Mathematics A (Linear) | `4MA1` | `4MA1` | `2F` | Paper 2F Foundation Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-27 | Morning | Mathematics A (Linear) | `4MA1` | `4MA1` | `2H` | Paper 2H Higher Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-27 | Morning | Mathematics A (Modular) | `4WM2F` | `4WM2F` | `01` | Unit 2F Foundation Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-27 | Morning | Mathematics A (Modular) | `4WM2H` | `4WM2H` | `01` | Unit 2H Higher Tier | 120 | 13:30:00 | 15:30:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-28 | Morning | History (Linear) | `4HI1` | `4HI1` | `02` | Paper 2: Investigation and Breadth Studies | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-05-28 | Morning | History (Modular) | `4WHI2` | `4WHI2` | `01` | Unit 2: Investigation and Breadth Studies | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-01 | Morning | Bangladesh Studies | `4BN1` | `4BN1` | `01` | Paper 1: History and culture of Bangladesh | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-01 | Afternoon | Greek (First Language) | `4GK1` | `4GK1` | `02` | Paper 2: Writing | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-02 | Morning | Pakistan Studies | `4PA1` | `4PA1` | `01` | Paper 1: History and culture of Pakistan | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-02 | Afternoon | Sinhala | `4SI1` | `4SI1` | `01` | Paper 1: Reading, Writing and Translation | 150 | 18:00:00 | 20:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-03 | Morning | Swahili | `4SW1` | `4SW1` | `01` | Paper 1: Reading, Writing and Translation | 135 | 13:30:00 | 15:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-03 | Morning | Swahili | `4SW1` | `4SW1` | `02` | Paper 2: Listening | 35 | 13:30:00 | 14:05:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-03 | Afternoon | Accounting (Linear) | `4AC1` | `4AC1` | `02` | Paper 2: Financial Statements | 75 | 18:00:00 | 19:15:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-03 | Afternoon | Accounting (Modular) | `4WAC2` | `4WAC2` | `01` | Unit 2: Financial Statements | 75 | 18:00:00 | 19:15:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-04 | Morning | Tamil | `4TA1` | `4TA1` | `01` | Paper 1: Reading, Writing and Translation | 150 | 13:30:00 | 16:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-07 | Window | Computer Science | `4CP0` | `4CP0` | `02` | Paper 2: Application of Computational Thinking | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-07 | Morning | English as a Second Language | `4WES3` | `4WES3` | `01` | Unit 3: Writing | 75 | 13:30:00 | 14:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-07 | Window | Information And Communication Technology (ICT) | `4IT1` | `4IT1` | `02` | Paper 2: Practical Exam | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-07 | Afternoon | Chinese | `4CN1` | `4CN1` | `01` | Paper 1: Listening | 35 | 18:00:00 | 18:35:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-07 | Afternoon | Chinese | `4CN1` | `4CN1` | `02` | Paper 2: Reading and Writing | 105 | 18:00:00 | 19:45:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-07 | Afternoon | Mathematics B | `4MB1` | `4MB1` | `01` | Paper 1 | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-08 | Window | Computer Science | `4CP0` | `4CP0` | `02` | Paper 2: Application of Computational Thinking | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-08 | Morning | English Language A (Linear) | `4EA1` | `4EA1` | `02` | Paper 2: Poetry and Prose Texts and Imaginative Writing | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-08 | Morning | English Language A (Modular) | `4WEA2` | `4WEA2` | `01` | Unit 2: Poetry and Prose Texts and Imaginative Writing | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-08 | Window | Information And Communication Technology (ICT) | `4IT1` | `4IT1` | `02` | Paper 2: Practical Exam | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-08 | Afternoon | Human Biology | `4HB1` | `4HB1` | `02` | Paper 02 | 105 | 18:00:00 | 19:45:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-09 | Window | Computer Science | `4CP0` | `4CP0` | `02` | Paper 2: Application of Computational Thinking | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-09 | Morning | Global Citizenship | `4GL1` | `4GL1` | `01` | Paper 1 | 150 | 13:30:00 | 16:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-09 | Window | Information And Communication Technology (ICT) | `4IT1` | `4IT1` | `02` | Paper 2: Practical Exam | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-09 | Morning | Spanish | `4SP1` | `4SP1` | `01` | Paper 1: Listening | 35 | 13:30:00 | 14:05:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-09 | Morning | Spanish | `4SP1` | `4SP1` | `02` | Paper 2: Reading and Writing | 105 | 13:30:00 | 15:15:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-09 | Afternoon | Further Pure Mathematics | `4PM1` | `4PM1` | `02` | Paper 2 | 120 | 18:00:00 | 20:00:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-10 | Morning | Biology (Linear) | `4BI1` | `4BI1` | `2B` | Paper: 2B | 75 | 13:30:00 | 14:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-10 | Morning | Biology (Modular) | `4WBI2` | `4WBI2` | `1B` | Unit 2 | 100 | 13:30:00 | 15:10:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-10 | Window | Information And Communication Technology (ICT) | `4IT1` | `4IT1` | `02` | Paper 2: Practical Exam | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-10 | Morning | Science (Double Award) (Modular) | `4WSD2` | `4WSD2` | `1B` | Unit 2 | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-10 | Morning | Science (Single Award) | `4SS0` | `4SS0` | `1B` | Paper: 1B | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-10 | Afternoon | Commerce (Linear) | `4CM1` | `4CM1` | `02` | Paper 2: Facilitating commercial operations | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-10 | Afternoon | Commerce (Modular) | `4WCM2` | `4WCM2` | `01` | Unit 2: Facilitating commercial operations | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-11 | Window | Information And Communication Technology (ICT) | `4IT1` | `4IT1` | `02` | Paper 2: Practical Exam | 180 | 13:30:00 | 16:30:00 | Pearson session: Window / SL start 13:30 |
| 2027-06-11 | Morning | Pakistan Studies | `4PA1` | `4PA1` | `02` | Paper 2: The landscape, people and economy of Pakistan | 90 | 13:30:00 | 15:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-11 | Afternoon | Bangladesh Studies | `4BN1` | `4BN1` | `02` | Paper 2: The landscape, people and economy of Bangladesh | 90 | 18:00:00 | 19:30:00 | Pearson session: Afternoon / SL start 18:00 |
| 2027-06-14 | Morning | Mathematics B | `4MB1` | `4MB1` | `02` | Paper 2 | 150 | 13:30:00 | 16:00:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-15 | Morning | Chemistry (Linear) | `4CH1` | `4CH1` | `2C` | Paper: 2C | 75 | 13:30:00 | 14:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-15 | Morning | Chemistry (Modular) | `4WCH2` | `4WCH2` | `1C` | Unit 2 | 100 | 13:30:00 | 15:10:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-15 | Morning | Science (Double Award) (Modular) | `4WSD4` | `4WSD4` | `1C` | Unit 4 | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-15 | Morning | Science (Single Award) | `4SS0` | `4SS0` | `1C` | Paper: 1C | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-18 | Morning | Physics (Linear) | `4PH1` | `4PH1` | `2P` | Paper: 2P | 75 | 13:30:00 | 14:45:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-18 | Morning | Physics (Modular) | `4WPH2` | `4WPH2` | `1P` | Unit 2 | 100 | 13:30:00 | 15:10:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-18 | Morning | Science (Double Award) (Modular) | `4WSD6` | `4WSD6` | `1P` | Unit 6 | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |
| 2027-06-18 | Morning | Science (Single Award) | `4SS0` | `4SS0` | `1P` | Paper: 1P | 70 | 13:30:00 | 14:40:00 | Pearson session: Morning / SL start 13:30 |

---

## 12. Appendix B — Data Provenance

| Section of this document | Primary source in project |
| --- | --- |
| Institute identity, tagline, programmes/qualifications defaults | `includes/homepage-data.php`, `includes/college_contact.php` |
| Exam series, unit titles, dates, durations | `database/seeds/official_exams.json` |
| Practice questions, explanations, misconceptions | `src/Services/CoursoQuizBank.php` |
| Portal capabilities / non-LMS note | `SYSTEM.md`, `README.md` |
| Fees default / timezone | `SYSTEM.md` |
| Lesson scripts, books, formal rubrics, capstone | **Not present** → marked Not specified |

---

*End of course_details.md*
