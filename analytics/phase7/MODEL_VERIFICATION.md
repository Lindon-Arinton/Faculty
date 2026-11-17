# Model verification — BAT 405

Audit date: 17 November 2026. Scope: existing local model and read-only inference. No training, database cleanup, regeneration or feature changes were performed. The saved model is an actual `sklearn.tree.DecisionTreeClassifier`; PHP serves a faithful export of its learned tree.

## Evidence and method

- Training implementation: `analytics/train.py:23–111`; saved estimator: `analytics/artifacts/decision_tree.joblib`; learned runtime tree: `analytics/artifacts/tree.json`; training report: `analytics/artifacts/metrics.json`.
- Independent audit: [audit.py](audit.py), [model results](evidence/model-results.json), [API results](evidence/api-results.json), [public class examples](evidence/class-examples.json), [official archive verification](evidence/official-source.json).
- Dataset: [official UCI dataset 697](https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success). Cached archive SHA-256: `e90e55fd65ec462ae283ebeb2cca409319e3460ed898d8754f62fb35cc83a65d`; CSV: `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae`. Fresh download verification is recorded separately, without overwriting the cache.
- Shape: 4,424 rows, 36 original inputs plus Target; no missing cells or exact full-record duplicates. Class counts: Dropout 1,421; Enrolled 794; Graduate 2,209. The four-column preparation preserves the official values and labels. No scaling, imputation, oversampling or synthetic training records.

| Order / API key | Actual UCI first-semester variable | Accepted range |
|---|---|---|
| 1 / enrolled | Curricular units 1st sem (enrolled) | Integer 0–26 |
| 2 / approved | Curricular units 1st sem (approved) | Integer 0–26; cannot exceed enrolled |
| 3 / grade | Curricular units 1st sem (grade) | Numeric 0–18.875 |
| 4 / evaluations | Curricular units 1st sem (evaluations) | Integer 0–45 |

Curricular units here are curricular-unit counts, not automatically local credit-hours. Evaluations means academic assessment occurrences, **not SETE**. The original grade scale is 0–20; the interface restricts inputs to observed support, hence rejects 20. A Philippine 1–5 GWA cannot be entered directly as an equivalent measurement. Prediction time is after the first semester, not admission.

## Split, selection and fitted model

The saved indices exactly reproduce `train_test_split(test_size=.2, stratify=y, random_state=405)`: 3,539 training and 885 test rows, disjoint and collectively exhaustive. Training class counts are 1,137 / 635 / 1,767; test counts are 284 / 159 / 442, in Dropout / Enrolled / Graduate order.

Source code uses five-fold stratified shuffled cross-validation, seed 405, **training set only**. The grid contains 40 candidates: maximum depth 2–6, minimum leaf size 10/20/40/80, and class weighting None/balanced. Selection uses macro F1; dropout recall is also recorded. The saved fitted parameters are `class_weight='balanced', max_depth=6, min_samples_leaf=40, random_state=405`, default Gini criterion and best splitter. The model has 83 nodes, depth 6 and 42 leaves. Its root contains 3,539 training samples. All fitted parameters are included in machine evidence.

Stored selected CV macro F1 is 0.6028546988 (fold standard deviation 0.0169245708); stored CV dropout recall is 0.6490609784. **These are historical training results, not independently reproduced Phase 7 scores.** This audit reconstructs and verifies the fold allocation without fitting. Per-fold original score logs were not saved. Recomputing those scores would require training, which is outside authorization. The original fit execution itself is supported by source/artifacts, not an immutable execution log.

## Reproduced held-out performance

| Metric | Reproduced value | Interpretation |
|---|---:|---|
| Dropout recall | **65.49%** (186 / 284) | Identified 186 actual dropouts; missed 98 |
| Macro F1 | **0.6137** | Equal weight for all three class F1 scores |
| Accuracy, supplementary | 66.67% (590 / 885) | External held-out classification agreement |
| Enrolled F1 | 0.3714 | Weakest class; substantial uncertainty |

Confusion matrix: rows are actual outcomes, columns predicted outcomes.

| Actual \ Predicted | Dropout | Enrolled | Graduate |
|---|---:|---:|---:|
| Dropout | 186 | 62 | 36 |
| Enrolled | 55 | 70 | 34 |
| Graduate | 22 | 86 | 334 |

There are 77 false Dropout predictions. The recorded always-Graduate baseline has 0 dropout recall, 0.2221 macro F1 and 49.94% accuracy. Class weighting makes leaf shares unsuitable as calibrated individual risk probabilities. These results measure the saved UCI test split only; **local university accuracy is NOT VERIFIED**.

## Inference and integration checks

The audit loads only the checksum-verified local joblib. Exported child arrays, feature indices, thresholds, classes and normalized leaf values match the fitted estimator. All **4,424** source vectors produce identical sklearn/PHP classifications and probabilities (absolute tolerance 1e-12). These are inference parity tests; training rows are not counted as extra test performance. **51** float32-adjacent grade-threshold probes also agree. PHP deliberately converts inputs to float32 before applying the learned `<=` splits, matching sklearn.

All **885 held-out vectors** also match through the authenticated HTTP API for classifications and probabilities. Public representative responses cover all three predicted classes, with actual labels recorded separately. Fourteen HTTP checks cover authentication, method, content type, malformed/oversized JSON, CSRF, missing/extra fields, identity and second-semester rejection, negative/out-of-range values, fractional counts, inconsistent approved/enrolled counts and boolean type rejection. See actual status codes in the evidence.

`includes/prediction.php:58` traverses learned tree arrays; it does not draw random outcomes or use a manually written classification rubric. `api/predict.php` delegates to that function. `includes/prediction-panel.php:7` predicts each of 12 public sample vectors and counts the resulting outcomes; labels are not fixed. The support recommendation text is predefined **after** classification, appropriately. `assets/js/predictions.js` submits inputs and renders the response. Actual university KPI cards and SETE do not supply model features. API responses expressly state demonstration status and local validation limits.

The 12 illustrative samples were selected from held-out records (four per actual class); their predicted outcome distribution is not an estimated university dropout rate or a representative population sample.

## Findings and limitations

- PASS: requested classifier, four allowed features, three outputs, saved split, reproduced test metrics, full-source PHP parity, held-out API parity, tested validation and preservation.
- No second-semester or SETE feature leakage was found in the model allowlist or runtime. Validation min/max bounds were computed on the full source dataset before splitting. This is limited test-set awareness for input support, not fitted scaling or target selection; a future training process should estimate bounds using training data or predeclared domain rules.
- Repeated four-feature vectors across splits exist despite zero full-record duplicates. There are 496 held-out vectors whose four-input combination also occurs in training. They can represent different students with the same summaries; no group/temporal independence or future-cohort evaluation has been established. Do not claim that zero full-row duplicates proves external validity.
- NOT VERIFIED: local institutional performance, calibrated probabilities, causal explanations, fairness across local groups, temporal/generalization performance and independent CV score reproduction. The UCI population and grading system differ from this portal.
- The model catches about two thirds of external test dropouts, so it cannot be the only support screen. Enrolled is an eventual dataset status, not proof of safety. Graduate is not a guarantee.
- Runtime `model_version` identifies dataset checksum and seed rather than every hyperparameter; artifact hashes are stronger identifiers for this audit.
- A live missing-artifact 503 test was not induced because it would require changing working model/service state. The 503 handler exists; earlier simulated Phase 6 evidence is historical, not a new Phase 7 pass.
