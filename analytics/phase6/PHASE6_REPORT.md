# Phase 6 — Populated BAT 405 presentation system

**DEMONSTRATION DATA — all academic records are fictional.** Snapshot: 17 November 2026, 09:30 Asia/Manila. This dataset is not actual BSU/university data and is not evidence of local model accuracy.

**Result: PASS for the tested local presentation workflows.** The portal at http://localhost/Faculty/ now has populated academic, admissions, schedule, grade, risk and descriptive SETE views. The brief is http://localhost/Faculty/index.php?view=brief. Academic-year, semester and college filters are functional; historical academic views are read-only.

## Inserted records

| Table | Inserted | Final rows |
|---|---:|---:|
| `applicant_requirements` | 800 | 800 |
| `applicants` | 160 | 160 |
| `colleges` | 4 | 4 |
| `courses` | 64 | 64 |
| `curriculum` | 128 | 128 |
| `enrollments` | 7,680 | 7,680 |
| `evaluation_responses` | 6,264 | 6,264 |
| `faculty` | 32 | 32 |
| `faculty_rating_history` | 64 | 64 |
| `interventions` | 64 | 64 |
| `meetings` | 320 | 320 |
| `meta` | 5 | 6 |
| `programs` | 8 | 8 |
| `risk_snapshots` | 36 | 36 |
| `rooms` | 16 | 16 |
| `sections` | 320 | 320 |
| `students` | 768 | 768 |
| `terms` | 3 | 3 |
| `users` | 0 | 1 |

**Total inserted: 16,736. Accounts inserted, deleted or updated: 0.** The existing user remains unchanged. One original metadata row was retained; five demonstration metadata rows were added. Final database row total: 16,738.

There are four colleges, eight programs, four year groups per program, 24 fictional students per group, 32 faculty, 64 courses and 16 rooms. The current semester has four modeled subjects per group. Two historical semesters contain the earlier cohorts; first-year entrants are absent from periods before their admission. This is a compact, balanced presentation sample, not an official degree transcript or a statistical representation of a real institution. Reference names and identities use DEMO/DEMONSTRATION markers and email addresses use `example.invalid`.

All academic records belong to a single owned batch, `BAT405_DEMO_2026_V1`, in an initially clean database. Batch-level provenance labels cover numeric-only rows such as grades, meetings and anonymous responses without changing any table structure. Login, dashboards, modules, print views and CSV downloads show demonstration provenance; exported rows include a Data source column and the selected term.

## Calculated current KPI values

| KPI | Value | Record source |
|---|---:|---|
| Active classes | 128 | Selected-term sections |
| Enrolled students | 768 | Student register/current enrolled cohort |
| Grades pending | 64 | Sections without grade submission timestamp |
| At-risk students | 96 | Existing failing/INC/back-subject rules |
| SETE average score | 4.14 | Response-weighted faculty average / 5 |

Current grade submissions: **64 Submitted, 32 In progress and 32 Not started**, giving **50% submitted**. All submitted sections have a final grade value for every enrolled student; INC and DRP remain valid recorded outcomes rather than missing values. Some pending sections have partially encoded final grades.

Current SETE: **2,576 anonymous responses / 3,072 eligible class invitations = 83.85%**, displayed as 84% where whole-percent formatting is used. Raw response mean is approximately 4.1419; the displayed response-weighted faculty score is 4.14/5. Responses never exceed the enrolled invitation count for any section. Histories for 32 faculty in each of two previous terms are calculated from those terms’ inserted anonymous responses.

The 96 current rule-based cases consist of 32 failing, 32 INC and 32 backlog cases; 32 meet the existing urgent rule. These counts are not Decision Tree dropout predictions. Current intervention records represent illustrative advising states; they are not term-keyed historical records.

## Period comparisons

| Semester | Academic year | Students with enrollment | Classes | Pending | SETE responses | Raw SETE mean |
|---|---|---:|---:|---:|---:|---:|
| DEMO S1 AY2025-26 | 2025-2026 | 576 | 96 | 0 | 1,816 | 4.1323 |
| DEMO S2 AY2025-26 | 2025-2026 | 576 | 96 | 0 | 1,872 | 4.1531 |
| DEMO S1 AY2026-27 | 2026-2027 | 768 | 128 | 64 | 2,576 | 4.1419 |

Current risk trend stored/rendered: Aug 2026: 160, Sep 2026: 128, Oct 2026: 96, Nov 2026: 96. Month-end points are explicitly simulated scenario snapshots derived from cohort patterns, not reconstructions of nonexistent grade audit logs. The final point is calculated from the selected term’s actual demonstration grades/profile flags.

Period selection changes classes, class enrollment, grade statistics, observed risk, monthly series, SETE responses and the preceding-term rating comparison. Historical grade/risk queries no longer compare meetings from different semesters or append a current-year point to old series. Student year level is projected from that term’s section enrollment. Current profile attributes remain current; historical intervention/contact records are explicitly unavailable instead of falsely reported as never contacted.

