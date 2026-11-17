# BAT 405 implementation and testing report

Follow-up: [Phase 5 final QA report](qa/PHASE5_REPORT.md) adds completed browser testing, screenshots and readiness findings. Visual limitations below describe the earlier implementation phase.

Completed locally on 17 November 2026 (Asia/Manila). Portal: http://localhost/Faculty/. No production database was accessed or changed.

## Delivered result

The existing portal retains its branding, navigation and academic workflows. It now has enforced server-side permissions, functional empty-state dashboards, administrator reference setup, a trained first-semester Decision Tree with PHP/API integration, and separate descriptive SETE analytics. The approved cleanup removed exactly 88,668 non-account records. The single original account and every original account field are unchanged; all 19 original table structures, indexes and foreign keys are preserved.

Academic tables are empty. One new `meta` clean-state marker remains alongside the one preserved account. No university student, enrollment, grade, faculty evaluation, risk-history or applicant records were retained. The 12 external demonstration feature records are filesystem artifacts, not university database records.

## Existing architecture and enhancements

| Component | Existing behavior | Implemented change |
|---|---|---|
| Database | PDO/MySQL connection automatically initialized accounts and sample records | Connections are read-only with respect to initialization; no automatic account creation, database/schema rebuilding or seeding |
| Authentication | PHP sessions, password verification, CSRF and session-based login throttling | Existing mechanisms retained; successful login never updates account fields; persisted role refreshed each protected request |
| Authorization | Role labels but no enforced permissions | Explicit fail-closed policy on all pages, actions, setup and prediction API |
| Overview | Enrollment, grade submission, admissions, schedule, risk and SETE summaries | Empty-data guards, unavailable baselines, clear setup state and a separate predictive demonstration panel |
| At-risk monitoring | Rules over failing/INC grades and back subjects | Retained; distinct from the learned eventual-outcome model |
| Academic forms | Dependent on seeded reference catalogs | Missing selections disabled, crafted submissions checked server-side; reference setup added for authorized administrators |
| Faculty evaluations | Anonymous section-linked responses and rating/category summaries | Retained and regression-tested; empty scores and missing historical baselines are explicitly unavailable; responsible interpretation explained |
| Predictive analytics | Absent | Official UCI data, sklearn training/evaluation, saved model, verified PHP inference, authenticated JSON API and interactive/server-rendered prediction page |

No schema migration was needed. The pre-existing schema has no user-to-student/faculty/college foreign keys and no separate permissions or database-session tables. No such links were guessed from names or email addresses.

## Backup and restoration evidence

- Private verified backup: `/Users/johnlukereynon/Faculty-backups/bat405-20261117-approved/veritas_portal.sql`.
- Size: 15,561,586 bytes; directory mode 0700 and SQL mode 0600; outside Apache web root.
- Backup SHA-256: `4e25391e7f5486924d872fdf0fb3cd8fdcc4b0f35fb11af66809c42735fd3ae7`.
- Full restore succeeded into `bat405_verify_20261117_022504_271703`, a newly created isolated verification copy. Every table row count, complete row-data fingerprint and SHOW CREATE TABLE fingerprint matched the source before rehearsal.
- All 23 declared foreign keys were audited; zero orphaned rows. All 19 tables use InnoDB. No triggers, routines, events, unclassified tables or cross-database account dependencies were found.
- Full application-database backup includes all tables and account records. It is not a server-wide MySQL grant or filesystem-session backup; those were not changed.
- A previous pre-cleanup backup is also retained at `/Users/johnlukereynon/Faculty-backups/bat405-20261117-precleanup/`.
- Verification schemas are deliberately retained for audit/recovery. They are not used by the actual Apache portal.

## Cleanup safeguards and results

The dry run matched the approved count. The rehearsal passed before source execution, and 80 pre-cleanup HTTP checks passed on the cleaned copy. Source preflight required the exact verified snapshot, original account fingerprint, all schemas, valid backup checksum, passing rehearsal and passing isolated tests.

Cleanup used child-before-parent DELETE statements in one serializable InnoDB transaction, with foreign key checks enabled. All table rows were locked and rechecked against the snapshot; account/schema/count mismatches would roll back. An exclusive application maintenance lock prevented concurrent portal database requests. No TRUNCATE, DROP, account recreation, credential reset, disabled foreign keys or auto-increment reset was performed.

