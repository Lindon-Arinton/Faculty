# BAT 405 Part IV — complete presentation answers

These answers describe the implemented system and the verified external model. Dashboard academic data are fictional demonstrations. Local university prediction performance has not been validated.

## 1. Analytical problem and management decision

The problem is to identify students who may need support after the first semester. The model estimates an eventual outcome—Dropout, Enrolled or Graduate—from four academic summaries. Management can use a prediction to prioritize a supportive advising conversation, review course completion and offer tutoring or assistance. A prediction is a screening aid, not a finding that a student will leave, and must not trigger punishment or exclusion. The existing portal's grade-based support list remains a separate source of evidence.

## 2. Types of analytics in the system

**Descriptive:** the five academic/SETE KPIs, enrollment and grade charts, teaching dimensions and response summaries describe stored demonstration records. **Diagnostic support:** filters by college/term and grade details help staff investigate where pending submissions or academic difficulties are concentrated; they do not establish causes. **Predictive:** the trained Decision Tree predicts the external dataset's eventual student outcome. **Prescriptive support:** fixed, human-reviewed recommendations suggest advising or progress checks after a prediction. These suggestions are not an optimized intervention policy or evidence that an intervention prevents dropout.

## 3. Selected technique and justification

The technique is a scikit-learn **Decision Tree Classifier**. It learns numerical splits from labeled records, follows those splits for new inputs and selects the class with the largest weighted share at the final leaf. Its small number of inputs and inspectable structure make it suitable for a student case study and PHP integration. A bounded depth and minimum leaf size help limit overfitting. It still requires labeled examples; “easy to train” does not mean the model is proven reliable with very little local data. More complex techniques are not necessary to explain the implemented baseline, and this audit does not claim the tree is the best possible classifier.

## 4. Four actual inputs and expected output

The inputs, in model order, are **first-semester curricular units enrolled, curricular units approved, curricular units grade, and curricular units evaluations**. Evaluations are academic assessment occurrences, not faculty SETE. Inputs describe completed first-semester activity; second-semester, demographic, financial and SETE fields are excluded.

The output is one of **Dropout, Enrolled or Graduate**, reflecting the external dataset's target definitions. Enrolled means the dataset's ongoing enrollment outcome, not a guaranteed safe student. Graduate is a prediction, not a graduation certification. The API also returns uncalibrated leaf class shares, demonstration labels and a support recommendation. UCI grades and unit counts must not be silently equated with local GWA/credit units.

## 5. Seven major modeling steps in the actual implementation

1. **Define the problem and decision.** Screen for academic support after the first semester; define the three target classes and a supportive, human-reviewed management use.
2. **Acquire and understand the data.** Use the official UCI dataset 697: 4,424 records, 36 original candidate inputs and one target. Verify archive/CSV checksums, class balance and measurement meanings. Keep public files separate from university records.
3. **Prepare and validate the data.** Select only the four approved first-semester columns and Target. Check missing values, finite values, integer count constraints, passed ≤ enrolled and full-record duplicates. No imputation or scaling is used. No synthetic portal data enter training. Runtime bounds currently reflect the full source data; future training should define these independently of held-out rows.
4. **Split and design validation.** Use a stratified 80/20 split, seed 405: 3,539 training and 885 testing records. Reserve the test set from model selection. Use five stratified shuffled folds within training. Stratification preserves class representation; it does not establish temporal or local validity.
5. **Train and select the model.** The original training script searches 40 parameter combinations, selecting by cross-validated macro F1. The saved model uses balanced class weights, depth limit 6 and minimum 40 records per leaf. It has 42 leaves. Phase 7 inspects the saved estimator and fold construction without retraining; the original numerical CV scores are historical evidence.
6. **Apply and integrate the saved model.** Export the learned thresholds/children/leaf shares to JSON. PHP validates four inputs and traverses the same tree. The authenticated CSRF-protected API and dashboard show demonstration predictions with academic-support suggestions. UCI examples are not assigned local student IDs. SETE stays descriptive and separate.
7. **Evaluate and communicate limitations.** Recompute held-out dropout recall, macro F1 and confusion matrix from saved indices. Compare sklearn against PHP on all 4,424 vectors and against HTTP API on all 885 test vectors, plus threshold/invalid-input checks. Document missed dropouts, weak Enrolled classification, external population differences and the need for future local validation before operational reliance.

## 6. Two evaluation metrics, actual values and justification

**Dropout recall = 186 / 284 = 65.49%.** Recall asks how many actual dropouts the model identifies. It matters because missing a student who needs support can delay assistance. The model missed 98 of the 284 test dropouts, so this result supports cautious screening, not automatic decisions.

**Macro F1 = 0.6136746733, reported as 0.6137.** Compute precision/recall F1 separately for Dropout, Enrolled and Graduate, then average equally: (0.6801 + 0.3714 + 0.7896) / 3. It gives the smaller Enrolled class equal importance instead of allowing the larger Graduate class to dominate accuracy. The low Enrolled F1 remains a material limitation.

Both numbers were reproduced from the saved 885-row UCI test split. Supplementary accuracy is 66.67%; the confusion matrix and full class metrics are in [model verification](MODEL_VERIFICATION.md). None of these values estimates local university accuracy, teaching effectiveness, intervention success or a causal dropout effect.

## Short spoken explanation

“Our portal uses a Decision Tree trained on 4,424 public UCI records. It reads four academic summaries available after the first semester and predicts Dropout, Enrolled or Graduate. PHP follows the exact learned tree, and our audit confirmed that its predictions match scikit-learn. On the reserved UCI test set, dropout recall is 65.49% and macro F1 is 0.6137. These results have not been validated for our university. Our populated dashboard contains fictional demonstration records, and SETE remains a separate descriptive teaching-evaluation feature. Predictions guide supportive review, not automatic decisions.”

## Part II presentation link

Use [the five-KPI table, formulas, targets and warning thresholds](KPI_AND_SETE.md) for Part II. Current demonstration values are 128 active sections, 768 students, 64 pending sections, 96 rule-based academic risk cases and 4.14/5 SETE. Explain that the 96 are grade/backlog flags, not predicted dropouts. Institutional plan totals and KPI targets require institutional approval; none is claimed here.