Admissions, reference catalogs and profile attributes are current registers, not fully versioned historical datasets. The applicants table has no term foreign key, so the upcoming DEMO 2027–2028 intake is not a historical admissions snapshot. A prior-intake/enrollment planning baseline is not invented merely to display a growth percentage.

## Data dependencies reviewed

| Feature | Required existing tables |
|---|---|
| Academic period filters | terms |
| Enrollment/college/program views | students, programs, colleges, enrollments, sections |
| Grade submission and course load | sections, enrollments, courses, terms |
| Schedules and conflicts | meetings, rooms, sections, faculty, courses, programs |
| Rule-based monitoring/support | student status/back-subject count, enrollment grades, sections, interventions |
| Monthly risk series | risk_snapshots, colleges plus the selected-term live rule count |
| SETE dimensions/distribution/participation | evaluation_responses, sections, faculty, enrollments, colleges |
| Historical teaching comparisons | faculty_rating_history, terms; summaries derived from responses |
| Applicant pipeline/documents | applicants, applicant_requirements, programs, colleges |
| Curriculum references | curriculum, programs, courses |
| Authentication/permissions | existing users and existing session mechanisms — unchanged |
| Decision Tree | independent existing model artifacts/UCI files — no academic table import |

All 19 tables were audited. Existing foreign keys, indexes, migrations and columns were retained. AUTO_INCREMENT counters advanced normally during insertion; they were not reset. Room capacity covers the modeled groups, and no room, instructor or student-block time overlaps occur within a term.

## Backup and safety

Private directory: `/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation/`.

- `veritas_portal.sql`: fresh pre-population backup, 20,658 bytes; SHA-256 `3c3199736b0378ecda7cd4df95ff1fb0515c12a94a75e50d0695d5b04921e474`.
- `veritas_portal-populated.sql`: populated snapshot, 2,824,410 bytes; SHA-256 `00e624c03fae2176e689417cb1824e5d52e7d1b4094254ae879e14bb63cf4fa4`.
- Both snapshots include all 19 application table definitions and all rows, including the preserved account, with private filesystem permissions outside Apache’s document root. Complete INSERT counts and unchanged source fingerprints were checked. The earlier full restore-tested backup and its checksum were verified before insertion.
- No fresh restore into another database was performed in Phase 6, respecting the instruction to leave other databases unchanged. Current snapshots are checksum/count/schema verified; the previous full restore test is historical evidence rather than a claimed new restore test.
- Pre/post CREATE TABLE definitions match after removing only normal AUTO_INCREMENT values. No table drop, truncate, schema rebuild, migration removal, foreign-key disabling or authentication-table mutation occurred.
- An exclusive maintenance lock and serializable insertion transaction protect the explicit population operation. Initial fingerprints must match the clean snapshot; the target must be loopback `veritas_portal`.
- The original user data/schema fingerprint matches the pre-population backup, including credentials, role, created date and last-login fields. Authentication still works without changing those fields.
- Automatic seeding remains disabled. The legacy sample seeder was not called. No production or other database was modified.

## Explicit population and rollback commands

```sh
# Preview only (the existing identical batch now reports NO_OP):
/Applications/XAMPP/xamppfiles/bin/php tools/populate_demo.php

# Explicit insertion; identical existing batch remains a no-op:
/Applications/XAMPP/xamppfiles/bin/php tools/populate_demo.php --execute --confirm=veritas_portal

# Preview rollback; removes nothing:
/Applications/XAMPP/xamppfiles/bin/php tools/populate_demo.php --rollback

# Only if deliberately returning to the clean pre-population state:
/Applications/XAMPP/xamppfiles/bin/php tools/populate_demo.php --rollback --execute --confirm=veritas_portal
```

The idempotency check passed: a repeated population command inserted zero rows. The rollback dry run passed and identifies exactly the owned records. Executed rollback was intentionally not tested so the presentation remains populated. It checks every batch fingerprint and will stop if anyone edits/adds records; it will not silently delete subsequent work. It deletes children before parents in one transaction and restores the original clean metadata while preserving users. Normal identity counters are not reset. Back up later edits before attempting any selective/manual recovery. Keep all additional presentation records fictional; do not mix real student information into this labeled batch.

## Model preservation and verification

The existing Decision Tree, its feature definitions/class ordering, official UCI 697 archive and original CSV remain unchanged by hash. **No model training occurred in Phase 6.** No UCI rows were imported into academic tables and no synthetic academic records were used to evaluate model accuracy.

- Actual held-out dropout recall: **65.49%**.
- Actual held-out macro F1: **0.6137**.
- Evaluation split and methodology remain the existing stratified 3,539/885 split and five-fold training CV.
- PHP inference matches the saved sklearn model on 4,424 source records and 51 threshold cases; saved metrics reproduce exactly.
- Dropout, Enrolled and Graduate examples, recommendations, native/input validation and simulated service-failure handling pass browser checks at all three viewport sizes.
- Academic college/year/semester filters do not turn the independent public examples into university student predictions. Teaching ratings do not enter the model. Leaf shares are uncalibrated and local accuracy remains unverified.

