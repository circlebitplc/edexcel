# Video lessons

A video lesson belongs to one class on the timetable. It is not limited to online delivery. The same lesson can be built for an online class, an in-college class, or a hybrid class.

The class id in the URL is the timetable id. `online_lessons.timetable_id` is that same id. Do not invent a second lesson id.

## Who uses it

| Person | Where | What they can do |
| --- | --- | --- |
| Teacher or admin assigned to the class | Today, the lesson editor, analytics | Create, edit, publish, mark essays, view the class |
| Student enrolled in the class | Class page, lesson player, own result | Open a published lesson after the fee and availability rules pass |
| Anyone else | — | No access |

Staff access is checked with `RecordingService::lessonForStaff()`. A teacher sees their own classes, including a class they are substituting. An admin can open any class. A student id in the URL is ignored unless that student is enrolled in the class.

## Teacher workflow

1. Open **Today** (`campus/today.php`).
2. On the class card:
   - **Video lesson** opens the existing editor when a lesson row already exists.
   - **Analytics** opens results for that lesson.
   - **+ Create video lesson** opens the same editor and creates the row when none exists.
3. Add activities, then publish.
4. Students use the published lesson.
5. Open **Analytics** from the editor or from Today.

The status line on Today uses the stored lesson:

- `Published · N items`
- `Draft · N items`
- `Not built`

**Start class** is still only for online and hybrid classes. A physical class does not need LiveKit, a recording, or live attendance before a video lesson can be created or opened.

Editor: `campus/online_lesson.php?lesson={timetable id}`

Analytics: `campus/lesson_analytics.php?lesson={timetable id}`

## Student workflow

On the class page, a published lesson with at least one item shows a Video lesson card. **Watch Video Lesson** opens the existing player:

`student/lesson.php?lesson={timetable id}`

**Your result** opens only that student’s marks:

`student/lesson_result.php?lesson={timetable id}`

The result page does not accept another student’s id. Unpaid students do not see the lesson or the result. “Open only after class ends” and “close after N days” still apply to the player.

If the lesson has activities but the class recording is not ready, the activities stay available. A missing recording does not hide text, questions, or links.

## Activities

| Type | Stored as | Marks |
| --- | --- | --- |
| Video | `item_type = video` | None. Watch progress is stored when the player reports it. |
| Text page | `item_type = page` | None |
| External link | `item_type = external_link` | None. “Opened” means the student opened the link inside the lesson. |
| MCQ | activity type `mcq` | Automatic, from the stored correct option and each question’s marks |
| Essay | activity type `essay` | Manual. Unmarked work stays pending. |
| Mixed | activity type `mixed` | MCQ portion automatic, essay portion manual |

A required item, and “finish each item before the next”, still control whether the student can open the next item. Opening a page does not complete a video. A video is complete only when the existing watch rule marks it complete. A required external link is complete when the student opens it.

## Progress and time

Progress is completed items divided by the items in the lesson. Status is:

- Not started
- In progress
- Completed

Active time is recorded only while the lesson page is open and the browser tab is visible. The page sends a short total about every 25 seconds. A hidden tab does not keep adding time. Time from before this tracker was added is not reconstructed, so an older quiz can show a short time even when the student finished every question.

## Results

Analytics keeps the progress figures and adds marks beside them.

The lesson total is the sum of each question’s own maximum. Questions are not treated as equal, and activity percentages are not averaged. A 20-mark quiz plus a 10-mark essay is 30 marks.

A student’s percentage is:

`marks awarded / marks of work that has been marked × 100`

An activity the student has not submitted is left out of that fraction. An essay with no teacher mark is **Pending**, not 0. Video, text, and external links are not added to the academic total.

Class average, highest, lowest, median, and pass rate use students whose marked work covers the full lesson total. MCQ and essay averages use the marks actually stored, weighted by each question’s maximum. The latest attempt is the one shown.

On the analytics page the teacher can:

- filter by status, pass or fail, student name or id, activity type, and last-activity dates
- sort by student, marks, percentage, progress, time, and last activity
- open one student for activity marks and MCQ ticks
- see each MCQ question, the correct option, and how many students chose each option
- see which questions scored below that activity’s average success rate
- see essays that are submitted, marked, or still pending
- export a results CSV and a detailed CSV
- print the report

There is no pass mark until one is saved on the analytics page. Blank means pass and fail are not shown. The pass mark is `online_lessons.pass_percent`, from 1 to 100. Unmarked or unfinished work is not classified as a fail.

The system has no Edexcel grade boundaries for video lessons, so results stay as marks and percentages.

## Tables

Schema is applied by `ensure_online_lesson_schema()` when a lesson page or the analytics page loads. Today does not run that check.

