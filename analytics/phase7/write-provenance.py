"""Render table provenance from already captured anonymized audit evidence."""
import json
from pathlib import Path
P=Path(__file__).parent;x=json.loads((P/'evidence/provenance.json').read_text())
info={
'applicant_requirements':('Generated five-document checklist per fictional applicant','incoming.php / applicant document modal'),
'applicants':('Generated fictional admissions pipeline; GWA/timestamps include recorded Phase 6 quality corrections','incoming.php / admissions dashboard'),
'colleges':('Generated four explicitly named demonstration college catalogs','setup.php / college filters / dashboard'),
'courses':('Generated 64 demonstration subjects','setup.php / curriculum / schedule / grades / class.php'),
'curriculum':('Generated program-course year mappings','curriculum/academic form options; course selection'),
'enrollments':('Generated student-section memberships and midterm/final grades','enrollment.php / class.php / grades.php / at-risk.php'),
'evaluation_responses':('Generated anonymous five-dimension score rows; no respondent student foreign key','evaluations.php / SETE dashboard charts'),
'faculty':('Generated fictional faculty identities, assignments and college membership','faculty views / schedule.php / evaluations.php / setup.php'),
'faculty_rating_history':('Derived summaries of generated responses in two historical terms','evaluations.php / previous-term teaching comparisons'),
'interventions':('Generated illustrative advising status/contact records; not term-versioned','at-risk.php / support details'),
'meetings':('Generated section meeting times and room allocations','schedule.php / conflict detection / class.php'),
'meta':('Mixed: five generated demo settings + one preexisting cleanup marker','demo notice / snapshot clock / intake label; internal clean-state guard'),
'programs':('Generated eight demonstration degree-program catalogs','setup.php / filters / enrollment and admissions forms'),
'risk_snapshots':('Generated fictional monthly/college scenario aggregates; not UCI or empirical longitudinal logs','at-risk.php / dashboard monthly risk trend'),
'rooms':('Generated fictional room catalog/capacities','setup.php / schedule.php / schedule forms'),
'sections':('Generated three-term teaching sections and submission timestamps','schedule.php / grades.php / class.php / dashboard'),
'students':('Generated fictional student profiles; not user login accounts','enrollment.php / at-risk.php / student detail views'),
'terms':('Generated three demonstration academic periods','setup.php / academic year and semester selectors'),
'users':('Preserved original portal account; original creation/import method NOT VERIFIED','login.php / authorized navigation / session role checks')}
text='''# Database provenance — every current table

Read-only local audit, 17 November 2026. Database: `veritas_portal`. Evidence: [table fingerprints and relationships](evidence/provenance.json), [before](evidence/database-before.json), [after](evidence/database-after.json), [preservation](evidence/preservation.json).

**Current total: 16,738 rows = 16,736 Phase 6 inserted demonstration rows + one preserved user + one preserved cleanup metadata row.** The 16,736 count includes reference catalogs and five demo metadata records; it is not a count of students. There are 768 fictional student profiles. No current academic rows are established as real university student/faculty records.

## Evidence chain and attribution limits

The private prepopulation backup audit records zero academic/catalog rows, one user and one metadata row. Phase 6 `population-state.json` lists per-table inserted counts and final fingerprints for batch `BAT405_DEMO_2026_V1`. Every current table fingerprint matches that final state exactly. The current user fingerprint also matches the prepopulation audit, preserving all user columns without publishing credentials. All 23 declared foreign keys have zero orphan rows. Before/after Phase 7 fingerprints and schema fingerprints match.

Verified private source evidence lives at `/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation/`: `audit-before.json`, `population-result.txt`, `population-state.json`, `audit-populated.json`, `manifest.json`, and `populated-backup-manifest.json`. This report includes only aggregate counts/hashes; SQL backups and identity/credential values were not copied into public documentation.

For all rows marked generated below, source is **`tools/populate_demo.php`**, especially lines 51 onward (Mt19937 seed 4052026), record builders, and line 140 insertion loop. The script performs explicit local-only population, not automatic web seeding. It was inspected, not executed in this audit. `includes/db.php:4` connects without seed/schema/account creation. Legacy `includes/seeder.php`, `database/seed.php` and the dormant SQL export are not sources of this current Phase 6 population. Their existence does not mean they were executed now.

The recorded Phase 6 applicant quality corrections align transferee GWA with the local 1–5 scale and update timestamps after document events; they changed owned generated rows, not accounts. The correction script remains at `/private/tmp/phase6_fix_owned.php`; corrected generation definitions and the final fingerprint state are available. These records remain demonstration data.

| Table | Current rows | Verified original source / type | Relationships (outgoing FK; parents also have incoming children) | System display / use |
|---|---:|---|---|---|
'''
for t,v in x['tables'].items():
 source,display=info[t]
 if t not in ['users','meta']:source+='; Phase 6 generator'
 if t=='meta':source+='; tools/populate_demo.php + tools/cleanup_database.php:71'
 fks=[f"{z['COLUMN_NAME']} → {z['REFERENCED_TABLE_NAME']}.{z['REFERENCED_COLUMN_NAME']}" for z in x['foreign_keys'] if z['TABLE_NAME']==t]
 text+=f"| `{t}` | {v['rows']:,} | {source} | {'; '.join(fks) or 'No outgoing declared FK'} | {display} |\n"
text+='''
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
'''
(P/'DATABASE_PROVENANCE.md').write_text(text)