| Table | Approved records removed | Final records |
|---|---:|---:|
| `applicant_requirements` | 16,200 | 0 |
| `applicants` | 3,240 | 0 |
| `colleges` | 6 | 0 |
| `courses` | 218 | 0 |
| `curriculum` | 370 | 0 |
| `enrollments` | 32,481 | 0 |
| `evaluation_responses` | 25,999 | 0 |
| `faculty` | 186 | 0 |
| `faculty_rating_history` | 186 | 0 |
| `interventions` | 276 | 0 |
| `meetings` | 2,459 | 0 |
| `meta` | 6 | 1 |
| `programs` | 16 | 0 |
| `risk_snapshots` | 24 | 0 |
| `rooms` | 115 | 0 |
| `sections` | 1,042 | 0 |
| `students` | 5,842 | 0 |
| `terms` | 2 | 0 |
| `users` | 0 | 1 |

**Total records deleted: 88,668. Accounts deleted: 0. Accounts preserved: 1. Orphaned rows: 0.**

Only known sample metadata keys were removed: `as_of`, `intake_label`, `prev_intake_applications`, `prev_term_enrollment`, `enrolled_active`, `seeded_at`. A new `case_study_cleaned` marker records the intentional clean state. Database connections do not use marker absence to seed, so removing or emptying metadata cannot restore sample data.

The final source audit matched every original user field and every original table schema against the pre-cleanup backup after all HTTP tests. The account successfully logged into the actual Apache portal using its existing credential; its password hash, role, last-login value and other fields remain byte-for-byte unchanged.

Historical sample export `database/veritas_portal.sql` remains a dormant legacy file, blocked from web download. It is not executed by the application and must not be imported into the cleaned portal. Full recovery backups necessarily retain removed records outside the application.

## Server-side permission policy

| Exact stored role | Authorized operations |
|---|---|
| `University-wide access` | Existing institutional views and academic mutations, SETE views/faculty mutations, reference setup, public demonstration prediction |
| `Academic Viewer` | Institutional/SETE views and exports; demonstration prediction; no mutations or setup |
| `Faculty` | External demonstration prediction only |
| Unknown or unconfigured role | Denied |

The source has only its original `University-wide access` account. No new source accounts or role records were created. Temporary role fixtures on the isolated copy verified both allowed and denied routes/actions and persisted-role revocation during an existing session. User roles cannot be escalated by editing the session role field. UI mutation controls are hidden or disabled for readers; server checks enforce the policy independently.

Institution-specific faculty or departmental access requires an explicit, validated account relationship and policy. Because those relationships do not exist, the implementation does not grant unscoped institutional access to Faculty or infer Dean/Program Chair privileges.

## Dataset and preparation

[Official UCI dataset 697](https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success): Predict Students’ Dropout and Academic Success, Realinho, Vieira Martins, Machado and Baptista (2021), DOI https://doi.org/10.24432/C5MC89, CC BY 4.0. This is external Portuguese higher-education data, not records from this university.

- Validated 4,424 rows and 37 CSV columns (36 potential predictors plus target).
- No missing values and no exact full-record duplicates. Numeric values, nonnegative counts, integer count fields, target labels, grade bounds and approved/enrolled consistency were validated.
- Outcomes: Dropout 1,421; Enrolled 794; Graduate 2,209.
- Archive SHA-256: `e90e55fd65ec462ae283ebeb2cca409319e3460ed898d8754f62fb35cc83a65d`.
- CSV SHA-256: `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae`.
- Raw archive/data are cached in `analytics/data/`, protected from direct Apache downloads and never imported into institutional tables. `first-semester.csv` contains only four predictors and the target. A checksum mismatch stops subsequent training.

| Model input | Meaning and observed source range |
|---|---|
| `Curricular units 1st sem (enrolled)` | First-semester units enrolled; 0–26 |
| `Curricular units 1st sem (approved)` | First-semester units passed; 0–26 |
| `Curricular units 1st sem (grade)` | First-semester average grade; 0–18.875 |
| `Curricular units 1st sem (evaluations)` | First-semester evaluations; 0–45 |

Curricular units are subjects/course units, not summed credit hours. Evaluations are academic assessment occurrences, not SETE responses. Grades use the source 0–20 system; 0 may indicate no earned grade. The API conservatively restricts inputs to observed source support. A Philippine 1–5 GWA or a count of encoded grades cannot be substituted without a validated definition and mapping.

## Seven analytics modeling steps

