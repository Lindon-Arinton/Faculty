# BAT 405 Phase 7 — verified analytics and provenance

**Result: the saved Decision Tree and tested PHP/API integration PASS.** The independent held-out evaluation reproduces **65.49% dropout recall** and **0.6137 macro F1**. All 4,424 source vectors match PHP inference, all 885 held-out vectors match the authenticated API, and 51 threshold probes agree. Fourteen HTTP protection/validation tests pass. The official UCI archive freshly downloaded in memory matches the cached checksum. No retraining was performed.

**The database contains demonstration records, not verified real university academic observations.** All 19 table fingerprints match the final Phase 6 state. There are **16,738 current rows: 16,736 generated Phase 6 records, one preserved user, and one preserved cleanup metadata row**. The 4,424 UCI observations are separate files used for model training/testing; they were not additionally imported into university academic tables. All 23 declared foreign keys have zero orphans. The preserved user's complete row fingerprint matches both the original precleanup and prepopulation backups.

Read-only audit date: 17 November 2026; site: http://localhost/Faculty/; database: LOCAL `veritas_portal`. The demonstration dashboard clock is deliberately frozen at 09:30 Asia/Manila. Nothing in this report implies a real-time institutional observation or local prediction accuracy.

## Deliverables

- [Model verification, actual metrics, parity tests and limitations](MODEL_VERIFICATION.md)
- [Every-table database provenance, counts, sources, relationships and displays](DATABASE_PROVENANCE.md)
- [Exactly five KPI rows, source calculations, proposed targets, warnings and responsible SETE interpretation](KPI_AND_SETE.md)
- [All six Part IV answers, seven modeling steps and short spoken explanation](PART_IV_PRESENTATION.md)
- [PASS / FAIL / NOT VERIFIED checklist](CHECKLIST.md)

## Dashboard findings

Current all-college demonstration KPIs reproduce as **128 active sections; 768 students; 64 pending class submissions; 96 grade/backlog risk cases; SETE 4.14/5**. The risk count is not a predicted Dropout count. SETE has 2,576 response records across 3,072 eligible class-enrollment invitations (83.85%, displayed 84%), not 2,576 distinct people. Actual SETE intermediate rounding differs slightly from a direct raw-score mean; both display 4.14. Overdue-submission and urgent-support warnings are triggered; the SETE low-score warning is not.

Two confirmed qualifications are documented rather than changed: the current “Enrolled students” card counts the whole student register, which could exceed strict term enrollment in other data; runtime input bounds were calculated from the full UCI source before splitting. Today's demo enrollment card equals the independent strict count (768). No second-semester or SETE predictor leakage was found.

## Remaining limits

Local university model performance, probability calibration, fairness, future-cohort performance, original account creation event and a complete historical manual-edit trail are NOT VERIFIED. Historical numerical cross-validation scores are recorded but not recomputed because refitting is excluded. The Enrolled class has a weak test F1 of 0.3714, and the model misses 98 of 284 actual test dropouts. Predictions require human review and supportive interpretation.

The 12 external demo samples are illustrative, selected by known source outcome; their 5 Dropout / 2 Enrolled / 5 Graduate predictions are not a population estimate. Anonymous SETE rows do not prove unique respondents, duplicate-invitation prevention or an approved small-group confidentiality policy. KPI targets are proposals rather than institutional commitments.

## What changed and how it was verified

Only new audit scripts, JSON evidence and Markdown documentation under `analytics/phase7/` were created. No database records, user fields, model files, application features or preexisting project files were changed. A checksum manifest verified **302 existing project files** unchanged. All table data/schema fingerprints and declared relationships match before and after the API tests. Login testing used the existing local account without printing its credentials or updating its row. No production or other database was modified.

The existing broad regression suites were inspected but not run because some overwrite prior evidence; purpose-built Phase 7 checks reproduced the relevant model/inference/API/calculation assertions while saving only here. No destructive fixtures or unavailable-model mutation were used. New PHP audit script passes syntax validation. Visual layout and full all-role workflow testing were not repeated; the prior Phase 6 browser report and screenshots remain historical evidence, not fresh Phase 7 passes.

## Reproducible evidence

Run from the project root with the existing compatible environment; these commands do not fit or replace the model:

```sh
/private/tmp/faculty-bat405-venv/bin/python analytics/phase7/audit.py
/Applications/XAMPP/xamppfiles/bin/php analytics/phase7/dashboard-audit.php > analytics/phase7/evidence/dashboard.json
/private/tmp/faculty-bat405-venv/bin/python analytics/phase7/supplement.py
python3 analytics/phase7/write-provenance.py
```

The main audit expects the existing private Phase 6 manifests and configured original local login to remain accessible; it stops on fingerprint or prediction mismatches. It reads only the configured local source database and writes Phase 7 evidence. It uses HTTP login/session state and a fresh in-memory official archive download. Evidence is local documentation protected by the existing server deny rules; private SQL backups and raw account/student records were not copied here.

Machine evidence: [model](evidence/model-results.json), [API](evidence/api-results.json), [class examples](evidence/class-examples.json), [dataset](evidence/official-source.json), [prepared data and account lineage](evidence/supplement.json), [provenance](evidence/provenance.json), [dashboard](evidence/dashboard.json), [preservation](evidence/preservation.json). Before/after audit JSON is available in the same directory. Legacy cleanup-oriented field names inside those read-only inventory outputs are audit-tool vocabulary only; **no cleanup was proposed or executed in Phase 7**. Its obsolete login-test note is superseded by the actual unchanged-account HTTP login and preservation results here.