## Test results

| Requirement/check | Status | Evidence |
|---|---|---|
| Data and integrity assertions | PASS | 142 checks, including every table count/fingerprint, account preservation, response-derived history and 23 foreign keys |
| Unique/relationship constraints | PASS | Transactional inserts accepted; no orphaned keys; repeat run adds no duplicates |
| Schedule validity | PASS | Zero room, faculty or student-block overlaps; current conflict detector also passes |
| Five primary KPIs | PASS | Calculated values shown above; no hardcoded dashboard totals |
| Three or more visualizations | PASS | Enrollment bars, grade donut, risk line, SETE dimensions/distributions/history, admissions funnel |
| Multiple interactions and alerts | PASS | College/year/semester scope, chart legend, sample picker, applicant chips, mobile drawer, overdue-grade and urgent-case notices |
| Semester/year/college filters | PASS | Historical cohorts show 576 and current cohort 768; college cohort 192; historical mutations are blocked |
| SETE confidentiality and descriptive logic | PASS | Anonymous section-linked rows, participation bounded by invitations, histories computed from responses; no personnel automation |
| Academic workflows | PASS for data/views/form controls | Populated enrollment/class/grade/room/reference data and scheduling, student, faculty, intervention controls render correctly. Source browser tests do not commit CRUD edits |
| Browser layouts and navigation | PASS | 54 current/historical route–viewport renderings; 204 assertions at 1440×1000, 820×1180 and 390×844 |
| Browser exceptions/network | PASS | No unhandled JS exceptions or unexpected HTTP failures. CSV downloads cause expected navigation cancellation only after file content/label verification; 422 and intercepted 503 are deliberate negative tests |
| Actual Apache/account/API regression | PASS | 71 HTTP checks; all source rows/schema unchanged by tests, original login works, private files blocked |
| Permission/CSRF/unit checks | PASS | Existing role matrix and malformed-token handling preserved; no new accounts created for Phase 6 testing |
| Model parity and artifacts | PASS | Original model/UCI hashes unchanged; no retraining; 4,424 rows plus 51 threshold cases |
| Idempotency | PASS | NO_OP, zero duplicate inserts |
| Rollback plan | PASS — dry run | Fingerprint-guarded owned-batch plan; destructive execution not performed |
| Syntax/whitespace | PASS | PHP, Python, JavaScript syntax and git diff whitespace checks |
| Long-bond brief | PASS in emulation | Browser PDF export verified as one 612×936-point long-bond page; physical printing is not verified |
| Physical devices and other engines | NOT VERIFIED | Chrome viewport/touch emulation only |
| Local real-world prediction accuracy | NOT VERIFIED | External UCI evaluation only; requires compatible local outcomes/independent validation |
| Historical profile/intervention versioning | NOT IMPLEMENTED | Existing schema stores current profiles/interventions, so historical displays explicitly limit those claims |

Browser screenshots (all academic records shown are DEMONSTRATION DATA):

- [Populated desktop overview](browser/screenshots/desktop-index.png)
- [Presentation brief](browser/screenshots/desktop-index-brief.png)
- [Long-bond print preview](browser/screenshots/long-bond-print-preview.png)
- [Tablet dashboard](browser/screenshots/tablet-index.png)
- [Mobile dashboard](browser/screenshots/mobile-index.png)
- [Historical semester](browser/screenshots/desktop-historical.png)
- [Historical risk module](browser/screenshots/mobile-historical-at-risk.png)
- [Mobile prediction and actual metrics](browser/screenshots/mobile-predictions.png)

Total screenshots: 70. Machine evidence: [dataset/KPI validation](validation.json), [browser results](browser/browser-results.json), [Apache regression](source-http-results.json), [backup verification](backup-verification.json), [idempotency](idempotency.json), [rollback plan](rollback-dry-run.json), [summary](summary.json).

## Fixes and presentation notes

- Added explicit provenance across login, pages, print and CSV without changing schemas or accounts.
- Added working year/semester context with period-specific caches, correct cohort/section-year projection, selected-term risk snapshots and read-only historical protection.
- Fixed cross-semester room comparisons that surfaced when historical data became available; schedules in different terms no longer falsely conflict.
- Corrected generated transferee GWA scales and applicant timestamps before final validation; these were owned fictional rows only. Counts and the model were unaffected.
- Retained unavailable historical intervention labels instead of inventing intervention archives, and suppressed new operational alerts for old terms.
- Preserved inter-panel spacing in the full overview and tightened print spacing for the populated brief.
- The frozen date is intentional for repeatable presentation. Demonstration percentages and patterns describe the fictional scenario, not actual student or faculty performance.
- Physical printing, other browser engines/devices, external hosting and production deployment are not verified. The URL is local to this computer. No claim of official institutional data or locally validated predictive performance is made.
