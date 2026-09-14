<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

function e($value) { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirectJobs($params = []) {
    $query = http_build_query($params);
    header('Location: jobs.php' . ($query ? '?' . $query : ''));
    exit;
}

$jobStatusLabels = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

$action = $_GET['action'] ?? 'list';
$errors = [];
$flash  = $_GET['flash'] ?? '';

// All technicians, for the assignment dropdown
$allTechs = $pdo->query("SELECT id, full_name, status FROM technicians ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---- CREATE ----
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = trim($_POST['title'] ?? '');
    $client_name    = trim($_POST['client_name'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $scheduled_date = trim($_POST['scheduled_date'] ?? '');
    $status         = $_POST['status'] ?? 'pending';
    $technician_id  = $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;

    if ($title === '') $errors[] = 'Job title is required.';
    if (!array_key_exists($status, $jobStatusLabels)) $status = 'pending';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO jobs (title, client_name, address, description, scheduled_date, status, technician_id)
            VALUES (:title, :client_name, :address, :description, :scheduled_date, :status, :technician_id)
        ");
        $stmt->execute(compact('title', 'client_name', 'address', 'description', 'scheduled_date', 'status', 'technician_id'));
        redirectJobs(['flash' => 'Job created successfully.']);
    }
}

// ---- UPDATE ----
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $title          = trim($_POST['title'] ?? '');
    $client_name    = trim($_POST['client_name'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $scheduled_date = trim($_POST['scheduled_date'] ?? '');
    $status         = $_POST['status'] ?? 'pending';
    $technician_id  = $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;

    if ($title === '') $errors[] = 'Job title is required.';
    if (!array_key_exists($status, $jobStatusLabels)) $status = 'pending';

    if (empty($errors) && $id > 0) {
        $stmt = $pdo->prepare("
            UPDATE jobs SET title=:title, client_name=:client_name, address=:address, description=:description,
                scheduled_date=:scheduled_date, status=:status, technician_id=:technician_id, updated_at=datetime('now')
            WHERE id=:id
        ");
        $stmt->execute(compact('title', 'client_name', 'address', 'description', 'scheduled_date', 'status', 'technician_id', 'id'));
        redirectJobs(['flash' => 'Job updated successfully.']);
    }
}

// ---- QUICK ASSIGN (from list view dropdown) ----
if ($action === 'assign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $technician_id = $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;
    if ($id > 0) {
        $pdo->prepare("UPDATE jobs SET technician_id = :tid, updated_at = datetime('now') WHERE id = :id")
            ->execute(['tid' => $technician_id, 'id' => $id]);
    }
    redirectJobs(['flash' => 'Technician assignment updated.']);
}

// ---- DELETE ----
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM jobs WHERE id = :id")->execute(['id' => $id]);
    }
    redirectJobs(['flash' => 'Job deleted.']);
}

// ---- Fetch single job (edit/view) ----
$editingJob = null;
if (in_array($action, ['edit', 'view'], true)) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT jobs.*, technicians.full_name AS technician_name
        FROM jobs LEFT JOIN technicians ON technicians.id = jobs.technician_id
        WHERE jobs.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $editingJob = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editingJob) redirectJobs(['flash' => 'Job not found.']);
}

// ---- LIST (search + filters) ----
$search        = trim($_GET['q'] ?? '');
$statusFilter  = $_GET['status'] ?? '';
$techFilter    = $_GET['technician_id'] ?? '';

$sql = "
    SELECT jobs.*, technicians.full_name AS technician_name
    FROM jobs LEFT JOIN technicians ON technicians.id = jobs.technician_id
    WHERE 1=1
";
$args = [];
if ($search !== '') {
    $sql .= " AND (jobs.title LIKE :search OR jobs.client_name LIKE :search OR jobs.address LIKE :search)";
    $args['search'] = '%' . $search . '%';
}
if ($statusFilter !== '' && array_key_exists($statusFilter, $jobStatusLabels)) {
    $sql .= " AND jobs.status = :status";
    $args['status'] = $statusFilter;
}
if ($techFilter !== '') {
    if ($techFilter === 'unassigned') {
        $sql .= " AND jobs.technician_id IS NULL";
    } else {
        $sql .= " AND jobs.technician_id = :tid";
        $args['tid'] = (int)$techFilter;
    }
}
$sql .= " ORDER BY jobs.scheduled_date DESC, jobs.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jobCounts = $pdo->query("SELECT status, COUNT(*) as c FROM jobs GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalJobs = array_sum($jobCounts);
$unassignedCount = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE technician_id IS NULL")->fetchColumn();

