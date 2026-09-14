<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

function e($value) { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }

$user = currentUser();
$stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = :id");
$stmt->execute(['id' => $user['id']]);
$apiKey = $stmt->fetchColumn();

// Regenerate key if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate') {
    $newKey = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE users SET api_key = :k WHERE id = :id")->execute(['k' => $newKey, 'id' => $user['id']]);
    header('Location: api_info.php');
    exit;
}

$pageTitle = 'API Access';
$activePage = 'api';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="info-card" style="max-width:820px;">
    <h2 style="margin-top:0;">Your API Key</h2>
    <p style="color:var(--gray-500); font-size:14px;">Use this key to call the JSON API from external scripts, apps, or tools like curl/Postman.</p>
    <div class="api-key-box">
        <span id="apiKeyText"><?= e($apiKey) ?></span>
    </div>
    <form method="post" style="margin-top:12px;" onsubmit="return confirm('Regenerating will invalidate the old key. Continue?');">
        <input type="hidden" name="action" value="regenerate">
        <button type="submit" class="btn btn-sm btn-secondary">Regenerate Key</button>
    </form>
</div>

<div class="info-card" style="max-width:820px; margin-top:20px;">
    <h2 style="margin-top:0;">Authentication</h2>
    <p>Pass your key on every request, either as a header or a query parameter:</p>
    <div class="code-block">X-API-Key: <?= e($apiKey) ?></div>
    <p>or</p>
    <div class="code-block">?api_key=<?= e($apiKey) ?></div>
</div>

<div class="info-card" style="max-width:820px; margin-top:20px;">
    <h2 style="margin-top:0;">Endpoints</h2>
    <table class="endpoint-table">
        <thead><tr><th>Method</th><th>Endpoint</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><span class="method method-get">GET</span></td><td>api.php?resource=technicians</td><td>List technicians (optional <code>q=</code>, <code>status=</code>)</td></tr>
            <tr><td><span class="method method-get">GET</span></td><td>api.php?resource=technicians&id=5</td><td>Get one technician</td></tr>
            <tr><td><span class="method method-post">POST</span></td><td>api.php?resource=technicians</td><td>Create a technician (JSON body)</td></tr>
            <tr><td><span class="method method-put">PUT</span></td><td>api.php?resource=technicians&id=5</td><td>Update a technician (JSON body)</td></tr>
            <tr><td><span class="method method-delete">DELETE</span></td><td>api.php?resource=technicians&id=5</td><td>Delete a technician</td></tr>
            <tr><td><span class="method method-get">GET</span></td><td>api.php?resource=jobs</td><td>List jobs (optional <code>q=</code>, <code>status=</code>, <code>technician_id=</code>)</td></tr>
            <tr><td><span class="method method-get">GET</span></td><td>api.php?resource=jobs&id=5</td><td>Get one job</td></tr>
            <tr><td><span class="method method-post">POST</span></td><td>api.php?resource=jobs</td><td>Create a job (JSON body)</td></tr>
            <tr><td><span class="method method-put">PUT</span></td><td>api.php?resource=jobs&id=5</td><td>Update a job (JSON body)</td></tr>
            <tr><td><span class="method method-delete">DELETE</span></td><td>api.php?resource=jobs&id=5</td><td>Delete a job</td></tr>
        </tbody>
    </table>
</div>

<div class="info-card" style="max-width:820px; margin-top:20px;">
    <h2 style="margin-top:0;">Example Requests</h2>

    <p><strong>List all active technicians</strong></p>
    <div class="code-block">curl "http://localhost:8000/api.php?resource=technicians&status=active" \
  -H "X-API-Key: <?= e($apiKey) ?>"</div>

    <p><strong>Create a new job</strong></p>
    <div class="code-block">curl -X POST "http://localhost:8000/api.php?resource=jobs" \
  -H "X-API-Key: <?= e($apiKey) ?>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Fix leaking pipe","client_name":"Jane Doe","status":"pending"}'</div>

    <p><strong>Assign a technician to a job</strong></p>
    <div class="code-block">curl -X PUT "http://localhost:8000/api.php?resource=jobs&id=1" \
  -H "X-API-Key: <?= e($apiKey) ?>" \
  -H "Content-Type: application/json" \
  -d '{"technician_id": 2}'</div>

    <p><strong>Delete a technician</strong></p>
    <div class="code-block">curl -X DELETE "http://localhost:8000/api.php?resource=technicians&id=5" \
  -H "X-API-Key: <?= e($apiKey) ?>"</div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
