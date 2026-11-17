# BAT 405 Phase 5 — Final system QA and dashboard readiness

Date: 17 November 2026 · Asia/Manila · System: http://localhost/Faculty/

**Verdict: PASS for the tested local case-study dashboard, prediction and security workflows, with the limitations listed below. No cleanup, reseeding, model retraining or source schema changes were performed.**

The source database and every original account field are unchanged from the pre-QA snapshot. Its academic tables remain empty, with one preserved user and one existing clean-state metadata row. All five original trained-model artifacts retain their hashes. The only database writes were disposable fixtures in `bat405_verify_20261117_022504_271703`; those fixtures were removed and the preserved account was rechecked.

## Requirement-by-requirement results

| Phase 5 requirement | Status | Evidence/result |
|---|---|---|
| Browser automation | PASS | Playwright 1.56.1 with isolated headless Chrome 158.0.8127.37; no personal browser profile used |
| Desktop layout | PASS | 1440×1000 CSS pixels; all applicable pages rendered, captured and checked |
| Tablet layout | PASS | 820×1180 CSS pixels with touch/mobile emulation |
| Mobile layout | PASS | 390×844 CSS pixels with touch/mobile emulation |
| All existing pages and navigation | PASS | Overview, brief, applicants, schedule, enrollment, grades, risk, SETE, reference setup, search, prediction and missing-class redirect; populated class checked on isolated copy |
| Layout overflow | PASS | No page-wide horizontal overflow in 75 route/viewport renderings; tables scroll within cards |
| Readable mobile metrics | PASS | Corrected three-column prediction cards to a single readable column; floating help no longer obscures mobile content |
| JavaScript exceptions | PASS | Zero uncaught exceptions in recorded browser runs |
| Network and console checks | PASS | Zero unexpected HTTP/console errors; deliberate 422 and simulated 503 responses were verified separately; see icon cancellation note below |
| Screenshots | PASS | 101 final PNGs plus baseline/diagnostic evidence; populated fixtures labeled explicitly |
| Five institutional KPI cards | PASS | Active classes, enrolled student register, grades pending, observed at-risk students, SETE average score |
| Three working visualizations | PASS | Enrollment bars, grade-submission donut and SETE dimension bars; populated and value-checked only on isolated data |
| Two or more interactive features | PASS | College/program scope, applicant chips, donut legend, chart tooltips, help dialog, mobile drawer and prediction sample selection |
| Warning mechanism | PASS | Missing setup warning on source; overdue-grade threshold warning exercised on isolated fixtures |
| Honest empty states | PASS | No empty 0% submission/conversion, invented current-term deadline or synthetic zero-risk trend; unavailable assessments shown explicitly |
| Institutional/demo separation | PASS | UCI counts remain in a separately labeled panel, do not enter institutional KPIs and remain unchanged by college filtering |
| SETE calculations | PASS | Five dimensions verified; one response over two eligible invitations yields 50%, including the faculty with zero responses |
| Valid prediction inputs | PASS | Four first-semester features; all three outcomes exercised through browser UI at all three viewport sizes |
| Recommendations and validation | PASS | Outcome-specific support advice, native range checks, relational input validation, stale-result clearing and service-failure recovery |
| API authentication/authorization/CSRF | PASS | Anonymous 401, forbidden-role 403, wrong CSRF rejection; malformed fields fail safely without PHP warnings |
| API request validation | PASS | Invalid media type, method, JSON, size, identities, second-semester/extra fields and unsupported values rejected |
| Model parity and metrics | PASS | 4,424 source rows plus 51 threshold cases match sklearn; saved test metrics reproduced, model files unchanged |
| User preservation | PASS | Exact data fingerprint for all account fields unchanged; original credential still logs in |
| Role permissions | PASS | Existing full-access role retained; viewer mutations denied; unlinked Faculty denied institutional data; unknown roles denied; revocation applies to existing sessions |
| Academic setup/workflows | PASS | Isolated college/program/course/room/term setup, faculty, students, classes, grades, interventions, applicants, SETE fixture and exports tested |
| No source data mutations | PASS | Full table data/schema fingerprint comparison, not just account counts |
| No automatic sample records | PASS | Source remains empty; disabled seeder unit tests pass; no seed/cleanup tool executed in Phase 5 |
| Regression suite | PASS | 178 isolated HTTP checks; 71 actual Apache checks; permission/unit, model and syntax checks |
| Optional one-page brief | PASS | `index.php?view=brief` reuses existing data/components; three charts plus five KPIs; long-bond print-emulated layout fits printable height |
| Historical year/semester and department filtering | FAIL | Not implemented. Current term is explicitly read-only; college and program drill-down work. This is a remaining feature gap, not a passing historical filter |
| Physical devices / Safari / Firefox | NOT VERIFIED | Viewport/touch emulation is not testing actual hardware or other browser engines |
| Physical printed output and submission packaging | NOT VERIFIED | Browser print preview checked; physical printer, paper, cover names and final submission were not exercised |
| Actual university prediction accuracy | NOT VERIFIED | External UCI evaluation only; no local outcome data or validated local grade mapping |
| Remote assessor access | NOT VERIFIED | URL is localhost; no public deployment or production change was authorized/performed |