$pageTitle = 'Jobs';
$activePage = 'jobs';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="errors"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>

    <div class="cards">
        <div class="card"><div class="num"><?= (int)$totalJobs ?></div><div class="label">Total Jobs</div></div>
        <div class="card"><div class="num"><?= (int)($jobCounts['pending'] ?? 0) ?></div><div class="label">Pending</div></div>
        <div class="card"><div class="num"><?= (int)($jobCounts['in_progress'] ?? 0) ?></div><div class="label">In Progress</div></div>
        <div class="card"><div class="num"><?= (int)($jobCounts['completed'] ?? 0) ?></div><div class="label">Completed</div></div>
        <div class="card"><div class="num"><?= $unassignedCount ?></div><div class="label">Unassigned</div></div>
    </div>

    <div class="toolbar">
        <form method="get" class="filters">
            <input type="text" name="q" placeholder="Search title, client, address..." value="<?= e($search) ?>">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($jobStatusLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="technician_id">
                <option value="">All technicians</option>
                <option value="unassigned" <?= $techFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                <?php foreach ($allTechs as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (string)$techFilter === (string)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit">Filter</button>
            <?php if ($search !== '' || $statusFilter !== '' || $techFilter !== ''): ?><a class="btn btn-secondary" href="jobs.php">Reset</a><?php endif; ?>
        </form>
        <div class="right-actions">
            <a class="btn btn-secondary" href="export_csv.php?type=jobs">&#11015; Export CSV</a>
            <a class="btn" href="jobs.php?action=new">+ New Job</a>
        </div>
    </div>

    <?php if (empty($jobs)): ?>
        <div class="empty">No jobs found.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Job</th><th>Client</th><th>Scheduled</th><th>Status</th><th>Technician</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $j): ?>
                <tr>
                    <td data-label="Job"><strong><?= e($j['title']) ?></strong></td>
                    <td data-label="Client"><?= e($j['client_name'] ?: '&mdash;') ?></td>
                    <td data-label="Scheduled"><?= e($j['scheduled_date'] ?: '&mdash;') ?></td>
                    <td data-label="Status"><span class="badge badge-<?= e($j['status']) ?>"><?= e($jobStatusLabels[$j['status']] ?? $j['status']) ?></span></td>
                    <td data-label="Technician">
                        <form method="post" action="jobs.php?action=assign" style="display:flex; gap:6px; align-items:center;">
                            <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                            <select name="technician_id" onchange="this.form.submit()">
                                <option value="">&mdash; Unassigned &mdash;</option>
                                <?php foreach ($allTechs as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>" <?= (int)$j['technician_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td data-label="Actions">
                        <div style="display:flex; gap:6px;">
                            <a class="btn btn-sm btn-secondary" href="jobs.php?action=view&id=<?= (int)$j['id'] ?>">View</a>
                            <a class="btn btn-sm" href="jobs.php?action=edit&id=<?= (int)$j['id'] ?>">Edit</a>
                            <form method="post" action="jobs.php?action=delete" onsubmit="return confirm('Delete this job?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php elseif ($action === 'new' || $action === 'edit'): ?>

    <?php $j = $editingJob ?? []; $isEdit = $action === 'edit'; ?>
    <div class="form-card">
        <form method="post" action="jobs.php?action=<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$j['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="field full"><label>Job Title *</label><input type="text" name="title" required value="<?= e($j['title'] ?? '') ?>"></div>
                <div class="field"><label>Client Name</label><input type="text" name="client_name" value="<?= e($j['client_name'] ?? '') ?>"></div>
                <div class="field"><label>Scheduled Date</label><input type="date" name="scheduled_date" value="<?= e($j['scheduled_date'] ?? '') ?>"></div>
                <div class="field full"><label>Address</label><input type="text" name="address" value="<?= e($j['address'] ?? '') ?>"></div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach ($jobStatusLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= (($j['status'] ?? 'pending') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Assigned Technician</label>
                    <select name="technician_id">
                        <option value="">&mdash; Unassigned &mdash;</option>
                        <?php foreach ($allTechs as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= (int)($j['technician_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?> <?= $t['status'] !== 'active' ? '(' . e($t['status']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field full"><label>Description</label><textarea name="description" rows="3"><?= e($j['description'] ?? '') ?></textarea></div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Job' ?></button>
                <a class="btn btn-secondary" href="jobs.php">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($action === 'view' && $editingJob): ?>

    <?php $j = $editingJob; ?>
    <div class="form-card">
        <h2 style="margin-top:0;"><?= e($j['title']) ?></h2>
        <dl class="view-grid">
            <dt>Status</dt><dd><span class="badge badge-<?= e($j['status']) ?>"><?= e($jobStatusLabels[$j['status']] ?? $j['status']) ?></span></dd>
            <dt>Client</dt><dd><?= e($j['client_name'] ?: '&mdash;') ?></dd>
            <dt>Address</dt><dd><?= e($j['address'] ?: '&mdash;') ?></dd>
            <dt>Scheduled Date</dt><dd><?= e($j['scheduled_date'] ?: '&mdash;') ?></dd>
            <dt>Technician</dt><dd><?= $j['technician_name'] ? e($j['technician_name']) : '<em>Unassigned</em>' ?></dd>
            <dt>Description</dt><dd><?= nl2br(e($j['description'] ?: '&mdash;')) ?></dd>
            <dt>Created</dt><dd><?= e($j['created_at']) ?></dd>
            <dt>Last Updated</dt><dd><?= e($j['updated_at']) ?></dd>
        </dl>
        <div class="form-actions">
            <a class="btn" href="jobs.php?action=edit&id=<?= (int)$j['id'] ?>">Edit</a>
            <a class="btn btn-secondary" href="jobs.php">Back to list</a>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