1. **Define the problem and decision.** After the first semester, classify eventual Dropout, Enrolled or Graduate outcomes to support advising. This is predictive classification, not an admission-time forecast, a subject-failure label, a causal explanation or an automatic disciplinary decision.
2. **Acquire and understand data.** Download dataset 697 from the official UCI host, retain attribution and checksums, inspect outcome imbalance, and keep its records separate from university records.
3. **Validate and prepare data.** Check source shape, missingness, exact duplicates, numeric domains and consistency. Prepare only four first-semester predictors and the target; no imputation, demographic encoding or grade-scale conversion is needed.
4. **Select features and prevent leakage.** Use the four predetermined first-semester columns. Exclude every second-semester, SETE, demographic and financial field. These inputs must already exist at prediction time. Source min/max values are descriptive input-validation bounds, not fitted preprocessing or model-selection inputs.
5. **Split data.** Use a stratified 80/20 split with fixed random seed 405: 3,539 training rows and 885 held-out test rows. Preserve split indices; verify no overlap. No test records are fitted into the saved model.
6. **Train and select the model.** Search 40 DecisionTreeClassifier candidates on five stratified shuffled training folds: max depth 2–6, minimum leaf size 10/20/40/80 and unweighted/balanced classes. Select by mean macro F1, then refit the selected estimator on the training split. The test set is not used for selection.
7. **Evaluate, integrate and monitor.** Evaluate the selected fitted model on the untouched test split using dropout recall, macro F1 and a labeled confusion matrix. Compare a majority-class baseline, persist the model/export/results, verify cross-language inference and expose advisory demonstration results. Future institutional deployment requires compatible local data, local outcome validation, monitoring and review.

## Actual model evaluation

Selected model: `DecisionTreeClassifier(random_state=405, max_depth=6, min_samples_leaf=40, class_weight="balanced")`; fitted tree has depth 6 and 42 leaves.

Selected training CV macro F1: **0.602855**, fold standard deviation **0.016925**. Selected CV dropout recall: **0.649061**. These CV scores were used for hyperparameter selection; they are not independent evaluation estimates.

| Metric | Decision Tree held-out test | Majority-class baseline |
|---|---:|---:|
| Dropout recall | 65.49% | 0.00% |
| Macro F1 | 0.613675 | 0.222055 |
| Accuracy, supplementary | 66.67% | 49.94% |

Dropout recall measures how many eventual dropouts the model identifies, which matters for missed-support cases. Macro F1 averages F1 equally across all outcome classes, preventing the largest Graduate class from dominating the evaluation.

Confusion matrix: **rows = actual outcomes, columns = predicted outcomes**.

| Actual / Predicted | Dropout | Enrolled | Graduate |
|---|---:|---:|---:|
| Dropout | 186 | 62 | 36 |
| Enrolled | 55 | 70 | 34 |
| Graduate | 22 | 86 | 334 |

The model identifies 186 of 284 eventual dropouts and misses 98. It predicts Dropout for 77 students whose recorded outcomes are Enrolled or Graduate. Enrolled-class precision is 32.11%, recall 44.03% and F1 0.3714. These results support an educational prototype, not reliable automatic individual decisions. No synthetic university data was used to train the model or claim real-world accuracy.

## Dashboard and API integration

- The overview includes a clearly separate demonstration panel for 12 public external feature records, with actual held-out evaluation metrics. These counts do not enter institutional enrollment/risk/SETE KPIs or college filters.
- `predictions.php` loads a public sample or accepts four compatible values, displays predicted outcome, an academic-support indicator, a recommendation and uncalibrated tree leaf class shares. Input changes clear stale results. A server-rendered POST fallback supports operation without JavaScript.
- `api/predict.php` enforces authentication, persisted-role permission, POST, JSON content type, CSRF, payload-size limits, exact input names, finite numeric values, integer counts, source ranges and passed ≤ enrolled. It rejects student identifiers and second-semester fields. Responses are not cached or saved to academic tables.
- Advisory responses: Dropout → priority supportive advising; Enrolled → progress review; Graduate → routine support, with no graduation guarantee. Predictions never automatically create interventions, modify grades or influence faculty ratings.
- Runtime PHP uses a checksum-verified JSON export of the same fitted sklearn tree. It reproduces sklearn float32 input conversion and class ordering. No pickle loading, Python subprocess or model service is required at request time.
- Saved artifacts: `decision_tree.joblib`, `tree.json`, `metrics.json`, `tree-rules.txt`, `demo-samples.json`, dataset checksum, split indices and validation results. Training dependencies are pinned in `analytics/requirements.txt`.