| Table | Role |
| --- | --- |
| `online_lessons` | One row per timetable class. Published flag, sequence, watch rule, availability, pass mark. |
| `online_lesson_items` | Ordered videos, pages, activities, and links. |
| `online_lesson_activities` | MCQ, essay, or mixed activity, including its own pass percent for that activity. |
| `online_lesson_questions` | Prompt, choices, correct option, marks. |
| `online_lesson_attempts` | A submission. Score and maximum come from the answers. |
| `online_lesson_answers` | The chosen option, whether it was correct, essay text, and `marks_awarded`. |
| `online_lesson_progress` | One row per student and lesson. Completed count, last seen, active seconds. |
| `online_lesson_item_state` | One row per student and item. Completed or not, watch position, opens, active seconds. |

Extra columns added for this work, all optional for old rows:

- `online_lessons.pass_percent`
- `online_lesson_progress.active_seconds`
- `online_lesson_item_state.active_seconds`
- `online_lesson_item_state.open_count`
- `online_lesson_item_state.last_seen_at`
- `online_lesson_items.link_url` and `open_new_tab` for external links

## Code

| Path | Role |
| --- | --- |
| `campus/today.php` | Class card buttons and status |
| `campus/online_lesson.php` | Editor |
| `campus/lesson_analytics.php` | Class progress and results |
| `student/lesson.php` | Player |
| `student/lesson_result.php` | The logged-in student’s result |
| `student/class.php` | Video lesson card |
| `src/Services/OnlineLessonService.php` | Lesson, progress, and result queries |
| `src/Services/LessonResultBuilder.php` | Mark totals from stored answers |
| `ajax/online_lesson_watch.php` | Video watch progress |
| `ajax/online_lesson_time.php` | Active time |
| `ajax/online_lesson_link.php` | External link opened |
| `config/online_lesson.php` | Tables and URLs |

## Lesson plan and sections

On the lesson editor, **Lesson plan** stores objectives, topics, duration, difficulty, prerequisites, outcomes, materials, homework, and the assessment plan on the lesson row. Saving the plan does not publish.

**Generate lesson structure** builds a draft only when the lesson has no pages, questions, or links yet. Video clips already synced from a recording are left in place. The draft is:

- Introduction, with the objectives written on a text page
- One section and one notes page for each objective line
- Assessment, with an empty MCQ named “Check understanding”

The teacher edits that draft. Publishing stays blocked until the quiz has questions and a correct answer, text pages have content, and links are valid. A duration warning appears when the section minutes add up to more than the planned duration. It does not block publishing.

Sections group activities. Removing a section keeps the activities. Archived lessons leave the normal class list and can be shown with the Archived filter. Archiving does not unpublish, so students keep access until the teacher unpublishes.

Copying a lesson to another date, the question bank, essay marking, and student results are unchanged.

The class card on Today calls this a **Learning module**. The editor, player, and analytics URLs are unchanged.

**Save template** stores the plan, sections, pages, links, and questions. It does not store recordings, progress, attempts, or marks. **Use template** copies that into a lesson that does not already have pages or questions, as a draft. **Duplicate** on a section or activity makes a new copy and leaves the original unchanged. Video clips are not copied, because they belong to that class recording.

## Learning module additions

The class card says **Learning module**. The editor address is still `campus/online_lesson.php`.

Teachers can save a template, apply it to an empty lesson, and duplicate a section or activity. Templates do not copy the class recording, progress, attempts, or marks.

The plan stores objectives, duration, and the assessment plan. A duration line says whether the estimated minutes fit the planned time. It does not block saving. Objectives can be added, reordered, and linked to questions. Old lessons with no objectives still open.

Question types are MCQ, short answer, exam question, and essay. Short answers and exam questions are marked by the teacher. They are not scored by text matching. A mark scheme is a list of criteria. Ticking criteria fills the mark, and the mark that is saved cannot go above the question maximum.

Assignments and homework accept text and an uploaded file, with a due date and maximum marks. Missing work is not treated as zero. Statuses are not submitted, submitted, marked, returned, and resubmission requested. Files are stored outside the website folder and opened only after a permission check.

The question bank can be filtered, and selected questions are copied into the activity. Editing the copy does not change the bank. A resource can be a link or an uploaded PDF, PowerPoint, image, or document, then reused in a lesson.

Students see the section, progress, and remaining minutes when item minutes are saved. Returning opens the first unfinished item. Analytics adds short-answer, exam-question, and assignment averages when that work exists, plus objective percentages from linked marked questions. The wording is “Performance based on linked assessment results.”

`campus/learning_modules.php` lists the teacher’s modules. `campus/marking_queue.php` lists written answers and submissions that are still open.

## Not built yet

These were requested and are not in the product yet: AI drafting, certificates, an approval workflow, a syllabus tree, question pools that give each student a random subset, version history, scheduled publishing, lesson prerequisites, bookmarks, private notes, drag-and-drop ordering, and automatic structure from a PDF or PowerPoint. Uploading a PDF stores the file. It does not split the file into sections.

## Limits

- MCQ questions have one correct option. Marks can differ per question.
- Essay marks are only the marks a teacher saved.
- Time spent is visible time on the lesson page after tracking started. It is not a reconstruction of how long a past quiz took.
- Watch percentage is shown only when the player stored seconds and duration.
- An external link records that it was opened, not that the outside page was read.
