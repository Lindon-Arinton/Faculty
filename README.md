# BSU Student and Faculty Portal — BAT 405

The existing PHP/MySQL portal now includes first-semester student outcome prediction using a trained scikit-learn Decision Tree, alongside enrollment, scheduling, grade monitoring, interventions and separate descriptive SETE reports.

Open **http://localhost/Faculty/** and use your existing account. Existing accounts and credentials were preserved; the application no longer creates accounts, seeds records, creates databases or rebuilds schemas automatically.

## Current Phase 6 demonstration

**The dashboard is populated with DEMONSTRATION DATA.** [Phase 6 report](analytics/phase6/PHASE6_REPORT.md) documents 16,736 new fictional records, validated backups and rollback instructions. Current KPIs: **128 classes, 768 students, 64 pending submissions, 96 observed risk cases and 4.14/5 SETE**.

Academic year, semester and college filters now work; historical terms are read-only. The original account, trained model and UCI training source remain unchanged. Automatic seeding remains disabled. Open **http://localhost/Faculty/** or the [dashboard brief](http://localhost/Faculty/index.php?view=brief).

## Prior Phase 5 QA and presentation

[Final QA report](analytics/qa/PHASE5_REPORT.md): 75 browser route/viewport checks, 249 browser interaction assertions, 178 backend checks and 71 actual Apache checks passed. Desktop, tablet and mobile screenshots are included. Source data, all account fields and original trained-model artifacts are unchanged.

Open **http://localhost/Faculty/index.php?view=brief** for the optional one-page case-study brief; the full overview and existing navigation remain available. Phase 5 tested the intentionally empty source and isolated fixtures. Phase 6 subsequently populated the source explicitly and implemented year/semester filters. Physical devices, physical printing, department-specific scope and local model validity remain outside the verified capabilities.

## Current case-study database

The approved cleanup removed exactly **88,668 non-account records** from the LOCAL `veritas_portal` database. All 19 tables, indexes and foreign keys remain. The original user account remains unchanged. Phase 6 subsequently added 16,736 fictional demonstration records, including five provenance/snapshot metadata records; the original clean-state marker is retained. Academic data is now populated, and users are unchanged. Production was not changed.

The verified SQL backup, full restore audit, cleanup rehearsal, execution results and private test evidence are outside the web root in `/Users/johnlukereynon/Faculty-backups/bat405-20261117-approved/`. Do not expose this directory through Apache or commit its SQL files.

See [the implementation and testing report](analytics/IMPLEMENTATION_REPORT.md) for results and limitations, and [the original cleanup plan](database/cleanup-plan.md) for the approved deletion scope.

## Using the cleaned portal

1. Start Apache and MySQL in XAMPP. This installation uses PHP 8.2.4 and MariaDB 10.4.28.
2. Sign in with the preserved account. No password reset is required.
3. The overview displays the explicitly populated demonstration KPIs and a DEMONSTRATION DATA banner. Data is never generated automatically.
4. An account with `University-wide access` can open **Reference setup** to enter actual colleges, programs, courses, rooms and an active term. Then use the existing faculty, applicant, student, class and grade forms. Adding reference records is an explicit administrator action.
5. Open **Outcome prediction** to select an external UCI demonstration record or supply four compatible first-semester measurements. The dashboard also displays a separate summary of 12 external examples. These examples are never imported into student, enrollment, grade, intervention or SETE tables.
6. SETE summaries remain on **Faculty evaluations**. With no responses, scores are unavailable rather than fabricated. Anonymous responses still link to sections, never student identities.

`database/seed.php` and legacy seeder entry points are disabled. `database/veritas_portal.sql` is a historical sample export and must **not** be imported into the cleaned portal. Apache blocks direct downloads of database, model, test, tool and hidden files.

## Authorization

Permissions are checked server-side on every protected page, mutation and prediction request. The persisted role is reloaded each request; editing a session role does not grant access. No roles or account records were changed.

| Exact role | Access |
|---|---|
| `University-wide access` | Existing institutional pages, academic and faculty CRUD, reference setup, demonstration prediction |
| `Academic Viewer` | Institutional/SETE pages and CSV exports, demonstration prediction; no mutations or setup |
| `Faculty` | Public demonstration prediction only |
| Any other role | Denied |

Only the first role exists in the source database. The other policies were tested with temporary accounts on the isolated verification copy. The schema has no account-to-faculty or department mapping; scoped access must be implemented explicitly before granting faculty/student-specific institutional access. New role names do not implicitly receive permissions.

Authentication deliberately leaves all stored account fields unchanged, including password hashes and last-login metadata. Sessions, CSRF checks and existing login throttling remain active.

## Model and API

The model uses UCI dataset 697, licensed CC BY 4.0, and exactly four first-semester variables: units enrolled, units approved, average grade and evaluations. Evaluation counts refer to academic assessments, not SETE. Curricular units are subjects, not credit hours. Source grades use 0–20; Philippine 1–5 grades cannot be directly substituted.

