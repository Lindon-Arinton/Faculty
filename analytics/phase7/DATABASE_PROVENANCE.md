# Database provenance — every current table

Read-only local audit, 17 November 2026. Database: `veritas_portal`. Evidence: [table fingerprints and relationships](evidence/provenance.json), [before](evidence/database-before.json), [after](evidence/database-after.json), [preservation](evidence/preservation.json).

**Current total: 16,738 rows = 16,736 Phase 6 inserted demonstration rows + one preserved user + one preserved cleanup metadata row.** The 16,736 count includes reference catalogs and five demo metadata records; it is not a count of students. There are 768 fictional student profiles. No current academic rows are established as real university student/faculty records.

## Evidence chain and attribution limits

The private prepopulation backup audit records zero academic/catalog rows, one user and one metadata row. Phase 6 `population-state.json` lists per-table inserted counts and final fingerprints for batch `BAT405_DEMO_2026_V1`. Every current table fingerprint matches that final state exactly. The current user fingerprint also matches both the original precleanup and prepopulation audits (supplement.json), preserving all user columns without publishing credentials. All 23 declared foreign keys have zero orphan rows. Before/after Phase 7 fingerprints and schema fingerprints match.

Verified private source evidence lives at `/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation/`: `audit-before.json`, `population-result.txt`, `population-state.json`, `audit-populated.json`, `manifest.json`, and `populated-backup-manifest.json`. This report includes only aggregate counts/hashes; SQL backups and identity/credential values were not copied into public documentation.

For all rows marked generated below, source is **`tools/populate_demo.php`**, especially lines 51 onward (Mt19937 seed 4052026), record builders, and line 140 insertion loop. The script performs explicit local-only population, not automatic web seeding. It was inspected, not executed in this audit. `includes/db.php:4` connects without seed/schema/account creation. Legacy `includes/seeder.php`, `database/seed.php` and the dormant SQL export are not sources of this current Phase 6 population. Their existence does not mean they were executed now.

The recorded Phase 6 applicant quality corrections align transferee GWA with the local 1–5 scale and update timestamps after document events; they changed owned generated rows, not accounts. The correction script remains at `/private/tmp/phase6_fix_owned.php`; corrected generation definitions and the final fingerprint state are available. These records remain demonstration data.

| Table | Current rows | Verified original source / type | Relationships (outgoing FK; parents also have incoming children) | System display / use |
|---|---:|---|---|---|
| `applicant_requirements` | 800 | Generated five-document checklist per fictional applicant; Phase 6 generator | applicant_id → applicants.id | incoming.php / applicant document modal |
| `applicants` | 160 | Generated fictional admissions pipeline; GWA/timestamps include recorded Phase 6 quality corrections; Phase 6 generator | program_id → programs.id | incoming.php / admissions dashboard |
| `colleges` | 4 | Generated four explicitly named demonstration college catalogs; Phase 6 generator | No outgoing declared FK | setup.php / college filters / dashboard |
| `courses` | 64 | Generated 64 demonstration subjects; Phase 6 generator | college_id → colleges.id | setup.php / curriculum / schedule / grades / class.php |
| `curriculum` | 128 | Generated program-course year mappings; Phase 6 generator | program_id → programs.id; course_id → courses.id | curriculum/academic form options; course selection |
| `enrollments` | 7,680 | Generated student-section memberships and midterm/final grades; Phase 6 generator | section_id → sections.id; student_id → students.id | enrollment.php / class.php / grades.php / at-risk.php |
| `evaluation_responses` | 6,264 | Generated anonymous five-dimension score rows; no respondent student foreign key; Phase 6 generator | section_id → sections.id | evaluations.php / SETE dashboard charts |
| `faculty` | 32 | Generated fictional faculty identities, assignments and college membership; Phase 6 generator | college_id → colleges.id | faculty views / schedule.php / evaluations.php / setup.php |
| `faculty_rating_history` | 64 | Derived summaries of generated responses in two historical terms; Phase 6 generator | faculty_id → faculty.id; term_id → terms.id | evaluations.php / previous-term teaching comparisons |
| `interventions` | 64 | Generated illustrative advising status/contact records; not term-versioned; Phase 6 generator | student_id → students.id | at-risk.php / support details |
| `meetings` | 320 | Generated section meeting times and room allocations; Phase 6 generator | section_id → sections.id; room_id → rooms.id | schedule.php / conflict detection / class.php |
| `meta` | 6 | Mixed: five generated demo settings + one preexisting cleanup marker; tools/populate_demo.php + tools/cleanup_database.php:71 | No outgoing declared FK | demo notice / snapshot clock / intake label; internal clean-state guard |
| `programs` | 8 | Generated eight demonstration degree-program catalogs; Phase 6 generator | college_id → colleges.id | setup.php / filters / enrollment and admissions forms |
| `risk_snapshots` | 36 | Generated fictional monthly/college scenario aggregates; not UCI or empirical longitudinal logs; Phase 6 generator | college_id → colleges.id | at-risk.php / dashboard monthly risk trend |
| `rooms` | 16 | Generated fictional room catalog/capacities; Phase 6 generator | college_id → colleges.id | setup.php / schedule.php / schedule forms |
| `sections` | 320 | Generated three-term teaching sections and submission timestamps; Phase 6 generator | term_id → terms.id; course_id → courses.id; program_id → programs.id; faculty_id → faculty.id | schedule.php / grades.php / class.php / dashboard |
| `students` | 768 | Generated fictional student profiles; not user login accounts; Phase 6 generator | program_id → programs.id; adviser_id → faculty.id | enrollment.php / at-risk.php / student detail views |
| `terms` | 3 | Generated three demonstration academic periods; Phase 6 generator | No outgoing declared FK | setup.php / academic year and semester selectors |
| `users` | 1 | Preserved original portal account; original creation/import method NOT VERIFIED | No outgoing declared FK | login.php / authorized navigation / session role checks |

