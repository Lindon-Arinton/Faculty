<?php
/** Inference from a verified JSON export of the fitted scikit-learn tree.
 * Does not connect to a database, load pickle files, or start a Python process.
 */
function prediction_model(): array
{
    static $model = null;
    if ($model !== null) return $model;
    $dir = __DIR__ . '/../analytics/artifacts/';
    if (!is_file($dir . 'metrics.json') || !is_file($dir . 'tree.json')) throw new RuntimeException('Prediction artifacts missing');
    $metrics = json_decode((string) file_get_contents($dir . 'metrics.json'), true, 512, JSON_THROW_ON_ERROR);
    $json = (string) file_get_contents($dir . 'tree.json');
    if (!hash_equals($metrics['artifacts']['tree_json_sha256'], hash('sha256', $json))) {
        throw new RuntimeException('Prediction artifact integrity check failed');
    }
    $model = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if ($model['format_version'] !== 1 || array_column($model['features'], 'key') !== ['enrolled','approved','grade','evaluations']
        || $model['classes'] !== ['Dropout','Enrolled','Graduate']) throw new RuntimeException('Unsupported model schema');
    return $model;
}

function prediction_metrics(): array
{
    if (!is_file(__DIR__ . '/../analytics/artifacts/metrics.json')) throw new RuntimeException('Prediction metrics missing');
    return json_decode((string) file_get_contents(__DIR__ . '/../analytics/artifacts/metrics.json'), true, 512, JSON_THROW_ON_ERROR);
}

function prediction_samples(): array
{
    if (!is_file(__DIR__ . '/../analytics/artifacts/demo-samples.json')) throw new RuntimeException('Demonstration samples missing');
    return json_decode((string) file_get_contents(__DIR__ . '/../analytics/artifacts/demo-samples.json'), true, 512, JSON_THROW_ON_ERROR);
}

function prediction_features(array $input): array
{
    $model = prediction_model();
    $expected = array_column($model['features'], 'key');
    if (array_diff(array_keys($input), $expected) || array_diff($expected, array_keys($input))) {
        throw new InvalidArgumentException('Supply exactly the four first-semester inputs.');
    }
    $values = [];
    foreach ($model['features'] as $feature) {
        $v = $input[$feature['key']];
        if ((!is_int($v) && !is_float($v)) || !is_finite((float) $v)
            || $v < $feature['min'] || $v > $feature['max'] || ($feature['integer'] && floor((float) $v) != $v)) {
            throw new InvalidArgumentException($feature['label'] . ' must be ' . ($feature['integer'] ? 'a whole number' : 'a number') . ' between ' . $feature['min'] . ' and ' . $feature['max'] . '.');
        }
        // scikit-learn converts input to float32 before tree traversal.
        $values[] = unpack('f', pack('f', (float) $v))[1];
    }
    if ($input['approved'] > $input['enrolled']) throw new InvalidArgumentException('Passed units cannot exceed enrolled units.');
    return $values;
}

function predict_outcome(array $input): array
{
    $values = prediction_features($input); $model = prediction_model();
    $node = 0;
    for ($steps = 0; $steps < count($model['children_left']); $steps++) {
        if ($model['children_left'][$node] === -1) break;
        $feature = $model['feature'][$node];
        $node = $values[$feature] <= $model['threshold'][$node] ? $model['children_left'][$node] : $model['children_right'][$node];
    }
    if ($model['children_left'][$node] !== -1) throw new RuntimeException('Invalid model tree');
    $probabilities = $model['probabilities'][$node];
    $index = array_search(max($probabilities), $probabilities, true);
    $outcome = $model['classes'][$index];
    [$indicator, $recommendation] = match ($outcome) {
        'Dropout' => ['Priority academic support', 'Invite the student for a supportive advising conversation. Review passed units, learning needs, and available tutoring or assistance; confirm the context before acting.'],
        'Enrolled' => ['Monitor academic progress', 'Review course completion and arrange a progress check with an adviser. The Enrolled outcome does not establish that the student is academically safe or will drop out.'],
        'Graduate' => ['Routine academic support', 'Continue routine advising and monitor progress. A predicted Graduate outcome is not a guarantee of graduation.'],
    };
    return ['outcome'=>$outcome, 'indicator'=>$indicator, 'recommendation'=>$recommendation,
        'probabilities'=>array_combine($model['classes'], $probabilities), 'demonstration'=>true,
        'source'=>'UCI 697 external demonstration; not validated for local university students',
        'prediction_time'=>'After the first semester',
        'probability_note'=>'Uncalibrated tree leaf class shares, not validated individual risk probabilities',
        'model_version'=>$model['dataset_sha256'] . ':seed-' . $model['split_seed']];
}