## Evidence and test scope

- Source browser run: **36 route/viewport combinations**, **108 interaction assertions**.
- Isolated populated browser run: **39 route/viewport combinations**, **141 interaction assertions**.
- Total: **75 page renderings, 249 interaction assertions, 101 final screenshots**.
- Captures cover empty source states and populated test states. Full-page screenshots allow review of the complete page, while horizontal tables deliberately retain their own scroll areas. Account sidebar details are redacted; populated screenshots carry an isolated-fixture label.
- No unhandled JavaScript exceptions or broken HTTP resources were found. Chrome cancelled 2 favicon/image requests with `net::ERR_ABORTED` during test navigation; an explicit request to the same live server returned HTTP 200 and the exact logo bytes. These are recorded as verified browser cancellations, not silently discarded. Source Apache browser run had zero failed requests.
- Expected negative cases are retained in browser logs: real input validation returns 422; a Playwright interception simulates a 503 JSON response only in the test browser to verify recovery. The actual source model was not removed or interrupted.
- Tooltip diagnostics showed the tooltip was populated then hidden by the existing scroll handler. Waiting for scrolling to settle before hovering fixed the test assertion; no tooltip feature was rebuilt.

Representative screenshots:

- [Source desktop dashboard brief](after/screenshots/desktop-index-brief.png)
- [Source tablet overview](after/screenshots/tablet-index.png)
- [Source mobile prediction interface](after/screenshots/mobile-predictions.png)
- [Source mobile Graduate prediction](after/screenshots/mobile-prediction-graduate.png)
- [Populated isolated brief: five KPIs, three charts and warning](fixtures/screenshots/desktop-index-brief.png)
- [Populated long-bond print preview](fixtures/screenshots/long-bond-print-preview.png)
- [Populated mobile class/grade form](fixtures/screenshots/mobile-class-populated.png)

Raw results: [source browser](after/browser-results.json), [isolated browser](fixtures/browser-results.json), [backend suite](backend-results.json), [Apache smoke checks](source-http-results.json), [integrity verification](integrity-results.json), [machine summary](summary.json).

## Problems found and fixed

1. **Only four primary KPI cards.** Added the institutional SETE average as a fifth card. It is response-weighted and unavailable without evaluation responses.
2. **Unsupported empty-data conclusions.** Removed an invented current-month zero-risk point when no evidence exists, empty percentage rates, fake overdue dates without an active term and zero-count warning panels. Missing assessments and history now have explicit messages, including empty lists.
3. **Missing SETE dimension chart on overview.** Added the existing bar-chart component with response-weighted dimension values and responsible-use text. No UCI values enter it.
4. **Cramped mobile prediction metrics.** Responsive metric cards now stack and remain legible. Help remains accessible without overlaying mobile form explanations.
5. **One-page presentation gap.** Added an optional brief of the existing dashboard, retaining all full-overview sections and navigation. Its long-bond print styling hides navigation chrome and avoids hover-dimmed printed charts.
6. **SETE response rate excluded nonrespondents.** Denominator now includes eligible class invitations for faculty with zero responses. Current overall/dimension ratings use response weights consistently. A two-invitation/one-response regression test verifies 50%.
7. **Malformed request fields could cause PHP errors.** CSRF accepts untrusted types and rejects nonstrings safely; login and return fields are normalized without array-to-string warnings. Negative regression tests pass.

## BAT 405 Parts I–V compliance