Incoming links follow the same relationships: colleges parent programs/courses/faculty/rooms/risk_snapshots; programs parent students/sections/curriculum/applicants; terms parent sections/history; sections parent meetings/enrollments/responses; students parent enrollments/interventions; faculty parent sections/advisers/history. `risk_snapshots` is college/month aggregate storage, not individual model output. `meta` stores key-value settings. Neither users nor external training artifacts depend on student profile IDs.

## Five sources distinguished

1. **Phase 6 generator: verified.** The 16,736 inserted rows are deterministic fictional demonstration data, including reference catalogs and derived history. Full current table hashes match the recorded final batch, rather than merely matching row counts or name prefixes.
2. **Original portal: verified preservation, limited creation provenance.** One user predates Phase 6, with all fields preserved. Its original registration, bootstrap or SQL-import event is NOT VERIFIED because there is no conclusive creation-event log. Constants in config or a legacy dump do not prove which process originally created it. One preexisting `case_study_cleaned` marker is attributable to `tools/cleanup_database.php:71` and the earlier cleanup evidence. Original academic rows were absent in the prepopulation snapshot; no original academic dataset survives in the current tables.
3. **Official UCI: external files only.** The 4,424 public records reside in `analytics/data/data.csv` and its prepared/split files, with fitted artifacts in `analytics/artifacts/`. `analytics/train.py` uses files and no university DB connection. The generator has no UCI importer. Current table hashes exactly match the owned demo state. This verifies that the current university academic tables contain the Phase 6 dataset, not an additional UCI import.
4. **Manual entry: no unexplained current-state additions.** Existing app forms can create/edit records, but the current complete table hashes show no net drift from the final Phase 6 state. This does not prove that no transient edits were ever made and reverted; event-level provenance is NOT VERIFIED without a full audit log. Do not label generated records “manually entered university data.”
5. **Other verified source: cleanup metadata and recorded generator corrections**, as above. No additional current source was established.

## Demonstration database versus model data

| Property | Local portal database | External UCI training/evaluation files |
|---|---|---|
| Row accounting | 16,736 inserted across 18 tables; 16,738 total including original rows | 4,424 student-level public source records |
| Student profiles | 768 fictional profiles | 4,424 public dataset observations |
| Purpose | Demonstrate dashboards, catalogs, schedules, grades, admissions and SETE | Train/evaluate a Decision Tree using four first-semester academic variables |
| Outcomes | Portal grades/rule-based risk and generated survey responses | Observed dataset Target: Dropout, Enrolled, Graduate |
| Persistence | Relational demonstration records with local IDs/FKs | CSV, split indices, joblib and JSON tree; no local university identity mapping |
| Valid performance claim | Demonstration functionality only | Saved external held-out model metrics only |

The 3,539/885 split partitions the 4,424 UCI records; it does not partition the 16,736 relational demo rows. No synthetic portal grades or SETE scores contributed to the verified model test accuracy. No production or other database was changed or queried by this Phase 7 audit.
