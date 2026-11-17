# Five dashboard KPIs and responsible SETE interpretation

All figures below describe **fictional Phase 6 academic records**, snapshot 17 November 2026 at 09:30 Asia/Manila, current selected term `DEMO S1 AY2026-27` (ID 5), all colleges. They are not official institutional performance or UCI model predictions. Fresh calculation evidence: [dashboard.json](evidence/dashboard.json), generated with the read-only [dashboard audit](dashboard-audit.php).

All targets below are **proposed case-study targets**, not approved university policy. Approved teaching/cohort plans are not stored or verified; their eventual totals must be supplied by administrators.

| KPI | Objective | Formula | Target | Management Action |
|---|---|---|---|---|
| Active classes — **128** | Monitor offered teaching sections | Count selected-term section rows after course/program/college joins and selected college filter | Match 100% of the approved teaching plan; no invented official section count | Compare with the approved plan; resolve missing assignments, courses, rooms and schedules |
| Enrolled students — **768** | Monitor cohort coverage | Current card: count student-register rows after program/college joins; historical card: count students with selected-term enrollment | 100% reconciliation with distinct selected-term enrolled students and the approved cohort plan | Reconcile registered but unenrolled students and investigate capacity or enrollment barriers |
| Grades pending — **64** | Ensure timely grade submission | Number of class sections classified In progress + Not started; equivalent to selected-term sections without submission timestamp | 0 pending sections by the grade deadline | Contact responsible faculty, check submission obstacles and escalate overdue sections |
| At-risk students — **96** | Identify academic support workload | Distinct students with selected-term effective grade 5.00 or INC, or current Irregular profile with ≥3 back subjects | Review 100% of flagged cases; assign an adviser to every urgent case | Verify grade evidence, prioritize urgent students, offer advising/tutoring and record follow-up; do not punish based on a flag |
| SETE average score — **4.14 / 5** | Monitor student-reported teaching experience | Sum(faculty rounded rating × response count) / sum(response count), displayed to 2 decimals | Proposed ≥4.0/5 with ≥60% response participation; review sample size and context | Review dimensions, participation and other teaching evidence; offer faculty development before drawing conclusions |

## Actual implementation and SQL logic

Source: `includes/data.php:13–18,33–41,69–76,135–158`; `includes/dataset.php:163–211,230–277,374–420,444–478`. All college filtering applies after the existing builders; the values above use no college filter. Use bound `:term=5` for the examples.

**Active classes.** Tables: sections, courses, programs, colleges; faculty and enrollment enrich the display. The builder's inner joins determine inclusion:

```sql
SELECT COUNT(*) FROM sections x
JOIN courses co ON co.id=x.course_id
JOIN colleges c ON c.id=co.college_id
JOIN programs p ON p.id=x.program_id WHERE x.term_id=:term;
```

Result 128. “Active” means selected-term sections, not lessons currently in progress by calendar date. The configured term remains current even though its teaching end date precedes the frozen snapshot.

**Enrolled students.** Tables: students, programs, colleges, enrollments, sections, courses; optional faculty adviser. Current builder includes registered students even without selected-term enrollment (`WHERE u.student_id IS NOT NULL OR 1=1`). Historical selection uses `OR 0=1`. Current count is therefore conceptually a student-register count:

```sql
SELECT COUNT(*) FROM students s
JOIN programs p ON p.id=s.program_id JOIN colleges c ON c.id=p.college_id;
-- Independent check of strict term enrollment:
SELECT COUNT(DISTINCT e.student_id) FROM enrollments e
JOIN sections x ON x.id=e.section_id WHERE x.term_id=:term;
```

Both results are 768 for this demonstration, so today's value is correct. **FAIL for general semantic equivalence of the card label to strict current-term enrollment:** future registered-but-unenrolled students could inflate it. No fix was made because this is a read-only audit.

**Grades pending.** Tables: sections and enrollments, with course/program/college joins for scope. `grades_submitted_at` determines Submitted. Otherwise any non-null final grade gives In progress; no encoded final grades gives Not started.

```sql
SELECT COUNT(*) FROM sections x WHERE x.term_id=:term
AND x.grades_submitted_at IS NULL;
```

All current references are valid, so this equals the scoped builder result: 32 In progress + 32 Not started = 64. Another 64 are Submitted. Unit is **class sections**, not missing individual grades. Submission timestamp is an operational status, not independent proof of complete/correct grades.

**At-risk students.** Tables: students, enrollments, sections, programs/colleges for scope; interventions supplies support status, not the risk decision. Effective grade is `COALESCE(final_grade,midterm_grade)`. Aggregate per student:

```sql
SELECT s.id FROM students s
LEFT JOIN (
 SELECT e.student_id,
 SUM(COALESCE(e.final_grade,e.midterm_grade)='5.00') failing,
 SUM(COALESCE(e.final_grade,e.midterm_grade)='INC') inc
 FROM enrollments e JOIN sections x ON x.id=e.section_id
 WHERE x.term_id=:term GROUP BY e.student_id
) g ON g.student_id=s.id
WHERE g.failing>0 OR g.inc>0
OR (s.status='Irregular' AND s.back_subjects>=3);
```

Historical mode disables the final current-profile backlog clause. Count the returned distinct student IDs within the builder's scoped student population. Current 96 = 32 Failing + 32 INC + 32 Behind curriculum (type precedence prevents double counting). Urgent means ≥2 failures, a failure plus INC, or ≥5 back subjects: 32 urgent. This is **rule-based monitoring**, not the tree's predicted Dropout count.

**SETE average.** Tables: evaluation_responses, sections, faculty, colleges; enrollments supplies invitations, terms/history supplies comparisons. For each faculty in the selected term, SQL obtains count and averages:

```sql
SELECT x.faculty_id,COUNT(*) responses,
AVG(r.teaching),AVG(r.organization),AVG(r.communication),
AVG(r.fairness),AVG(r.punctuality)
FROM evaluation_responses r JOIN sections x ON x.id=r.section_id
WHERE x.term_id=:term GROUP BY x.faculty_id;
```

Actual PHP rounds each dimension mean to two decimals, takes their mean and rounds the faculty rating to two decimals, then response-weights those ratings. Unrounded headline result: 4.1421506211, displayed **4.14**. Direct response-level mean is 4.1419254700, also 4.14. The small intermediate-rounding difference is disclosed; the headline is not an unweighted mean of faculty ratings. Do not silently substitute a different formula.

## Measurable warnings

Existing `includes/data.php:152–158` implements these case-study thresholds. Historical views suppress operational warnings.

1. **Pending sections >0 after the configured grade deadline.** Currently triggered: 64 pending; snapshot is one day after deadline. Action: resolve overdue submissions with faculty.
2. **Urgent academic support count >0.** Currently triggered: 32. Action: inspect each case and coordinate support; being contacted does not automatically remove an academic flag.
3. **SETE average <3.5 with at least 30 responses.** Not triggered: 4.14, 2,576 responses. Action: contextual teaching-support review. Thirty is an implemented aggregate trigger, not proof of adequate statistical precision or anonymity.

Targets and warnings serve different purposes: the proposed SETE target is 4.0, while the existing warning is below 3.5. Case review coverage and plan reconciliation are proposed management targets; the current cards alone do not prove they have been achieved. Empty grades/SETE should show insufficient evidence (dash/message), not a claim of universal academic safety or poor teaching.

## Responsible SETE interpretation

Current term has **2,576 anonymous response records** against **3,072 eligible course-enrollment invitations**: **83.85%**, displayed as 84%. These are not 2,576 distinct people: a student can evaluate several classes. The denominator excludes Off-campus mode under the current invitation query. No identifiable respondent linkage exists in the response table, so unique-person response rate and one-response-per-invitation enforcement are NOT VERIFIED from this schema. Phase 6 produced internally bounded demonstration counts; this does not establish a complete real survey collection protocol.

Scores use five 1–5 dimensions: teaching effectiveness, course organization, communication, fairness in grading, and punctuality/availability. Current response-weighted displayed dimension scores are 4.14, 4.14, 4.14, 4.14 and 4.15. An average summarizes reported experience; it does not measure learning gains or prove teaching quality causally. With no responses, no valid average exists. Always show counts and participation alongside scores.

Voluntary response, nonresponse, grade expectations, course difficulty, attendance, timing and student bias can affect results. Compare similar courses and periods; large classes contribute more response weight, while small classes produce less stable and potentially identifiable summaries. Avoid ranking faculty by tiny differences, especially after rounding. Historic summaries derive from demonstration responses and are descriptive, not independent observations establishing improvement.

Protect anonymous responses, restrict authorized access, suppress or combine small groups under an institutionally approved confidentiality policy, and avoid attempts to infer identities. Anonymity of stored rows does not by itself guarantee confidentiality in small classes; an approved suppression threshold and an operational anonymity audit are NOT VERIFIED.

Use patterns to offer coaching, peer observation, clearer assessment rubrics, course organization or communication support. Combine SETE with peer review, teaching portfolios, course context and appropriately interpreted student learning evidence. SETE should never be the sole basis for hiring, promotion, discipline or other personnel decisions.

**Separation verified:** SETE uses evaluation_responses/faculty/history; the Decision Tree uses only four UCI first-semester academic variables and independent artifacts. Academic “evaluations” in UCI is not teaching evaluation. Neither a low SETE score nor a faculty rating is an input to dropout prediction.
