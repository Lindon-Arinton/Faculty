<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/prediction.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function api_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}
start_session();
if (!current_user()) api_reply(401, ['error'=>'Sign in to access demonstration predictions.']);
$user = require_login();
if (!can('prediction.demo', $user)) api_reply(403, ['error'=>'Your role does not authorize predictions.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); api_reply(405, ['error'=>'Use POST.']);
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') api_reply(415, ['error'=>'Use application/json.']);
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (strlen($raw) > 4096) api_reply(413, ['error'=>'Request is too large.']);
try {
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input) || !is_string($input['csrf'] ?? null) || !verify_csrf($input['csrf'])) api_reply(403, ['error'=>'Invalid session token.']);
    if (!is_array($input['features'] ?? null)) api_reply(422, ['error'=>'Supply the four feature values.']);
    if (array_diff(array_keys($input), ['csrf','features'])) api_reply(422, ['error'=>'Only csrf and features are supported. Do not send student identities.']);
    api_reply(200, predict_outcome($input['features']));
} catch (JsonException $error) {
    api_reply(400, ['error'=>'Invalid JSON request.']);
} catch (InvalidArgumentException $error) {
    api_reply(422, ['error'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('BAT405 prediction artifact unavailable: ' . get_class($error));
    api_reply(503, ['error'=>'Prediction model is temporarily unavailable.']);
}
