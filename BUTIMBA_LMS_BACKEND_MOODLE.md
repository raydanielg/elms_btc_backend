# Butimba Teachers College LMS — Moodle-Style Backend Specification

> **Goal:** A backend modeled on **Moodle's internal architecture** — the full set of internal management subsystems (contexts, granular roles/capabilities, course formats, enrolment methods, gradebook, question bank, completion, badges, competencies, files API, events, scheduled tasks, backup/restore, web services).
> **Institution scope:** Diploma programs only.
> **Version:** v2.0 (Moodle-style)
> **Principle:** Everything plugin-driven and configurable — nothing hard-coded.

---

## 0. How Moodle Organizes Itself (Mental Model)

Moodle is not a flat app. Its power comes from a few backbone ideas that every other subsystem plugs into:

1. **Context hierarchy** — every object lives in a *context*; permissions cascade down the tree.
2. **Roles + capabilities** — permissions are granular capabilities assigned to roles **at a context**, with overrides.
3. **Plugins everywhere** — activity modules, enrolment methods, auth methods, repositories, question types, grade aggregations are all pluggable.
4. **Course = category → course → sections → course_modules → activity instance.**
5. **Gradebook** aggregates grade items into categories into a course total.
6. **Files API** — files are never "in a folder"; they live in *file areas* keyed by context + component + filearea + itemid.
7. **Events & logging** — everything emits events; the log store and analytics read them.

The rest of this document builds each subsystem on top of these.

---

## 1. Tech Stack

| Layer | Recommendation |
|-------|----------------|
| Framework | Node.js/NestJS **or** PHP/Laravel (Moodle itself is PHP) |
| Database | PostgreSQL / MySQL (Moodle supports both) |
| Cache | Redis (Moodle MUC — Multi-layer Universal Cache equivalent) |
| Queue / cron | Redis + worker (Moodle scheduled + adhoc tasks) |
| File storage | Object storage keyed by content hash (Moodle file API) |
| Search | OpenSearch/Solr (global search) |
| Auth | Pluggable auth methods + JWT/session |

Table prefix convention (Moodle-like): `bt_` (shown without prefix below).

---

## 2. Context Hierarchy (The Backbone)

Every permission check happens **in a context**. Contexts form a tree; a role assigned high up cascades to everything below.

### 2.1 Context levels
| Level | Const | Instance points to |
|-------|-------|--------------------|
| 10 | `SYSTEM` | whole site (one row) |
| 40 | `COURSECAT` | course_categories.id |
| 50 | `COURSE` | course.id |
| 70 | `MODULE` | course_modules.id (an activity) |
| 80 | `BLOCK` | block_instances.id |
| 30 | `USER` | user.id |

### 2.2 Table: `context`
| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL PK | |
| contextlevel | SMALLINT | 10/30/40/50/70/80 |
| instanceid | BIGINT | id within that level |
| path | VARCHAR | e.g. `/1/12/45/230` (ancestor ids) |
| depth | SMALLINT | |

`path` + `depth` let a single indexed query fetch all ancestor/descendant contexts (used by every permission check).

---

## 3. Roles & Capabilities (Granular RBAC)

Moodle's permission model — far richer than a simple role enum.

### 3.1 Tables
**`role`**
| id | name | shortname (UNIQUE) | archetype | description |

Seed roles: `manager`, `coursecreator`, `editingteacher` (tutor), `teacher` (non-editing), `student`, `guest`, `authenticated user`. (Butimba mapping in §26.)

**`capability`** — the master list of ~ hundreds of atomic permissions
| name (PK) | e.g. `mod/quiz:attempt`, `moodle/course:manageactivities` |
| contextlevel | where it applies |
| riskbitmask | RISK_XSS, RISK_CONFIG, RISK_PERSONAL, RISK_SPAM, RISK_DATALOSS |

**`role_capabilities`** — what a role can do, per context
| roleid FK | capability FK | contextid FK | permission |
`permission ∈ { ALLOW(1), PREVENT(-1), PROHIBIT(-1000), INHERIT(0) }`.

**`role_assignments`** — who has which role, where
| id | roleid FK | contextid FK | userid FK | timemodified |

**`role_context_levels`** — which levels a role may be assigned at.

### 3.2 Permission resolution algorithm
```
has_capability(userid, capability, context):
    contexts = ancestors(context) + context      # by context.path
    verdict = INHERIT
    for ctx in contexts (system → leaf):
        for each role the user has in ctx:
            p = role_capabilities[role, capability, ctx]
            if p == PROHIBIT: return DENY          # cannot be overridden
            if p != INHERIT: verdict = p           # nearer context wins
    return verdict == ALLOW
```
- **PROHIBIT** always wins.
- Otherwise the **most specific context** wins → enables course-level overrides.

