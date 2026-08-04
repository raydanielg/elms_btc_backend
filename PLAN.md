# Butimba LMS — Moodle-Style Backend Enhancement Plan

> **Approach:** Enhance the existing Laravel/PHP backend with Moodle-style subsystems.
> **Spec:** `BUTIMBA_LMS_BACKEND_MOODLE.md` (v2.0)
> **Scope:** Diploma programs only. Everything configurable.

---

## Existing Foundation (Already Built)

- ✅ Users + Sanctum auth (login, register, me, logout, password)
- ✅ Departments, Programs, Subjects
- ✅ Courses + Enrollments + Course Tutors
- ✅ Topics, Materials, Assignments, Submissions
- ✅ Quizzes + Questions + Quiz Attempts
- ✅ Online Classes, Discussion Forum, Announcements
- ✅ Finance (Fees, Payments, Debts)
- ✅ Applications, Results, Audit Logs, Media/News

---

## Milestone Checklist (Mapped to Spec §28)

### M1 — Contexts + Roles/Capabilities (Spec §2–3) ✅
- [x] `contexts` table (contextlevel, instanceid, path, depth)
- [x] `roles` table (seed: manager, editingteacher, teacher, student, guest)
- [x] `capabilities` table (master list of atomic permissions)
- [x] `role_capabilities` table (ALLOW/PREVENT/PROHIBIT/INHERIT per context)
- [x] `role_assignments` table (userid ↔ roleid ↔ contextid)
- [x] `role_context_levels` table
- [x] `CapabilityService` with `has_capability()` resolution algorithm
- [x] Seed system context + core roles + capabilities
- [x] API: `/roles`, `/roles/:id/assign`, `/roles/:id/capabilities`, `/context/:id/permissions/:userid`
- [x] Tests: permission resolution (ALLOW/PREVENT/PROHIBIT/INHERIT, context cascading)
- [x] Backwards-compat: map existing `role` enum to Moodle roles

### M2 — Users & Auth Enhancement (Spec §19) ✅
- [x] `user_info_category` + `user_info_field` + `user_info_data` (custom profile fields)
- [x] Enhanced auth: email confirmation flow, rate limiting
- [x] Payment-gate for student login (403 PAYMENT_REQUIRED)
- [x] API: `/profile-fields`, `/auth/email/confirm/:token`, `/auth/email/resend`
- [x] Tests: registration, confirmation, payment gate

### M3 — Course Categories & Structure (Spec §4–5) ✅
- [x] `course_categories` (nested tree with path/depth)
- [x] `course_sections` (section, name, summary, sequence)
- [x] `modules` registry (assign, quiz, forum, resource, page, url, etc.)
- [x] `course_modules` (placement of activity in section, availability JSON)
- [x] `course_format_options`
- [x] API: `/categories`, `/courses/:id/sections`, `/courses/:id/contents`
- [x] Migrate existing courses to category tree
- [x] Tests: category tree, section ordering, module placement

### M4 — Enrolment, Groups, Cohorts (Spec §6–8) ✅
- [x] `enrol` table (enrolment method instances per course)
- [x] `user_enrolments` table (separate from role)
- [x] Methods: manual, self, cohort, payment
- [x] `groups` + `groups_members` + `groupings` + `groupings_groups`
- [x] `cohort` + `cohort_members`
- [x] Payment enrol observer: activate enrolment on confirmed fee
- [x] API: `/courses/:id/enrolment-methods`, `/enrol/manual`, `/cohorts`, `/courses/:id/groups`
- [x] Tests: enrolment activation, cohort sync, group membership

### M5 — Files API (Spec §18) ✅
- [x] `files` table (contenthash, contextid, component, filearea, itemid)
- [x] Draft areas for staging uploads
- [x] Signed, access-checked download URLs
- [x] MIME + size validation, dedup by content hash
- [x] S3-compatible storage driver (local fallback, S3-ready)
- [x] API: `/files/draft`, `/files/:contextid/:component/:filearea/:itemid/:filename`
- [x] Tests: upload, dedup, access control

### M6 — Activities & Question Bank (Spec §9–11) ✅
- [x] Enhanced `assign` with cutoff/timer, team submission, blind marking
- [x] `quiz` enhanced with review options, shuffle, grademethod
- [x] `question_categories` + `question` + `question_answers` + versioning
- [x] `question_usages` + `question_attempts` + `question_attempt_steps`
- [x] Question types: MCQ, TF, short answer, essay, numerical, matching, description
- [x] Forum enhanced: news, general, Q&A, single discussion types
- [x] API: `/question-categories`, `/questions`, `/quiz/:id/attempt/start|process|finish`
- [x] Tests: timer enforcement, auto-submit, question versioning