| Case-study part | Status | Basis |
|---|---|---|
| I — Existing system assessment | PASS | Existing/proposed capabilities, data types, stakeholders and limitations documented below and in the implementation report |
| II — Five KPIs and responsible interpretation | PASS — proposed specification | Five KPI definitions, formulas, proposed targets/actions and warning thresholds below; proposals are not invented official university policy |
| III — Dashboard design | PASS for mandatory 5/3/2/1 requirements | Primary user is authorized academic administrator; five cards, three chart types, multiple interactions, alerts and a labeled one-page brief. Historical/department filter gap remains explicit above |
| IV — Model selection/application | PASS | Predictive classification problem, four features, seven modeling steps and actual evaluation in the implementation report; reproduced in Phase 5 |
| V — Evaluation/recommendations | PASS | Functional/security/model and responsive testing, confidentiality/fairness limits and specific recommendations below |

### Part I — Assessment

Existing portal functions include applicant processing, student registration, class/room scheduling, grade submission, rule-based risk monitoring/interventions and faculty evaluation summaries. The analytics enhancements add a learned outcome model and shared student/SETE monitoring; they are not claimed to have existed before implementation. Student self-service access and an end-to-end SETE submission connector are not implemented by this faculty-office portal.

Data types include student profiles/program/year/status; enrollment-class relationships; class schedules and rooms; midterm/final grades; applicant exam/document status; anonymous SETE dimension responses; faculty rank/employment and rating history. SETE provides student-perceived teaching evidence, not a complete measure of teaching quality.

Stakeholders and decisions: administrators allocate teaching capacity and review overdue submissions; program chairs coordinate advising and curriculum support; faculty use dimension feedback for improvement; authorized advisers review observed risk flags; students benefit from support services. The existing Faculty role has demonstration-only access because no safe faculty/account mapping exists. Role names do not imply unimplemented scoped permissions.

### Part II — KPI design

The following are **case-study proposals**, subject to institutional approval. They do not invent an official enrollment plan, official thresholds or historical values. Numerical planning baselines remain unconfigured until authorized staff supply real plans.

| KPI | Objective / formula | Proposed target | Management action |
|---|---|---|---|
| Active classes | Monitor teaching load; count selected current-term sections | Match the approved semester offering plan; target count must come from that plan | Assign faculty/rooms and resolve missing offerings |
| Enrolled student register | Monitor the existing portal cohort; count student registration rows in selected college scope | Match the approved cohort/enrollment plan; no official target is assumed | Review capacity and student registration services |
| Grades pending | Monitor submission completion; count sections whose status is not Submitted | 0 after the configured grade deadline | Contact responsible faculty and track completion |
| Observed at-risk students | Count students flagged by existing failing/INC/back-subject rules | Review every flagged case; proposed 100% urgent-case contact coverage | Review actual evidence and arrange supportive advising |
| SETE average score | Response-weighted mean of faculty averages on the 1–5 scale | Proposed ≥4.0/5 with adequate participation/context | Review dimensions and development opportunities alongside other evidence |

Definition caveat: the existing student-register count includes all registration statuses, including on-leave profiles. It is not a newly verified distinct-student, term-specific course-enrollment headcount. Counts must be presented with that definition rather than as attendance or completion outcomes.

Warnings implemented: (a) at least one unsubmitted section after an actual configured grade deadline; (b) at least one urgent case under the existing rule set; (c) SETE average below the proposed 3.5/5 threshold with at least 30 responses. The overdue-grade condition was exercised with disposable dates/records; the SETE threshold code is implemented, but a 30-response scenario was not separately browser-exercised. Missing academic setup also produces a genuine data-availability alert. SETE thresholds request review, not automatic personnel action.

### Part III — Dashboard components

The brief places the primary user/context and scope first, then five institutional cards and threshold alerts. Enrollment bars compare registration counts across colleges/programs, the donut shows submitted/in-progress/not-started classes, and SETE bars compare the five evaluation dimensions. The full overview retains risk history, admissions, schedule and detailed monitoring. The brief is an optional presentation of the same information, not another database or model.

College selection changes institutional scope; selecting a college drills enrollment down to programs. Donut legends toggle visible series; chart hover reveals exact values; applicant chips filter recent records. On mobile, the drawer and horizontally scrollable tables retain navigation and access to all columns. The current term is a disabled contextual selector, not a working historical term filter.

