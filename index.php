<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

function e($value) { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect($params = []) {
    $query = http_build_query($params);
    header('Location: index.php' . ($query ? '?' . $query : ''));
    exit;
}

$statusLabels = ['active' => 'Active', 'inactive' => 'Inactive', 'on_leave' => 'On Leave'];

$action = $_GET['action'] ?? 'list';
$errors = [];
$flash  = $_GET['flash'] ?? '';

// ---- CREATE ----
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $status    = $_POST['status'] ?? 'active';
    $hire_date = trim($_POST['hire_date'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if ($full_name === '') $errors[] = 'Full name is required.';
    if (!array_key_exists($status, $statusLabels)) $status = 'active';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO technicians (full_name, email, phone, specialty, status, hire_date, notes)
            VALUES (:full_name, :email, :phone, :specialty, :status, :hire_date, :notes)
        ");
        $stmt->execute(compact('full_name', 'email', 'phone', 'specialty', 'status', 'hire_date', 'notes'));
        redirect(['flash' => 'Technician added successfully.']);
    }
}

// ---- UPDATE ----
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $status    = $_POST['status'] ?? 'active';
    $hire_date = trim($_POST['hire_date'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if ($full_name === '') $errors[] = 'Full name is required.';
    if (!array_key_exists($status, $statusLabels)) $status = 'active';

    if (empty($errors) && $id > 0) {
        $stmt = $pdo->prepare("
            UPDATE technicians SET full_name=:full_name, email=:email, phone=:phone, specialty=:specialty,
                status=:status, hire_date=:hire_date, notes=:notes, updated_at=datetime('now')
            WHERE id=:id
        ");
        $stmt->execute(compact('full_name', 'email', 'phone', 'specialty', 'status', 'hire_date', 'notes', 'id'));
        redirect(['flash' => 'Technician updated successfully.']);
    }
}

// ---- DELETE ----
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM technicians WHERE id = :id")->execute(['id' => $id]);
    }
    redirect(['flash' => 'Technician deleted. Any assigned jobs were unassigned.']);
}

// ---- Fetch single technician (edit/view) ----
$editingTech = null;
if (in_array($action, ['edit', 'view'], true)) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $editingTech = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editingTech) redirect(['flash' => 'Technician not found.']);

    // jobs assigned to this technician, for the view page
    if ($action === 'view') {
        $jobStmt = $pdo->prepare("SELECT * FROM jobs WHERE technician_id = :id ORDER BY scheduled_date DESC");
        $jobStmt->execute(['id' => $id]);
        $techJobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ---- LIST (search + filter) ----
$search       = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT * FROM technicians WHERE 1=1";
$args = [];
if ($search !== '') {
    $sql .= " AND (full_name LIKE :search OR email LIKE :search OR specialty LIKE :search)";
    $args['search'] = '%' . $search . '%';
}
if ($statusFilter !== '' && array_key_exists($statusFilter, $statusLabels)) {
    $sql .= " AND status = :status";
    $args['status'] = $statusFilter;
}
$sql .= " ORDER BY full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT status, COUNT(*) as c FROM technicians GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($counts);

$pageTitle = 'Technicians';
$activePage = 'technicians';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="errors"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>

    <div class="cards">
        <div class="card"><div class="num"><?= (int)$totalCount ?></div><div class="label">Total</div></div>
        <div class="card"><div class="num"><?= (int)($counts['active'] ?? 0) ?></div><div class="label">Active</div></div>
        <div class="card"><div class="num"><?= (int)($counts['on_leave'] ?? 0) ?></div><div class="label">On Leave</div></div>
        <div class="card"><div class="num"><?= (int)($counts['inactive'] ?? 0) ?></div><div class="label">Inactive</div></div>
    </div>

    <div class="toolbar">
        <form method="get" class="filters">
            <input type="text" name="q" placeholder="Search name, email, specialty..." value="<?= e($search) ?>">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit">Filter</button>
            <?php if ($search !== '' || $statusFilter !== ''): ?><a class="btn btn-secondary" href="index.php">Reset</a><?php endif; ?>
        </form>
        <div class="right-actions">
            <a class="btn btn-secondary" href="export_csv.php?type=technicians">&#11015; Export CSV</a>
            <a class="btn" href="index.php?action=new">+ Add Technician</a>
        </div>
    </div>

    <?php if (empty($technicians)): ?>
        <div class="empty">No technicians found.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Name</th><th>Specialty</th><th>Contact</th><th>Status</th><th>Hire Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($technicians as $t): ?>
                <tr>
                    <td data-label="Name"><strong><?= e($t['full_name']) ?></strong></td>
                    <td data-label="Specialty"><?= e($t['specialty'] ?: '&mdash;') ?></td>
                    <td data-label="Contact"><?= e($t['phone']) ?><br><span style="color:var(--gray-500)"><?= e($t['email']) ?></span></td>
                    <td data-label="Status"><span class="badge badge-<?= e($t['status']) ?>"><?= e($statusLabels[$t['status']] ?? $t['status']) ?></span></td>
                    <td data-label="Hire Date"><?= e($t['hire_date'] ?: '&mdash;') ?></td>
                    <td data-label="Actions">
                        <div class="actions" style="display:flex; gap:6px;">
                            <a class="btn btn-sm btn-secondary" href="index.php?action=view&id=<?= (int)$t['id'] ?>">View</a>
                            <a class="btn btn-sm" href="index.php?action=edit&id=<?= (int)$t['id'] ?>">Edit</a>
                            <form method="post" action="index.php?action=delete" onsubmit="return confirm('Delete this technician? Any assigned jobs will be unassigned.');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
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

    <?php $t = $editingTech ?? []; $isEdit = $action === 'edit'; ?>
    <div class="form-card">
        <form method="post" action="index.php?action=<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="field full"><label>Full Name *</label><input type="text" name="full_name" required value="<?= e($t['full_name'] ?? '') ?>"></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($t['email'] ?? '') ?>"></div>
                <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= e($t['phone'] ?? '') ?>"></div>
                <div class="field"><label>Specialty</label><input type="text" name="specialty" placeholder="e.g. HVAC, Electrical" value="<?= e($t['specialty'] ?? '') ?>"></div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= (($t['status'] ?? 'active') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Hire Date</label><input type="date" name="hire_date" value="<?= e($t['hire_date'] ?? '') ?>"></div>
                <div class="field full"><label>Notes</label><textarea name="notes" rows="3"><?= e($t['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit"><?= $isEdit ? 'Save Changes' : 'Add Technician' ?></button>
                <a class="btn btn-secondary" href="index.php">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($action === 'view' && $editingTech): ?>

    <?php $t = $editingTech; ?>
    <div class="form-card" style="margin-bottom:20px;">
        <h2 style="margin-top:0;"><?= e($t['full_name']) ?></h2>
        <dl class="view-grid">
            <dt>Status</dt><dd><span class="badge badge-<?= e($t['status']) ?>"><?= e($statusLabels[$t['status']] ?? $t['status']) ?></span></dd>
            <dt>Specialty</dt><dd><?= e($t['specialty'] ?: '&mdash;') ?></dd>
            <dt>Email</dt><dd><?= e($t['email'] ?: '&mdash;') ?></dd>
            <dt>Phone</dt><dd><?= e($t['phone'] ?: '&mdash;') ?></dd>
            <dt>Hire Date</dt><dd><?= e($t['hire_date'] ?: '&mdash;') ?></dd>
            <dt>Notes</dt><dd><?= nl2br(e($t['notes'] ?: '&mdash;')) ?></dd>
            <dt>Added On</dt><dd><?= e($t['created_at']) ?></dd>
            <dt>Last Updated</dt><dd><?= e($t['updated_at']) ?></dd>
        </dl>
        <div class="form-actions">
            <a class="btn" href="index.php?action=edit&id=<?= (int)$t['id'] ?>">Edit</a>
            <a class="btn btn-secondary" href="index.php">Back to list</a>
        </div>
    </div>

    <h3>Assigned Jobs</h3>
    <?php if (empty($techJobs)): ?>
        <div class="empty">No jobs assigned to this technician yet.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Job</th><th>Client</th><th>Scheduled</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($techJobs as $j): ?>
                <tr>
                    <td data-label="Job"><?= e($j['title']) ?></td>
                    <td data-label="Client"><?= e($j['client_name'] ?: '&mdash;') ?></td>
                    <td data-label="Scheduled"><?= e($j['scheduled_date'] ?: '&mdash;') ?></td>
                    <td data-label="Status"><span class="badge badge-<?= e($j['status']) ?>"><?= e(ucwords(str_replace('_',' ',$j['status']))) ?></span></td>
                    <td><a class="btn btn-sm btn-secondary" href="jobs.php?action=view&id=<?= (int)$j['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