Training: fixed seed 405; stratified 80/20 split; 5-fold stratified cross-validation on training data; macro F1 model selection. The held-out test set has 885 records. Actual dropout recall is **65.49%** and macro F1 is **0.6137**. See [metrics.json](analytics/artifacts/metrics.json) for the full confusion matrix, class results and baseline.

The saved sklearn model is `analytics/artifacts/decision_tree.joblib`. PHP runs a checksum-verified JSON export of that same fitted tree; it does not start Python or deserialize pickle files. Inference agrees with sklearn for all 4,424 source rows and 51 floating-point threshold cases. Academic and SETE tables are never queried by the inference engine.

`POST api/predict.php` requires an authenticated permitted session, JSON and the current CSRF token:

```json
{
  "csrf": "current-session-token",
  "features": {"enrolled": 6, "approved": 4, "grade": 12, "evaluations": 8}
}
```

The API returns outcome, advisory support indicator, recommendations, uncalibrated leaf class shares and explicit demonstration provenance. It rejects extra feature names, student identifiers, invalid counts, unsupported grades, oversized requests and second-semester inputs. Predictions are not stored or automatically converted to interventions. The page also supports a server-rendered POST fallback.

## Reproduce training

Keep the Python environment outside the web root. Runtime PHP inference needs no Python dependencies.

```sh
python3 -m venv /private/tmp/faculty-bat405-venv
/private/tmp/faculty-bat405-venv/bin/python -m pip install -r analytics/requirements.txt
/private/tmp/faculty-bat405-venv/bin/python analytics/train.py --download
/private/tmp/faculty-bat405-venv/bin/python tests/model.py
```

The first download must come from the official UCI host. Its pinned checksum is recorded in `analytics/dataset-checksum.txt`; changed downloads stop training. Raw external data stays in `analytics/data/`, separately from institutional tables. `first-semester.csv` contains only the four selected predictors and target. Training artifacts and actual results are saved in `analytics/artifacts/`.

## Verification tools

```sh
/Applications/XAMPP/xamppfiles/bin/php tests/unit.php
/private/tmp/faculty-bat405-venv/bin/python tests/model.py
python3 tests/source_smoke.py --demo
/Applications/XAMPP/xamppfiles/bin/php tests/phase6_validate.php
```

`tests/source_smoke.py` checks actual LOCAL Apache routes, existing-account login, model API, private-file protection and unchanged database fingerprints. It does not insert or delete source records. It uses the configured existing credential without printing it; if the account credential differs, it stops rather than resetting anything.

`tests/integration.py --database bat405_verify_<id> --output /private/tmp/test-results.json --with-model` requires a cleaned isolated verification schema. It creates clearly disposable test accounts and academic fixtures there, tests existing form workflows, then removes those fixtures. It refuses the source database. Test fixtures are never used for model-performance claims.

`tools/database_audit.php` performs read-only audits without application initialization. `tools/backup_database.py` writes a private full backup, restores into a newly named verification schema and compares all table data/schema fingerprints. `tools/cleanup_database.php` defaults to dry run, validates the exact approved snapshot, requires passing rehearsal/tests for source execution, maintains foreign keys and runs explicit DELETE statements in one transaction. It will refuse another cleanup against the now-clean database.

The cleanup tool's exclusive maintenance lock blocks application database requests during cleanup. Browser requests obtain shared locks. No table drops, truncations or disabled foreign keys are used.

## Limits

This is an external-data demonstration; the results do not validate predictions for this university. The model has limited ability to distinguish still-enrolled students and misses some eventual dropouts. Leaf shares are uncalibrated, and predictions are advisory. Collect compatible local first-semester measurements and eventual outcomes before local validation or deployment decisions. SETE remains a separate source of descriptive evidence and must not independently determine personnel actions.

Phase 5 Playwright browser QA and the functional/model suites passed; see the final QA report for screenshots, per-requirement status and the remaining limitations. Physical devices and browser engines other than Chrome were not tested.


## Phase 6 explicit data controls

`tools/populate_demo.php` previews population by default. Only `--execute --confirm=veritas_portal` can insert, and an identical owned batch is a no-op. `--rollback` previews removal; `--rollback --execute --confirm=veritas_portal` deliberately returns to the clean state only if every owned fingerprint still matches. See the Phase 6 report before running it. No rollback was executed during presentation QA. Later edits cause these safeguards to stop rather than overwrite work.

Pre-population and populated SQL backups are private in `/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation/`. Original accounts were never recreated or changed; only the approved local database was populated. New presentation entries should remain fictional and explicitly labeled.

Run populated browser QA with `QA_STAGE=phase6 node tests/visual_qa.cjs`. The Phase 5 isolated fixture suite remains historical regression tooling and must not be run against the populated source; Phase 6 checks do not modify any other database.