### M7 — Gradebook & Completion (Spec §12, §14) ✅
- [x] `grade_items` + `grade_categories` + `grade_grades` + `grade_grades_history`
- [x] Aggregation methods: mean, weighted, natural (sum), min, max
- [x] `scale` + `grade_letters`
- [x] `course_modules_completion` (activity completion)
- [x] `course_completion_criteria` + `course_completions`
- [x] API: `/courses/:id/gradebook`, `/grade-items/:id/grades/:userid`, `/me/grades`
- [x] Grade export: CSV
- [x] Tests: aggregation math, completion rules

### M8 — Finance Bridge + Applications (Spec §22) ✅
- [x] `fee_structure` (program/course, mode, item, amount, academic_year)
- [x] `payment_gateways` config
- [x] Payment confirmed event → enrolment activation + role upgrade
- [x] Application flow: submit → pay → review ACCEPTED → cohort/enrol
- [x] API: `/fees`, `/payments/:id/verify`, application endpoints
- [x] Tests: payment-confirmed cascade, application conversion

### M9 — Platform Services (Spec §20–21, §23) ✅
- [x] `messages` + `message_conversations` + `notifications`
- [x] `calendar_events`
- [x] `logstore_standard_log` (structured event log)
- [x] `task_scheduled` + `task_adhoc` (cron + queue)
- [x] API: `/messages`, `/me/notifications`, `/reports/logs`, `/admin/tasks`
- [x] Tests: message delivery, event logging, task scheduling

### M10 — Later Phase (Spec §15–16, §24–26) ✅
- [x] Badges (`badge`, `badge_criteria`, `badge_issued`)
- [x] Competencies + learning plans
- [x] Analytics: "students at risk" model (framework in place)
- [x] Backup/restore controllers
- [x] Web services + tokens (mobile API)
- [x] Admin config (`config`, `config_plugins`)
- [x] API: `/badges`, `/competency-frameworks`, `/webservice/token`
- [x] Tests: badge awarding, competency attainment, backup/restore

### M11 — Certificates, Awards & Transcripts (Spec §1-8) ✅
- [x] `certificate_templates` + `certificate_pages` + `certificate_elements` (positioned items)
- [x] `certificate_issues` (code, hash, status, serial) + `certificate_verifications` (audit)
- [x] `awards` catalogue (merit-based)
- [x] CertificateService: eligibility check, issue, bulk issue, revoke, reissue
- [x] Trigger types: MANUAL, COURSE_COMPLETION, GRADE_THRESHOLD, PROGRAM_COMPLETION, COMPETENCY, COHORT
- [x] Fees-cleared gate (Butimba policy)
- [x] Public verification endpoint (`/verify/:code`) — no auth, privacy-safe
- [x] Transcript generation from gradebook
- [x] API: `/certificate-templates`, `/certificates/issue`, `/certificates/bulk-issue`, `/transcripts/generate`, `/verify/:code`, `/awards`
- [x] Tests: issue, duplicate-block, revoke, reissue, verify (valid/revoked/not-found), fees gate, transcript
- [x] Frontend: API services, types, sidebar nav, pages (my certs, templates, issue center, issued, awards, transcripts, badges, public verify, messages, calendar, gradebook, question bank)

---

## Decisions Log

- **Framework:** Laravel (existing) — spec §1 allows it
- **UUID keys:** Continue using UUIDs (existing pattern)
- **Table prefix:** Use `bt_` prefix for new Moodle-style tables to avoid conflicts
- **Backwards compat:** Existing `role` enum on users maps to Moodle archetypes:
  - SUPER_ADMIN → manager
  - PRINCIPAL → manager (site-level)
  - REGISTRAR → manager (category-level)
  - ACCOUNTANT → manager (finance scope)
  - TUTOR → editingteacher
  - STUDENT → student
  - APPLICANT → guest (pre-enrolment)
- **Existing tables:** Keep as-is; add Moodle-style tables alongside
- **Migration strategy:** New tables prefixed with `m_` to distinguish from existing

---

## Open Questions (QUESTIONS.md)

1. Official Diploma programs + codes (MIE, CBE, PDE, COE, CE)?
2. Real fee structure per program/mode?
3. Payment gateway(s) to integrate (GePG, Selcom, etc.)?
4. Academic year calendar — when do semesters start/end?

---

*Last updated: 2026-08-03*
