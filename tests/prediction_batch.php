<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/prediction.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$results=[];
foreach ($input as $features) $results[]=predict_outcome($features);
echo json_encode($results, JSON_THROW_ON_ERROR);
