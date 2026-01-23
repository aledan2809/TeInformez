<?php
/**
 * TeInformez GitHub Webhook Auto-Deploy
 *
 * Acest fișier primește push events de la GitHub și face auto git pull.
 *
 * Setup:
 * 1. Urcă acest fișier în public_html/webhook.php
 * 2. În GitHub repo: Settings > Webhooks > Add webhook
 *    - Payload URL: https://www.teinformez.eu/webhook.php
 *    - Content type: application/json
 *    - Secret: (copiază WEBHOOK_SECRET de mai jos)
 *    - Events: Just the push event
 * 3. Șterge deploy.php după ce webhook-ul funcționează
 */

// ============================================
// CONFIGURATION - Schimbă aceste valori!
// ============================================

// Secret key - TREBUIE să fie identic cu cel din GitHub Webhook settings
define('WEBHOOK_SECRET', 'teinformez_webhook_secret_2024_XyZ123');

// Branch to deploy
define('DEPLOY_BRANCH', 'master');

// Path to git repository (relativ la acest fișier)
define('REPO_PATH', __DIR__);

// Log file
define('LOG_FILE', __DIR__ . '/webhook-deploy.log');

// ============================================
// DO NOT EDIT BELOW THIS LINE
// ============================================

// Disable error display for security
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set execution time
set_time_limit(120);

/**
 * Log message to file
 */
function webhook_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Verify GitHub signature
 */
function verify_signature($payload, $signature) {
    if (empty($signature)) {
        return false;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $payload, WEBHOOK_SECRET);
    return hash_equals($expected, $signature);
}

/**
 * Send JSON response
 */
function respond($status, $message, $data = []) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['message' => $message], $data));
    exit;
}

// ============================================
// MAIN EXECUTION
// ============================================

webhook_log("=== Webhook received ===");

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhook_log("ERROR: Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    respond(405, 'Method not allowed');
}

// Get raw POST data
$payload = file_get_contents('php://input');
if (empty($payload)) {
    webhook_log("ERROR: Empty payload");
    respond(400, 'Empty payload');
}

// Get GitHub signature
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Verify signature
if (!verify_signature($payload, $signature)) {
    webhook_log("ERROR: Invalid signature");
    respond(403, 'Invalid signature');
}

// Parse JSON payload
$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    webhook_log("ERROR: Invalid JSON payload");
    respond(400, 'Invalid JSON');
}

// Check if it's a push event
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';
webhook_log("Event type: $event");

if ($event !== 'push') {
    webhook_log("INFO: Ignoring non-push event");
    respond(200, 'Event ignored', ['event' => $event]);
}

// Check branch
$ref = $data['ref'] ?? '';
$branch = str_replace('refs/heads/', '', $ref);
webhook_log("Branch: $branch");

if ($branch !== DEPLOY_BRANCH) {
    webhook_log("INFO: Ignoring push to branch: $branch");
    respond(200, 'Branch ignored', ['branch' => $branch]);
}

// Get commit info
$commits = $data['commits'] ?? [];
$commit_count = count($commits);
$pusher = $data['pusher']['name'] ?? 'unknown';
$head_commit = $data['head_commit']['message'] ?? 'No message';

webhook_log("Push by: $pusher");
webhook_log("Commits: $commit_count");
webhook_log("Message: $head_commit");

// Change to repository directory
if (!chdir(REPO_PATH)) {
    webhook_log("ERROR: Cannot change to directory: " . REPO_PATH);
    respond(500, 'Cannot access repository directory');
}

webhook_log("Working directory: " . getcwd());

// Execute git pull
webhook_log("Executing git pull...");

$output = [];
$return_var = 0;

// Git fetch first
exec('git fetch origin 2>&1', $output, $return_var);
webhook_log("Git fetch output: " . implode("\n", $output));

// Git pull
$output = [];
exec('git pull origin ' . DEPLOY_BRANCH . ' 2>&1', $output, $return_var);
$pull_output = implode("\n", $output);
webhook_log("Git pull output: $pull_output");
webhook_log("Git pull exit code: $return_var");

// Check result
if ($return_var !== 0) {
    webhook_log("ERROR: Git pull failed with exit code $return_var");
    respond(500, 'Deploy failed', [
        'output' => $pull_output,
        'exit_code' => $return_var
    ]);
}

// Success
$success_msg = "Deploy successful";
if (strpos($pull_output, 'Already up to date') !== false) {
    $success_msg = "Already up to date";
}

webhook_log("SUCCESS: $success_msg");
webhook_log("=== Webhook complete ===\n");

respond(200, $success_msg, [
    'branch' => $branch,
    'commits' => $commit_count,
    'pusher' => $pusher,
    'output' => $pull_output
]);
