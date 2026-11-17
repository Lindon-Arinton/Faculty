<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/prediction.php';
$user = require_login();
require_permission('prediction.demo', $user);
$error = ''; $result = null;
try {
    $model = prediction_model(); $metrics = prediction_metrics(); $samples = prediction_samples();
} catch (Throwable $ex) {
    http_response_code(503);
    page_start('predictions','Outcome prediction',$user);
    echo '<section class="card panel"><h1>Demonstration model unavailable</h1><p>Please ask an administrator to verify the trained model artifacts.</p></section>';
    page_end(); exit;
}
$values = $samples[0]['features'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !verify_csrf($_POST['csrf'])) {
        http_response_code(403); $error = 'Your session expired. Please reload and try again.';
    } else {
        try {
            $posted = $_POST['features'] ?? [];
            if (!is_array($posted) || array_diff(array_keys($posted), array_column($model['features'],'key'))) throw new InvalidArgumentException('Supply only the four first-semester inputs.');
            $values = [];
            foreach ($model['features'] as $feature) {
                $raw = $posted[$feature['key']] ?? null;
                if (!is_string($raw) || !is_numeric($raw)) throw new InvalidArgumentException('All four inputs must be numeric.');
                $values[$feature['key']] = (float)$raw;
            }
            $result = predict_outcome($values);
        } catch (InvalidArgumentException $ex) { http_response_code(422); $error = $ex->getMessage(); }
    }
}
page_start('predictions', 'Outcome prediction', $user);
page_head('Student outcome prediction', 'Demonstration predictions using public UCI 697 data. No university student records are used or stored.');
?>
<section class="card panel"><h2>Demonstration only</h2><p class="muted">The model predicts eventual Dropout, Enrolled, or Graduate outcomes after the first semester. Its results support advising discussions. Local university performance has not been validated. SETE ratings and faculty personnel decisions are outside this model.</p></section>
<div class="grid grid-2">
<section class="card panel">
<h2>First-semester academic inputs</h2>
<p class="muted small">Units mean curricular units (subjects), not credit hours. Evaluations mean assessment occurrences in the source dataset, not teaching evaluations. Average grade uses the source 0–20 scale; 0 can represent no earned grade. Do not enter a Philippine 1–5 GWA directly.</p>
<label class="f"><span class="f-label">Load an external demonstration record</span><select class="f-input" id="demoSample">
<?php foreach ($samples as $index=>$sample): ?><option value="<?= $index ?>"><?= e($sample['id']) ?></option><?php endforeach; ?>
</select></label>
<form method="post" action="predictions.php" id="predictionForm" class="f-grid">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<?php foreach ($model['features'] as $feature): ?>
<?= field($feature['label'], input('features['.$feature['key'].']', ['type'=>'number','required'=>true,'min'=>$feature['min'],'max'=>$feature['max'],'step'=>$feature['integer'] ? '1' : 'any','value'=>$values[$feature['key']] ?? '', 'data-feature'=>$feature['key']]), 'UCI observed range: '.$feature['min'].'–'.$feature['max'], 3) ?>
<?php endforeach; ?>
<div class="f f--3"><button type="submit" class="btn btn--primary">Predict demonstration outcome</button></div>
</form>
<p id="predictionError" class="tone-red" role="alert"><?= e($error) ?></p>
</section>
<section class="card panel" aria-live="polite" aria-atomic="true">
<h2>Demonstration prediction</h2>
<div id="predictionResult"<?= !$result ? ' hidden' : '' ?>>
<p class="muted small">Predicted outcome</p><h3 id="predictedOutcome"><?= e($result['outcome'] ?? '') ?></h3>
<p id="predictedIndicator"><?= e($result['indicator'] ?? '') ?></p>
<p id="predictedRecommendation"><?= e($result['recommendation'] ?? '') ?></p>
<h3>Uncalibrated class shares</h3>
<ul id="predictedShares"><?php foreach (($result['probabilities'] ?? []) as $label=>$share): ?><li><?= e($label) ?>: <?= number_format($share*100,1) ?>%</li><?php endforeach; ?></ul>
<p class="muted small">Tree leaf shares are not validated individual risk probabilities. Predictions never create interventions, edit student grades, or change faculty evaluations.</p>
</div>
<p id="predictionPlaceholder" class="muted"<?= $result ? ' hidden' : '' ?>>Select a public demonstration record or enter four source-compatible values, then run the prediction.</p>
</section>
</div>
<section class="card panel">
<div class="panel-head"><div><h2>Held-out test evaluation</h2><p class="muted small">Official UCI data · <?= $metrics['method']['train_records'] ?> training / <?= $metrics['method']['test_records'] ?> test records · Five-fold stratified training cross-validation</p></div><a class="link-strong" href="https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success" target="_blank" rel="noopener">Dataset source</a></div>
<div class="grid grid-3">
<?= stat_card('Dropout recall',number_format($metrics['test']['dropout_recall']*100,2).'%', 'Eventual dropouts identified in test set','alert','orange') ?>
<?= stat_card('Macro F1',number_format($metrics['test']['macro_f1'],4), 'Equal weight to all three outcome classes','chart','blue') ?>
<?= stat_card('Tree depth',(string)$metrics['tree']['depth'], $metrics['tree']['leaves'].' terminal leaves','grid','purple') ?>
</div>
<h3>Confusion matrix</h3><p class="muted small">Rows are actual outcomes; columns are predicted outcomes. Counts refer to external test records.</p>
<div class="table-wrap"><table class="data"><thead><tr><th>Actual / Predicted</th><?php foreach ($metrics['confusion_matrix_labels'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($metrics['test']['confusion_matrix'] as $i=>$row): ?><tr><th><?= e($metrics['confusion_matrix_labels'][$i]) ?></th><?php foreach ($row as $count): ?><td><?= $count ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div>
<p class="muted small">Selected tree: maximum depth <?= $metrics['method']['best_parameters']['max_depth'] ?>; minimum leaf records <?= $metrics['method']['best_parameters']['min_samples_leaf'] ?>; class weighting <?= e($metrics['method']['best_parameters']['class_weight'] ?? 'none') ?>. Features include no second-semester information or SETE data.</p>
</section>
<script type="application/json" id="demoSamples"><?= json_encode($samples,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="assets/js/predictions.js?v=<?= filemtime(__DIR__.'/assets/js/predictions.js') ?>" defer></script>
<?php page_end(); ?>