For SETE, dimension bars support interpretation of teaching strengths/development areas. Historical semester switching is a remaining feature gap. Screenshots are labeled component evidence; the isolated populated brief serves as the case-study mockup and must not be presented as actual university results.

### Part IV — Actual model results

Problem: predict eventual Dropout, Enrolled or Graduate outcomes after the first semester to support advising. Technique: Decision Tree Classifier; four source variables are curricular units enrolled, units approved, average grade and evaluations. Second-semester, demographic, financial and SETE features remain excluded. Seven steps, data provenance/checksum, leakage controls and methodology are in [the implementation report](../IMPLEMENTATION_REPORT.md).

The saved model remains `DecisionTreeClassifier(max_depth=6, min_samples_leaf=40, class_weight="balanced", random_state=405)`. The split remains 3,539 training / 885 held-out test records, with five-fold stratified CV on training data only. **No retraining occurred in Phase 5.**

| Metric | Actual held-out result |
|---|---:|
| Dropout recall | 65.49% |
| Macro F1 | 0.613675 |
| Accuracy, supplementary | 66.67% |

Confusion matrix: rows actual, columns predicted.

| Actual / Predicted | Dropout | Enrolled | Graduate |
|---|---:|---:|---:|
| Dropout | 186 | 62 | 36 |
| Enrolled | 55 | 70 | 34 |
| Graduate | 22 | 86 | 334 |

Dropout recall captures missed-support risk; macro F1 gives equal weight to each outcome class. Enrolled-class F1 remains 0.3714. The classifier misses 98 of 284 eventual dropouts. These are public Portuguese dataset results, not verified accuracy for this university. Leaf shares remain uncalibrated. Predictions do not automatically edit grades, create interventions or determine personnel actions.

### Part V — Evaluation and recommendations

Three testing categories: (1) functional/integration testing of setup, academic workflows and the API; (2) authentication, authorization, CSRF and confidentiality testing; (3) model validation plus responsive/browser usability testing. Physical devices, other engines, outage/load recovery, external access and actual university model validity were not verified.

Privacy/fairness controls include fail-closed permissions, no guessed account/student/faculty relationships, anonymous section-linked SETE responses, protected model/data/SQL files, no student identifiers in prediction requests, explicit external provenance and no adverse automated decisions. Low participation, response selection, course context and differences between source and local grading systems limit interpretation.

Two specific recommendations: use deadline/observed-risk alerts for supportive follow-up rather than automatic penalties; use SETE dimension profiles and participation alongside peer review and course context to select faculty-development support. Before local predictive use, obtain compatible first-semester measurements and actual eventual outcomes, validate independently and review group-level performance.

## Remaining limitations / presentation checklist

- **Known missing feature:** historical year/semester switching and department-specific filtering. College/program scope and current-term context are verified. Extending historical/scoped queries is separate feature work, not a capability marked as passed here.
- **Live academic data is intentionally absent.** The source should display unavailable states. Populated academic screenshots are isolated QA fixtures; no official references or student records were invented.
- **No local predictive accuracy claim.** The four UCI measurements require a validated local definition/grade mapping and local outcome evaluation before real student use.
- **Physical devices and other browser engines are not verified.** Tests emulate CSS viewports/touch in Chrome; they do not certify Safari, Firefox or physical iOS/Android devices.
- **Physical submission remains unverified:** cover names, final long-bond print, colored print quality and submission packaging require the actual students/printer. Print-emulated content fits the proposed long-bond printable height; no physical output is claimed.
- **Local access only:** a teacher on another computer cannot use their own localhost to access this laptop. No public hosting or production data was modified.

## Reproduce the checks

Playwright is a QA dependency outside the web root:

```sh
npm install --prefix /private/tmp/faculty-phase5-playwright playwright@1.56.1
QA_STAGE=after node tests/visual_qa.cjs
python3 tests/integration.py --database bat405_verify_20261117_022504_271703 --output /private/tmp/phase5-tests.json --with-model --browser-qa
/Applications/XAMPP/xamppfiles/bin/php tests/unit.php
/private/tmp/faculty-bat405-venv/bin/python tests/model.py
python3 tests/source_smoke.py
```

The isolated fixture suite refuses the source database and checks academic tables are empty before inserting fixtures. Its finally block removes only the disposable workflow data in that verification copy; original account data is fingerprinted. Source smoke/browser tests perform login, reads and nonpersistent inference only. No cleanup or seed command is part of Phase 5.
