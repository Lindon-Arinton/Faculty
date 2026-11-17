# Phase 7 PASS / FAIL / NOT VERIFIED checklist

PASS means directly supported by this audit unless explicitly identified as source inspection. NOT VERIFIED means evidence is insufficient or a test was excluded by read-only scope; it does not automatically mean broken. FAIL identifies a confirmed limitation against the stated interpretation. No source fixes, cleanup or retraining were authorized or performed.

| Requirement / claim | Result | Actual evidence or unresolved issue |
|---|---|---|
| Real fitted scikit-learn DecisionTreeClassifier | PASS | Checksummed joblib type, fitted tree and four-feature shape; model-results.json |
| Official UCI 697 identity and 4,424 records | PASS | Fresh official 533,344-byte archive matches cache SHA; CSV matches archive; official-source.json / supplement.json |
| Prepared four first-semester inputs only | PASS | Exact prepared-file comparison; source allowlist and model order |
| Dropout, Enrolled, Graduate output classes | PASS | Model classes and real API examples for each; class-examples.json |
| Stratified 80/20 saved test split | PASS | Exact seed-405 split reconstruction; 3,539/885; no index overlap |
| CV method / training-only folds / selected fitted parameters | PASS | Training source and metadata, independently reconstructed folds, actual estimator parameters |
| Independent reproduction of historical numerical CV scores | NOT VERIFIED | No refitting authorized; original per-fold score logs unavailable |
| Reproduce 65.49% dropout recall and 0.6137 macro F1 | PASS | Saved estimator evaluated on saved 885-row test set; exact confusion matrix reproduced |
| JSON tree is learned estimator export | PASS | Child arrays, features, thresholds, classes, leaf values match estimator |
| PHP inference matches sklearn | PASS | 4,424 source vectors, labels and probability tolerance 1e-12 |
| Threshold-boundary parity | PASS | 51 adjacent float32 grade probes |
| HTTP API matches sklearn | PASS | All 885 held-out vectors, labels and probability tolerance 1e-12 |
| Input validation and protected API responses | PASS | 14 HTTP cases; 401/405/415/400/413/403/422 as expected |
| Model-unavailable 503 exercised live in Phase 7 | NOT VERIFIED | Handler inspected; no artifact/service changes permitted. Prior Phase 6 simulation is separate historical evidence |
| Dashboard predictions are learned outputs, not random/hardcoded labels | PASS | Runtime tree traversal and panel sample loop inspected; all 12 samples recomputed (5/2/5) |
| Recommendations appropriate to predicted class | PASS | Actual class examples return support text, demonstration and uncertainty labels |
| No second-semester or SETE feature use | PASS | Four-feature allowlist, trained estimator shape, runtime schema and exports |
| Strict train-only derivation of input validation bounds | FAIL | Observed min/max were computed before splitting on all source records; limited test-support awareness disclosed |
| Local university accuracy / calibration / fairness / future-cohort validity | NOT VERIFIED | No labeled local validation set or independent temporal/fairness/calibration study |
| Provenance of every current DB table | PASS | 19 table counts and complete fingerprints match final Phase 6 state; DATABASE_PROVENANCE.md |
| 16,736 inserted demonstration rows reconciled | PASS | Plus one original user and one original metadata row = 16,738 total |
| UCI not additionally imported into academic tables | PASS | Entire current DB matches owned generator state; prepopulation was clean; training file-only source |
| No unexplained current-state manual additions | PASS | Complete table fingerprint equality; not a claim of complete event-history knowledge |
| Original user creation/bootstrap/import event | NOT VERIFIED | Preservation is established; exact original creation process is not logged conclusively |
| Complete absence of any historical manual edits | NOT VERIFIED | A current-state fingerprint cannot exclude changes later reverted |
| Existing account fields preserved | PASS | Current user fingerprint matches original precleanup and prepopulation backups; Phase 7 before/after matches |
| Database unchanged during Phase 7 | PASS | All table data/schema fingerprints unchanged; no destructive or write workflows used |
| FK integrity | PASS | 23 declared foreign keys, zero orphan rows |
| Existing project/model files preserved | PASS | 302 preexisting project files unchanged by checksum; preservation.json |
| Existing-account login | PASS | Actual localhost session login succeeded; no credentials published and account unchanged |
| Exhaustive all-role security / production posture audit | NOT VERIFIED | Not this audit's scope; only original account and relevant API access tests repeated; no role/account fixtures |
| Exactly five KPI table, objective/formula/proposed target/action | PASS | KPI_AND_SETE.md; current values 128 / 768 / 64 / 96 / 4.14 |
| Current five KPI values agree with actual builders and SQL | PASS | dashboard-audit.php / dashboard.json; strict enrollment also 768 |
| Enrolled students card always equals current-term enrolled headcount | FAIL | Current builder counts whole student register; today all 768 are enrolled, so no current numerical mismatch |
| At-risk list distinguished from tree-predicted Dropout | PASS | Grade/backlog SQL and independent inference data paths documented |
| At least two measurable warning thresholds | PASS | Existing overdue-submission and urgent-case warnings triggered; SETE threshold evaluated but not triggered |
| Institutional approval of proposed targets / plan totals | NOT VERIFIED | No official target policy or approved plan supplied; clearly labeled proposed |
| SETE mean, dimensions, response records and invitation denominator | PASS | 4.14/5; 2,576 / 3,072 = 83.85%; exact rounding method disclosed |
| SETE unique-person response rate / duplicate-submission prevention | NOT VERIFIED | Anonymous response table lacks respondent identity/invitation linkage; response count is not unique people |
| SETE confidentiality small-group suppression policy | NOT VERIFIED | No approved suppression rule established; stored anonymity alone is insufficient |
| SETE and student model remain separate | PASS | Different data sources and computation paths; UCI academic evaluations is not SETE |
| All six Part IV answers and seven modeling steps | PASS | PART_IV_PRESENTATION.md, with actual external metrics and limitations |
| New visual browser layout/screenshot QA | NOT VERIFIED | Not repeated in this read-only analytics audit; prior Phase 6 browser evidence remains historical |

## Unresolved priorities

1. Correct the enrollment-card population definition in a separately authorized implementation, or label it as the student register. No present fixture causes a numeric mismatch.
2. In a future authorized training revision, define runtime bounds from training data or declared domain constraints and preserve complete CV results. Existing fitted artifacts were retained.
3. Before operational university use, validate measurement mappings, local outcomes, temporal behavior, fairness and probability calibration. Provide a real survey collection/anonymity protocol and institutional KPI targets.
4. Original account creation provenance, complete historical manual-edit events, unique survey respondents and live 503 behavior remain unverified; do not present them as passed.