## SETE separation and interpretation

SETE remains descriptive: faculty-level response counts, participation, dimension scores, rating bands, distributions and history. Responses link anonymously to sections, not to identified students. Tests created one disposable anonymous section response on the isolated copy and verified the expected descriptive score, then removed the fixture. Source SETE tables remain empty.

Missing evaluation scores and historical baselines are displayed as unavailable. Scores must be interpreted alongside response counts, participation, course context, peer review and other evidence. The model neither uses SETE inputs nor joins external UCI outcomes to faculty records. Ratings do not automatically determine personnel actions.

## Testing and validation evidence

| Check | Final result |
|---|---|
| Backup full restore | PASS: all 19 table data/schema fingerprints and counts match |
| Cleanup rehearsal | PASS: exactly 88,668 deleted, original account unchanged, zero orphaned rows |
| Source cleanup | PASS: exactly approved count deleted; all account fields and schemas preserved |
| Isolated HTTP/form/API integration | PASS: 170 checks |
| Actual LOCAL Apache smoke tests | PASS: 71 checks |
| Permission/unit tests | PASS: role policy, action guards, empty charts, zero denominators, blocked seeder |
| Model tests | PASS: saved metrics reproduced; disjoint split; four first-semester inputs only |
| PHP/sklearn parity | PASS: all 4,424 source rows and 51 threshold-adjacent cases |
| Syntax and formatting | PASS: 34 PHP files; all Python sources; prediction JavaScript syntax; git diff whitespace check |
| Final source audit | PASS: exact original account fields and all original schemas match pre-cleanup backup; only 1 user + 1 clean marker; 23 valid foreign keys |
| Visual browser QA | NOT COMPLETED: browser access host unavailable |

The isolated suite covers original-account login, unauthenticated redirects, allowed/denied role routes and actions, session permission revocation, CSRF, empty filters/pages, setup prerequisites, creation of colleges/programs/courses/rooms/term, faculty/student/class/applicant workflows, grade submission, existing grade-based risk monitoring, interventions, SETE calculation, exports and model API validation. Temporary test accounts and academic fixtures were created only in the approved verification copy and removed afterward; its original account fields remain unchanged.

Actual Apache tests verify the preserved login, existing pages, dashboard prediction panel, API and server-rendered prediction fallback, zero recreated academic data and blocked downloads of SQL, CSV, model artifacts, tools, tests, maintenance lock and `.git/config`. A discovered hidden-file exposure was fixed and retested. All source database tables and schemas were unchanged by HTTP tests.

Private detailed evidence is beside the verified backup: `audit-before.json`, `audit-restored.json`, `rehearsal.json`, `precleanup-tests.json`, `cleanup-result.json`, `final-integration-tests.json`, `audit-final-source.json`. Nonconfidential summaries are in `analytics/artifacts/` and protected from web download.

## Remaining limitations and manual checks

1. Visual rendering, mobile layout and browser-side sample-selection/fetch interactions were not visually exercised because the browser tool was unavailable. HTTP routes, API behavior, server fallback and JavaScript syntax passed; manually review the page in a browser.
2. The model has no validated local-university performance. It must remain labeled external demonstration until compatible local measurements and actual eventual outcomes support independent local validation. Its moderate performance and weak Enrolled classification must be reported honestly.
3. Local student prediction is not automatically derived from the existing grade tables: grade scales and evaluation-count definitions differ. No fabricated mapping or university predictions were inserted.
4. Institutional reference catalogs are intentionally empty. An authorized administrator must enter official references before new academic transactions. Existing navigation and workflows remain available with explicit setup states.
5. Faculty/department/student-specific record access needs explicit account relationships before those roles can receive institutional permissions. Unknown roles fail closed. The existing unrestricted institutional role retains its original access.
6. Trained leaf shares are uncalibrated; they are not verified individual dropout probabilities or causal effects. Review all alerts as support opportunities, without automatic adverse academic or personnel decisions.

## Reproduction and review

See the root README for training and test commands. The initial isolated training environment is `/private/tmp/faculty-bat405-venv`; it is not required for portal runtime and may be recreated from pinned dependencies. Full source audits and private backups remain outside the web root. The cleanup tool refuses to rerun against the changed clean snapshot. No production or other pre-existing database was modified.
