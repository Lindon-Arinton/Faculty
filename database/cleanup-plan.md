# BAT 405 database audit and proposed cleanup

Historical pre-cleanup plan. Conditionally approved and executed on 17 November 2026. Exactly 88,668 non-account records were removed; the original account and all table structures were preserved. See `analytics/IMPLEMENTATION_REPORT.md` for final implementation and validation results. The counts below describe the approved pre-cleanup snapshot.

## Environment and architecture

- Local XAMPP instance, database `veritas_portal`, MariaDB 10.4.28; configured host is loopback. This proposal applies only to this local instance.
- Plain PHP 8.2.4, PDO/MySQL, server-rendered pages, shared PHP chart helpers and browser scripts. No package manager or automated test suite was found.
- Existing pages: overview, admissions, schedule, enrollment, grade encoding/submission, grade-based risk monitoring/interventions, and faculty evaluation summaries.
- `includes/db.php` initializes schema and default accounts and invokes automatic sample-data seeding if `meta` has no records. The audit bypasses this initializer entirely.
- Authentication uses PHP sessions, password hashes, CSRF protection and session-based login throttling. The one account has a nonempty recognized password hash and role. No account-linked academic foreign keys, separate role/permission tables, or database-backed session tables were found.
- Roles are stored/displayed, but page and action guards only require a signed-in session. Faculty/department scope enforcement is an existing missing capability; do not claim the portal already implements it.
- Existing at-risk classification is a deterministic query over failing/INC grades and back subjects; it is not a trained predictive model.
- SETE responses are anonymous and reference sections; existing faculty summaries/history are descriptive and must stay separate from external student predictions.

## Verified backup

- Backup: `/Users/johnlukereynon/Faculty-backups/bat405-20261117-precleanup/veritas_portal.sql`
- Size: 15,561,586 bytes.
- SHA-256: `f859ed753b1927c8c3509693a605cd94f2fcb7078f1f9e6f2ee83d8e348329ae`.
- Restored successfully into a new isolated schema on the local server: `bat405_verify_20261117_021709_634771`. That schema is retained; no existing schema was overwritten or dropped.
- All 19 tables matched source schema fingerprints, row counts and complete row-data fingerprints after restore. Source fingerprints were checked before/after backup and again after restoration.
- Backup includes users, all application tables, triggers, routines and events. The source has zero triggers, routines or events. This is an application-database backup, not a backup of server-wide MySQL grants or filesystem sessions.
- Backup directory permissions are 0700 and SQL permissions are 0600, outside the web root. Private full audit reports and a verification manifest are beside the SQL file.
- Database integrity: 23 foreign keys checked, zero orphaned rows, all tables InnoDB; no unclassified tables or external account relationships found.

## Deletion dry run

| Table | Existing rows | Proposed deleted rows | Preserved rows |
|---|---:|---:|---:|
| `applicant_requirements` | 16,200 | 16,200 | 0 |
| `applicants` | 3,240 | 3,240 | 0 |
| `colleges` | 6 | 6 | 0 |
| `courses` | 218 | 218 | 0 |
| `curriculum` | 370 | 370 | 0 |
| `enrollments` | 32,481 | 32,481 | 0 |
| `evaluation_responses` | 25,999 | 25,999 | 0 |
| `faculty` | 186 | 186 | 0 |
| `faculty_rating_history` | 186 | 186 | 0 |
| `interventions` | 276 | 276 | 0 |
| `meetings` | 2,459 | 2,459 | 0 |
| `meta` | 6 | 6 | 0 |
| `programs` | 16 | 16 | 0 |
| `risk_snapshots` | 24 | 24 | 0 |
| `rooms` | 115 | 115 | 0 |
| `sections` | 1,042 | 1,042 | 0 |
| `students` | 5,842 | 5,842 | 0 |
| `terms` | 2 | 2 | 0 |
| `users` | 1 | 0 | 1 |

**Total proposed deleted records: 88,668. Existing accounts preserved: 1.**

All database tables, indexes, primary keys, foreign keys and auto-increment state remain. No TRUNCATE, DROP, account recreation or disabled foreign key checks are proposed.

The six `meta` records proposed for removal are sample-specific: `as_of`, `intake_label`, `prev_intake_applications`, `prev_term_enrollment`, `enrolled_active`, `seeded_at`. Preserve any new/unrecognized settings. Add an explicit clean-state marker so empty academic data does not trigger reseeding.

