<?php
require_once __DIR__ . '/prediction.php';
if (!can('prediction.demo')) return;
try {
    $demoOutcomes = ['Dropout'=>0,'Enrolled'=>0,'Graduate'=>0];
    foreach (prediction_samples() as $sample) $demoOutcomes[predict_outcome($sample['features'])['outcome']]++;
?>
<section class="card panel" aria-label="Demonstration predictive analytics">
<div class="panel-head"><div><h2>Student outcome prediction</h2><p class="muted small">Demonstration · External UCI data · After the first semester</p></div><a class="link-strong" href="predictions.php">Explore predictions <?= icon('chev-r',16) ?></a></div>
<div class="grid grid-3">
<?php foreach ($demoOutcomes as $label=>$count): ?>
<?= stat_card('Demo: predicted ' . $label, (string)$count, 'Of 12 external sample records', 'chart', $label==='Dropout' ? 'orange' : 'blue', 'predictions.php') ?>
<?php endforeach; ?>
</div>
</section>
<?php } catch (Throwable $error) { ?>
<section class="card panel"><h2>Student outcome prediction</h2><p class="muted">Demonstration model unavailable. Academic records and SETE reports remain accessible.</p></section>
<?php } ?>