### 3.3 Endpoints
```
GET  /roles                         POST /roles
POST /roles/:id/assign              { userid, contextid }
POST /roles/:id/capabilities        { capability, contextid, permission }   # override
GET  /context/:id/permissions/:userid
```

---

## 4. Course Categories & Courses

### 4.1 `course_categories` (nested tree)
| id | name | idnumber | parent | path (`/1/5/12`) | depth | visible | sortorder |

### 4.2 `course`
| Column | Notes |
|--------|-------|
| id | PK |
| category FK | |
| fullname / shortname (UNIQUE) | shortname = program code (e.g. DSE-SCI) |
| idnumber | external code |
| format | ENUM(topics, weeks, singleactivity, social) |
| startdate / enddate | |
| visible / lang / theme | |
| enablecompletion | BOOLEAN |
| groupmode | ENUM(NOGROUPS, SEPARATE, VISIBLE) |

### 4.3 Course format & structure
- **`course_format_options`** (courseid, format, name, value) — per-format settings (e.g. number of sections).
- **`course_sections`** (id, course, section, name, summary, sequence, visible) — `sequence` = CSV of course_module ids in order.

### 4.4 Endpoints
```
GET/POST/PATCH/DELETE  /categories
GET/POST/PATCH/DELETE  /courses
POST  /courses/:id/sections
PATCH /courses/:id/sections/:num/move
POST  /courses/:id/reset          # course reset (wipe user data, keep content)
```

---

## 5. Course Modules & the Activity/Resource Plugin System

A **course_module** is the placement of an activity/resource instance inside a course section.

### 5.1 `modules` (registry of module *types*)
| id | name |  → assign, quiz, forum, resource, page, book, folder, url, label, lesson, scorm, workshop, wiki, glossary, choice, feedback, survey, chat, data, h5pactivity, bigbluebutton |

### 5.2 `course_modules`
| Column | Notes |
|--------|-------|
| id | PK |
| course FK | |
| module FK→modules | which type |
| instance | id in the module's own table (e.g. quiz.id) |
| section FK→course_sections | |
| idnumber / visible / visibleoncoursepage | |
| completion | ENUM(NONE, MANUAL, AUTO) |
| completiongradeitemnumber / completionview / completionexpected | |
| availability | JSON (access restrictions: date, grade, group, profile, completion of X) |
| groupmode | |

### 5.3 Access restrictions (`availability` JSON)
Mirrors Moodle "Restrict access": AND/OR trees of conditions — date, grade ≥ X, group membership, another activity completed, user profile field.

### 5.4 Endpoints
```
POST  /courses/:id/modules            # add activity/resource: { module, section, instance_payload }
PATCH /modules/:cmid                  # visibility, completion, restrictions
POST  /modules/:cmid/move             # to section/position
DELETE/modules/:cmid
GET   /courses/:id/contents           # full course tree (sections → modules)
```

---

## 6. Enrolment Subsystem (Pluggable Methods)

Moodle separates **enrolment** (being in a course) from **role** (what you can do). Enrolment is via pluggable *methods* per course.

### 6.1 `enrol` (an enrolment instance on a course)
| id | enrol (method) | courseid FK | status (ENABLED/DISABLED) | roleid (role granted) | enrolstartdate | enrolenddate | enrolperiod | customint/char fields |

Methods: `manual`, `self` (with enrolment key), `cohort` (sync), `guest`, `meta` (link another course), **`payment`** (gate on paid fee), `flatfile`, `database` (external).

### 6.2 `user_enrolments`
| id | enrolid FK | userid FK | status (ACTIVE/SUSPENDED) | timestart | timeend | timecreated |

> **Butimba payment gate:** the `payment` enrol method activates a user_enrolment only after a confirmed registration/admission fee (see §22 Finance & §26).

### 6.3 Endpoints
```
GET   /courses/:id/enrolment-methods       POST /courses/:id/enrolment-methods
POST  /enrol/manual                        { courseid, userid, roleid, timeend? }
POST  /enrol/self                          { courseid, enrolkey }
POST  /enrol/:id/suspend/:userid
GET   /courses/:id/enrolled-users
```

---

## 7. Groups & Groupings