Reference catalogs (`colleges`, `programs`, `rooms`, `courses`, `curriculum`, `terms`) contain seeded academic demonstration records and are included in this proposal. Their structures remain. With these catalogs empty, new academic transactions require valid catalog setup; the current UI has no complete catalog-administration workflow. Forms must show a clear setup-required state and avoid invalid submissions. If the university wants to retain these catalogs instead, revise the deletion scope before execution.

## Relationships and transaction order

`colleges` is the parent of programs, courses, rooms, faculty and risk snapshots; programs/courses feed curriculum and sections. Students depend on programs and optionally faculty advisers. Sections depend on terms, programs, courses and faculty. Enrollments depend on students/sections; meetings on sections/rooms; evaluations on sections; interventions on students; faculty history on faculty/terms; applicants on programs; requirements on applicants. `users` has no declared foreign keys.

Delete children before parents in this order:

`applicant_requirements` → `applicants` → `faculty_rating_history` → `evaluation_responses` → `risk_snapshots` → `interventions` → `enrollments` → `meetings` → `sections` → `students` → `curriculum` → `courses` → `faculty` → `rooms` → `programs` → `colleges` → `terms` → known sample `meta` keys.

## Execution plan after approval

1. Implement and test empty-state handling and an explicit sample-data mode, keeping the current source database untouched. Automatic initialization must never repopulate a deliberately cleaned database. Preserve the existing seeder compatibility fix.
2. Rehearse the proposed transaction on the restored verification copy. Compare complete user-row fingerprints and table definitions before/after. Validate foreign keys and all authenticated pages against empty data.
3. Refresh the source audit and verified backup immediately before cleanup. Abort on new tables, unknown metadata, changed accounts/counts, new dependencies, triggers, events, invalid relationships or nontransactional storage. Restrict execution to this approved local database and prevent concurrent academic writes during cleanup.
4. Execute explicit DELETE statements in one transaction, with foreign key enforcement enabled. Never call `seed_database()` or its drop-and-recreate schema path. Preserve every user column byte-for-byte, including credentials, roles and login metadata. Roll back on any mismatch or failure.
5. Remove only the six known demo metadata records and insert the clean-state marker. Verify zero remaining targeted records and identical user data before commit. Invalidate application caches after commit.
6. Test account login on the isolated copy first; actual source login updates `last_login_at` and may rehash credentials, so report those expected authentication writes separately from cleanup. Do not reset passwords or claim all accounts were authenticated without valid credentials.
7. Test all pages, actions, empty-state charts, authentication redirects, CSRF, and authorization. Retain backup and verification evidence. Report actual deleted/preserved counts.

## Predictive implementation planned

- Download the official UCI dataset 697 from UCI, record its source/checksum/license, validate all 4,424 records and target labels. Keep files outside institutional student/enrollment/SETE tables.
- Four inputs only: `Curricular units 1st sem (enrolled)`, `Curricular units 1st sem (approved)`, `Curricular units 1st sem (grade)`, `Curricular units 1st sem (evaluations)`. Verify these exact columns from the downloaded official file.
- Seven modeling steps: define the decision and prediction time; obtain/understand data; validate/prepare data; select the four first-semester features; split data; fit/tune a DecisionTreeClassifier with stratified cross-validation on training data only; evaluate the locked test set and integrate/monitor responsibly.
- Stratified 80/20 split with a fixed seed; bounded tree depth/leaf-size tuning on training folds. Exclude all second-semester and SETE variables. Report actual dropout recall, macro F1 and a labeled confusion matrix; compare a simple baseline. No performance results exist yet.
- Save the scikit-learn model plus feature/class metadata and a safe integration representation. Add an authenticated prediction panel within the existing dashboard design, label external demonstration predictions, and provide advisory support suggestions.
- UCI outputs are eventual Dropout/Enrolled/Graduate outcomes, not individual-subject failure or causal explanations. First-semester data supports end-of-first-semester predictions only. Philippine grades cannot be substituted for the source grade scale without validated mapping; number of grade rows is not automatically equivalent to number of evaluations.
- Public Portuguese data supports demonstration, not demonstrated accuracy for this university. Existing generated seeder records cannot validate real-world model accuracy. SETE ratings must not be treated as automatic personnel decisions.
- Python scikit-learn is not installed in the current default Python environment; use a project-isolated environment and record dependencies during implementation.

## Approval requested

Approve deleting the 88,668 listed non-account records from the local `veritas_portal` database, including seeded reference catalogs and six demo metadata records, after the preflight safeguards and verification-copy rehearsal pass. Preserve the existing user account and all table structures. No destructive cleanup has been executed.