### 7.1 Tables
- **`groups`** (id, courseid, name, idnumber, enrolmentkey, picture)
- **`groups_members`** (groupid, userid, timeadded)
- **`groupings`** (id, courseid, name) — a set of groups
- **`groupings_groups`** (groupingid, groupid)

Group mode (NOGROUPS/SEPARATE/VISIBLE) is set at course or activity level and filters what members see (e.g. separate-group forums).

### 7.2 Endpoints
```
POST /courses/:id/groups          POST /groups/:id/members
POST /courses/:id/groupings       POST /groupings/:id/groups
POST /courses/:id/groups/auto     # auto-create N groups / N-per-group
```

---

## 8. Cohorts (Site/Category-Wide User Sets)

### 8.1 Tables
- **`cohort`** (id, contextid, name, idnumber, visible, description)
- **`cohort_members`** (cohortid, userid, timeadded)

Cohorts feed **cohort-sync enrolment** (add a whole intake to a program's courses at once) and **learning plan** assignment.

### 8.2 Endpoints
```
GET/POST /cohorts        POST /cohorts/:id/members
POST /cohorts/:id/sync-to-course   { courseid, roleid }
```

---

## 9. Activity Modules (Each Has Its Own Tables)

Each activity type stores its instance data in a dedicated table; `course_modules.instance` points to it.

### 9.1 Assignment — `assign`
`assign`: id, course, name, intro, duedate, allowsubmissionsfromdate, cutoffdate, grade, maxattempts, submissiondrafts, teamsubmission, blindmarking, markingworkflow, timelimit.
`assign_submission`: id, assignment, userid, groupid, status(new/submitted), attemptnumber, latest.
`assign_grades`: id, assignment, userid, grader, grade, attemptnumber.
Plugins: file submission, online text; feedback comments, annotate PDF.

### 9.2 Quiz — `quiz`
`quiz`: id, course, name, timeopen, timeclose, timelimit, attempts, grademethod(highest/average/first/last), questionsperpage, shuffleanswers, reviewoptions.
`quiz_slots`: quiz, slot, questionid/ref, maxmark, page.
`quiz_attempts`: quiz, userid, attempt, uniqueid→question_usage, state(inprogress/finished), timestart, timefinish, sumgrades.
`quiz_grades`: quiz, userid, grade.
(Uses the Question Engine — §10/§11.)

### 9.3 Forum — `forum`
`forum` (type: general/news/qanda/single/eachuser), `forum_discussions`, `forum_posts`, `forum_subscriptions`, `forum_read` (tracking).

### 9.4 Resources & content modules
`resource` (file), `folder`, `page`, `book` + `book_chapters`, `url`, `label`, `lesson` (+ `lesson_pages`, `lesson_answers`), `scorm` (+ `scorm_scoes`, `scorm_scoes_track`), `h5pactivity`, `workshop` (peer assessment), `wiki`, `glossary`, `choice`, `feedback`, `survey`, `data` (database activity), `bigbluebutton` (live class).

### 9.5 Generic module endpoints
```
GET  /modules/:cmid                     # view instance
POST /assign/:id/submit
POST /assign/:id/grade/:userid
POST /quiz/:id/attempt/start | /process | /finish
POST /forum/:id/discussions             POST /discussions/:id/posts
```

---

## 10. Question Bank (Shared Across Quizzes)

### 10.1 Tables
- **`question_categories`** (id, contextid, parent, name) — categories live in a **context**, so questions can be shared site/category/course-wide.
- **`question`** (id, category, qtype, name, questiontext, generalfeedback, defaultmark, penalty, hidden).
- **`question_answers`** (question, answer, fraction, feedback).
- Per-type tables: `qtype_multichoice_options`, `qtype_match_*`, `qtype_shortanswer_options`, `qtype_numerical_*`, `qtype_essay_options`, `qtype_calculated_*`, `qtype_ddimageortext_*`, `qtype_gapselect_*` (cloze), etc.
- **`question_bank_entries`** + **`question_versions`** — question versioning.
- Tags via the tag system (§17).

Question types: MCQ, true/false, matching, short answer, numerical, calculated, essay, drag-and-drop (image/text/marker), select missing words, cloze/embedded, description.

### 10.2 Endpoints
```
GET/POST /question-categories
GET/POST /questions        PATCH /questions/:id   # creates a new version
GET      /questions?category=&qtype=&tag=
```

---

## 11. Question Engine (Attempt Layer)

Separates a question's *definition* from a user's *attempt* at it, so the same question behaves under different modes (deferred feedback, interactive, adaptive).

- **`question_usages`** — a set of question attempts for one activity attempt.
- **`question_attempts`** (questionusageid, slot, questionid, maxmark, responsesummary, rightanswer).
- **`question_attempt_steps`** (+ `_data`) — every state transition of an attempt (Moodle stores the full history).

Behaviours: `deferredfeedback`, `interactive`, `adaptive`, `immediatefeedback`, `manualgraded`.

---

## 12. Gradebook

The gradebook aggregates everything gradable into a course total.

### 12.1 Tables
**`grade_items`** — one per gradable thing (activity, manual item, category total, course total)
| id | courseid | categoryid FK | itemname | itemtype (mod/manual/category/course) | itemmodule (assign/quiz) | iteminstance | grademax | grademin | gradepass | gradetype (VALUE/SCALE/TEXT/NONE) | scaleid FK | aggregationcoef (weight) | sortorder | hidden | locked |

**`grade_categories`** — grouping + aggregation
| id | courseid | parent | fullname | aggregation | keephigh | droplow | aggregateonlygraded | aggregateoutcomes |

Aggregation methods: mean, weighted mean, simple weighted mean, mean of selected, median, min, max, mode, **natural (sum)**.

**`grade_grades`** — the actual grades
| id | itemid FK | userid | rawgrade | finalgrade | rawgrademax/min | feedback | information | hidden | locked | overridden |

**`grade_grades_history`** — full audit of grade changes.
**`grade_outcomes`** — competency-style outcomes attachable to items.
**`scale`** — custom scales (e.g. "A,B,C,D,F" or "Not yet/Competent").
**`grade_letters`** — letter boundaries (contextid, lowerboundary, letter).

### 12.2 Endpoints
```
GET   /courses/:id/gradebook               # tree: categories → items → grades
POST  /courses/:id/grade-items             # manual item
PATCH /grade-items/:id                     # weight, max, category
POST  /grade-items/:id/grades/:userid      { finalgrade, feedback }
GET   /courses/:id/grades/export?format=csv|xlsx
POST  /courses/:id/grades/import
GET   /me/grades                           # student grade report
```

---

## 13. Advanced Grading (Rubrics & Marking Guides)

For activities graded with a method richer than a single number.

- **`grading_areas`** (contextid, component, areaname, activemethod).
- **`grading_definitions`** (areaid, method, name, status).
- **`grading_instances`** (definitionid, raterid, itemid) — one marking event.
- **Rubric:** `gradingform_rubric_criteria` → `gradingform_rubric_levels`; fills → `gradingform_rubric_fillings`.
- **Marking guide:** `gradingform_guide_criteria` (+ comments bank).

Endpoints:
```
POST /modules/:cmid/grading/rubric        # define rubric
POST /assign/:id/grade/:userid/rubric     # apply rubric on grading
```

---

## 14. Completion Tracking

### 14.1 Activity completion — `course_modules_completion`
| coursemoduleid | userid | completionstate (0..3) | viewed | timemodified |
Rules taken from `course_modules` completion fields (view, grade, submit, meet criteria).

### 14.2 Course completion
- **`course_completion_criteria`** (course, criteriatype: activity/grade/date/duration/self/role/course).
- **`course_completion_crit_compl`** (userid per criterion).
- **`course_completions`** (userid, timecompleted).

### 14.3 Endpoints
```
POST /modules/:cmid/complete           # manual self-completion
GET  /courses/:id/completion/report    # per-student matrix
GET  /me/completion
```

---

## 15. Badges (Digital Achievements)

- **`badge`** (name, description, type site/course, courseid, status, criteria, imageurl, issuername, expiry).
- **`badge_criteria`** + **`badge_criteria_params`** — award conditions (activity completion, course completion, manual, cohort, competency).
- **`badge_issued`** (badgeid, userid, uniquehash, dateissued, dateexpire).
- **`badge_backpack`** — external backpack (Open Badges) connection.

Endpoints:
```
GET/POST /badges       POST /badges/:id/criteria
POST /badges/:id/award/:userid          # manual
GET  /me/badges
```

---

## 16. Competencies & Learning Plans

- **`competency_framework`** (shortname, idnumber, scaleid, contextid).
- **`competency`** (frameworkid, parentid, shortname, idnumber, path) — hierarchical.
- **`competency_coursecomp`** / **`competency_modulecomp`** — link competencies to courses/activities.
- **`competency_plan`** (userid, templateid, status) + **`competency_template`**.
- **`competency_usercomp`** (userid, competencyid, proficiency, grade) — attainment.
- **`competency_evidence`** — how it was demonstrated.

Endpoints:
```
GET/POST /competency-frameworks     POST /frameworks/:id/competencies
POST /courses/:id/competencies      # attach to course
POST /users/:id/plans               GET /me/competencies
```

---

## 17. Tags (Cross-Cutting)

- **`tag`** (id, name, rawname, tagcollid, isstandard).
- **`tag_instance`** (tagid, component, itemtype, itemid, contextid) — polymorphic; tags courses, questions, forum posts, users, etc.
- **`tag_coll`** — tag collections.

Endpoints: `GET /tags/:name`, `POST /:entity/:id/tags`.

---

## 18. Files API & Repositories

Moodle never stores files "in folders" — files are content-addressed and referenced by area.

### 18.1 `files`
| id | contenthash (sha1) | contextid | component (mod_assign, user, course) | filearea (submission_files, intro, content) | itemid | filepath | filename | filesize | mimetype | userid | timecreated |
Dedup: identical `contenthash` stored once.

### 18.2 Draft areas & repositories
- **Draft area** — a per-user staging file area used before an item is saved.
- **`repository`** / **`repository_instances`** — file sources: upload, URL download, server files, plus external: Google Drive, OneDrive, Dropbox, S3.
- **`files_reference`** — aliases/links to external files.

### 18.3 Endpoints
```
POST /files/draft                      # upload to draft area → draftitemid
POST /:component/:cmid/files/save      { draftitemid, filearea }
GET  /files/:contextid/:component/:filearea/:itemid/:filename   # signed, access-checked
GET  /repositories                     POST /repositories/:id/browse
```

---

## 19. Custom Profile Fields & Authentication

### 19.1 User profile
- **`user`** (core fields + `auth` method + `mnethostid`).
- **`user_info_category`**, **`user_info_field`** (datatype: text, textarea, checkbox, menu, date), **`user_info_data`** — admin-defined custom fields (e.g. "NECTA Index No", "Region").

### 19.2 Authentication methods (pluggable)
`manual`, `email` (self-registration + confirm), `oauth2` (Google/Microsoft), `ldap`, `cas`, `nologin`, `webservice`. Config in `config_plugins`.

Endpoints:
```
POST /auth/:method/login
POST /auth/email/register + /auth/email/confirm/:token
GET/POST /profile-fields                 # admin defines custom fields
```

---

## 20. Messaging & Notifications

### 20.1 Tables
- **`messages`** / **`message_conversations`** / **`message_conversation_members`** — internal messaging (1:1 and group).
- **`message_read`**, **`message_user_actions`** (read/deleted).
- **`notifications`** (useridfrom, useridto, subject, fullmessage, component, eventtype, read, timecreated).
- **`message_processors`** (email, popup, mobile/airnotifier) + **`message_providers`** + **user notification preferences**.

### 20.2 Endpoints
```
GET/POST /messages                    GET /me/notifications
PATCH /notifications/:id/read
PUT  /me/message-preferences
```

---

## 21. Logging, Events & Analytics

### 21.1 Event API
Every meaningful action dispatches a **structured event** (`\core\event\course_viewed`, `\mod_quiz\event\attempt_submitted`). Observers react (notifications, completion, analytics).

### 21.2 `logstore_standard_log`
| id | eventname | component | action | target | objecttable | objectid | contextid | courseid | userid | relateduserid | timecreated | other (JSON) |

Reports: live logs, activity report, course participation, per-user access.

### 21.3 Analytics
- **`analytics_models`** (target + indicators), **`analytics_predictions`** — e.g. "students at risk of not completing."

Endpoints:
```
GET /reports/logs?course=&user=&from=&to=
GET /reports/participation?course=
GET /analytics/predictions?model=
```

---

## 22. Finance & Payment Enrolment (Butimba-Specific)

Bridges Moodle's `payment` enrol method with the college fee model.

- **`fee_structure`** (programid/courseid, mode, item, amount, academic_year).
- **`payment`** (userid, component, itemid, amount, gateway, status PENDING/CONFIRMED, control_number, paidat, verifiedby).
- **`payment_gateways`** (config per gateway).
- **`debt`** (userid, description, amount_due, amount_paid, balance, due_date).

**Rule:** confirming a registration/admission payment triggers the event `payment_confirmed` → the `payment` enrol observer activates `user_enrolments` and (if applicable) upgrades the applicant to student.

Endpoints:
```
GET/POST /fees            PATCH /payments/:id/verify
GET /me/payments   GET /me/debts
```

---

## 23. Scheduled Tasks (Cron) & Adhoc Tasks

Moodle runs a task scheduler; nearly every subsystem registers tasks.

- **`task_scheduled`** (classname, minute, hour, day, month, dayofweek, lastruntime, nextruntime, disabled).
- **`task_adhoc`** (classname, customdata JSON, nextruntime, faildelay) — queued one-off jobs.

Typical tasks: send scheduled notifications, quiz auto-submit of overrun attempts, completion recalculation, gradebook recalculation, badge review, backup, log cleanup, search indexing, analytics prediction.

Endpoints (admin):
```
GET /admin/tasks/scheduled     PATCH /admin/tasks/:name
GET /admin/tasks/adhoc
```

---

## 24. Backup & Restore

- **`backup_controllers`** (backupid, type: course/activity/section, itemid, status, format MBZ-equivalent).
- Course backup captures: structure, activities, question bank, gradebook, (optionally) user data, files, completion.
- Restore into new/existing course; **import** copies content between courses; **duplicate** clones an activity.

Endpoints:
```
POST /courses/:id/backup        GET /backups/:id/status
POST /courses/:id/restore       { backupid, target: new|existing }
POST /courses/:id/import        { fromcourseid, settings }
```

---

## 25. Web Services & Tokens (API / Mobile)

- **`external_functions`** (name, classname, methodname, capabilities).
- **`external_services`** (name, enabled, restrictedusers) + **`external_services_functions`**.
- **`external_tokens`** (token, userid, serviceid, validuntil) — powers the mobile app and integrations.

Endpoints:
```
POST /webservice/token          { username, password, service }
POST /webservice/rest           { wsfunction, params }        # generic dispatcher
```

---

## 26. Site Administration & Configuration

- **`config`** (name, value) — core site settings.
- **`config_plugins`** (plugin, name, value) — per-plugin settings (auth, enrol, mod, qtype, gradebook, repositories, message processors).
- **`config_log`** — audit of admin setting changes.

Everything customizable — themes, languages, feature toggles, enrolment defaults, gradebook defaults — lives here, not in code.

Endpoints:
```
GET/PUT /admin/settings/:plugin
GET     /admin/plugins                 # installed plugins + versions
```

---

## 27. Butimba-Specific Mapping (Diploma Only)

| Butimba concept | Moodle mechanism |
|-----------------|------------------|
| Diploma program (DSE-SCI…) | **course_categories** (program) or a course template; `shortname` = program code |
| Course/module (ASE 231) | **course** with `idnumber` = course code; sections = topics |
| Teaching subject (MAT, PHY) | **tag** + custom field, or sub-category |
| Tutor | role `editingteacher` assigned at COURSE context |
| Student | role `student` via **payment** enrolment |
| Modes (Masafa/Kutwa/Bweni) | course custom field + cohort per mode |
| Apply Now | pre-enrolment **application** table → cohort on ACCEPTED |
| Login after fee | **payment** enrol method gate (§6, §22) |
| Assignment with timer | `assign.timelimit` / `cutoffdate` (§9.1) |
| Results | **gradebook** export (§12) |
| Announcements | `forum` type=news + notifications (§9.3, §20) |
| Online class (Meet/Zoom) | `bigbluebutton` or `url` module |

**Constraint:** restrict `course.level`/category tree to Diploma; hide other levels via config.

---

## 28. Minimum Build Order

1. Contexts + roles/capabilities (§2–3) — nothing works without these.
2. Users, auth, custom profile fields (§19).
3. Categories, courses, sections, course_modules + module registry (§4–5).
4. Enrolment methods incl. payment; groups; cohorts (§6–8).
5. Files API (§18) — required by every activity.
6. Core activities: assign, quiz (+ question bank + engine), forum, resource/page/url (§9–11).
7. Gradebook + completion (§12, §14).
8. Finance/payment bridge (§22).
9. Messaging/notifications, calendar, logging (§20–21).
10. Backup/restore, web services, admin config (§24–26).
11. Badges, competencies, analytics (§15–16, §21) — later phases.

---

## 29. To Confirm With the College

- Official Diploma programs + codes; meaning of note codes **MIE, CBE, PDE, COE, CE**.
- Real fee structure per program/mode.
- Payment gateway(s) to integrate.
- Whether to reuse actual **Moodle** (open-source) or build this custom backend — this spec supports either path.

---

*End of Moodle-style backend specification.*
