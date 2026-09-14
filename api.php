<?php
/**
 * api.php - Simple JSON API secured by an API key.
 *
 * Auth: pass the key either as header  X-API-Key: <key>
 *                          or query    ?api_key=<key>
 * (Get your key from api_info.php after logging in.)
 *
 * Endpoints:
 *   GET    api.php?resource=technicians                -> list (supports q=, status=)
 *   GET    api.php?resource=technicians&id=5           -> get one
 *   POST   api.php?resource=technicians                -> create (JSON body)
 *   PUT    api.php?resource=technicians&id=5           -> update (JSON body)
 *   DELETE api.php?resource=technicians&id=5           -> delete
 *
 *   GET    api.php?resource=jobs                       -> list (supports q=, status=, technician_id=)
 *   GET    api.php?resource=jobs&id=5                  -> get one
 *   POST   api.php?resource=jobs                       -> create (JSON body)
 *   PUT    api.php?resource=jobs&id=5                  -> update (JSON body)
 *   DELETE api.php?resource=jobs&id=5                  -> delete
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

function respond($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- Authenticate via API key ----
$apiUser = userByApiKey($pdo, extractApiKey());
if (!$apiUser) {
    respond(['error' => 'Unauthorized. Provide a valid API key via the X-API-Key header or ?api_key= parameter.'], 401);
}

$resource = $_GET['resource'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];
$id       = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Parse JSON body for POST/PUT
$input = [];
if (in_array($method, ['POST', 'PUT'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input = $decoded;
        } else {
            // fall back to form-encoded body
            $input = $_POST;
        }
    }
}

if (!in_array($resource, ['technicians', 'jobs'], true)) {
    respond(['error' => 'Unknown resource. Use "technicians" or "jobs".'], 404);
}

// =====================================================================
// TECHNICIANS
// =====================================================================
if ($resource === 'technicians') {
    $validStatus = ['active', 'inactive', 'on_leave'];

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row ? respond($row) : respond(['error' => 'Technician not found.'], 404);
    }

    if ($method === 'GET') {
        $sql = "SELECT * FROM technicians WHERE 1=1";
        $args = [];
        if (!empty($_GET['q'])) {
            $sql .= " AND (full_name LIKE :q OR email LIKE :q OR specialty LIKE :q)";
            $args['q'] = '%' . $_GET['q'] . '%';
        }
        if (!empty($_GET['status'])) {
            $sql .= " AND status = :status";
            $args['status'] = $_GET['status'];
        }
        $sql .= " ORDER BY full_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        respond($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($method === 'POST') {
        $full_name = trim($input['full_name'] ?? '');
        if ($full_name === '') respond(['error' => 'full_name is required.'], 422);
        $status = in_array($input['status'] ?? '', $validStatus, true) ? $input['status'] : 'active';

        $stmt = $pdo->prepare("
            INSERT INTO technicians (full_name, email, phone, specialty, status, hire_date, notes)
            VALUES (:full_name, :email, :phone, :specialty, :status, :hire_date, :notes)
        ");
        $stmt->execute([
            'full_name' => $full_name,
            'email'     => $input['email'] ?? null,
            'phone'     => $input['phone'] ?? null,
            'specialty' => $input['specialty'] ?? null,
            'status'    => $status,
            'hire_date' => $input['hire_date'] ?? null,
            'notes'     => $input['notes'] ?? null,
        ]);
        $newId = $pdo->lastInsertId();
        $row = $pdo->query("SELECT * FROM technicians WHERE id = $newId")->fetch(PDO::FETCH_ASSOC);
        respond($row, 201);
    }

    if ($method === 'PUT') {
        if (!$id) respond(['error' => 'id is required.'], 422);
        $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) respond(['error' => 'Technician not found.'], 404);

        $full_name = trim($input['full_name'] ?? $existing['full_name']);
        $status = in_array($input['status'] ?? $existing['status'], $validStatus, true) ? ($input['status'] ?? $existing['status']) : $existing['status'];

        $stmt = $pdo->prepare("
            UPDATE technicians SET full_name=:full_name, email=:email, phone=:phone, specialty=:specialty,
                status=:status, hire_date=:hire_date, notes=:notes, updated_at=datetime('now')
            WHERE id=:id
        ");
        $stmt->execute([
            'full_name' => $full_name,
            'email'     => $input['email'] ?? $existing['email'],
            'phone'     => $input['phone'] ?? $existing['phone'],
            'specialty' => $input['specialty'] ?? $existing['specialty'],
            'status'    => $status,
            'hire_date' => $input['hire_date'] ?? $existing['hire_date'],
            'notes'     => $input['notes'] ?? $existing['notes'],
            'id'        => $id,
        ]);
        $row = $pdo->query("SELECT * FROM technicians WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        respond($row);
    }

    if ($method === 'DELETE') {
        if (!$id) respond(['error' => 'id is required.'], 422);
        $stmt = $pdo->prepare("DELETE FROM technicians WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->rowCount() ? respond(['success' => true, 'message' => 'Technician deleted.']) : respond(['error' => 'Technician not found.'], 404);
    }
}

// =====================================================================
// JOBS
// =====================================================================
if ($resource === 'jobs') {
    $validStatus = ['pending', 'in_progress', 'completed', 'cancelled'];

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare("
            SELECT jobs.*, technicians.full_name AS technician_name
            FROM jobs LEFT JOIN technicians ON technicians.id = jobs.technician_id
            WHERE jobs.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row ? respond($row) : respond(['error' => 'Job not found.'], 404);
    }

    if ($method === 'GET') {
        $sql = "
            SELECT jobs.*, technicians.full_name AS technician_name
            FROM jobs LEFT JOIN technicians ON technicians.id = jobs.technician_id
            WHERE 1=1
        ";
        $args = [];
        if (!empty($_GET['q'])) {
            $sql .= " AND (jobs.title LIKE :q OR jobs.client_name LIKE :q OR jobs.address LIKE :q)";
            $args['q'] = '%' . $_GET['q'] . '%';
        }
        if (!empty($_GET['status'])) {
            $sql .= " AND jobs.status = :status";
            $args['status'] = $_GET['status'];
        }
        if (isset($_GET['technician_id'])) {
            if ($_GET['technician_id'] === 'unassigned') {
                $sql .= " AND jobs.technician_id IS NULL";
            } else {
                $sql .= " AND jobs.technician_id = :tid";
                $args['tid'] = (int)$_GET['technician_id'];
            }
        }
        $sql .= " ORDER BY jobs.scheduled_date DESC, jobs.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        respond($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($method === 'POST') {
        $title = trim($input['title'] ?? '');
        if ($title === '') respond(['error' => 'title is required.'], 422);
        $status = in_array($input['status'] ?? '', $validStatus, true) ? $input['status'] : 'pending';
        $technician_id = isset($input['technician_id']) && $input['technician_id'] !== '' ? (int)$input['technician_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO jobs (title, client_name, address, description, scheduled_date, status, technician_id)
            VALUES (:title, :client_name, :address, :description, :scheduled_date, :status, :technician_id)
        ");
        $stmt->execute([
            'title'          => $title,
            'client_name'    => $input['client_name'] ?? null,
            'address'        => $input['address'] ?? null,
            'description'    => $input['description'] ?? null,
            'scheduled_date' => $input['scheduled_date'] ?? null,
            'status'         => $status,
            'technician_id'  => $technician_id,
        ]);
        $newId = $pdo->lastInsertId();
        $row = $pdo->query("SELECT * FROM jobs WHERE id = $newId")->fetch(PDO::FETCH_ASSOC);
        respond($row, 201);
    }

    if ($method === 'PUT') {
        if (!$id) respond(['error' => 'id is required.'], 422);
        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) respond(['error' => 'Job not found.'], 404);

        $title = trim($input['title'] ?? $existing['title']);
        $status = in_array($input['status'] ?? $existing['status'], $validStatus, true) ? ($input['status'] ?? $existing['status']) : $existing['status'];
        $technician_id = array_key_exists('technician_id', $input)
            ? ($input['technician_id'] !== '' && $input['technician_id'] !== null ? (int)$input['technician_id'] : null)
            : $existing['technician_id'];

        $stmt = $pdo->prepare("
            UPDATE jobs SET title=:title, client_name=:client_name, address=:address, description=:description,
                scheduled_date=:scheduled_date, status=:status, technician_id=:technician_id, updated_at=datetime('now')
            WHERE id=:id
        ");
        $stmt->execute([
            'title'          => $title,
            'client_name'    => $input['client_name'] ?? $existing['client_name'],
            'address'        => $input['address'] ?? $existing['address'],
            'description'    => $input['description'] ?? $existing['description'],
            'scheduled_date' => $input['scheduled_date'] ?? $existing['scheduled_date'],
            'status'         => $status,
            'technician_id'  => $technician_id,
            'id'             => $id,
        ]);
        $row = $pdo->query("SELECT * FROM jobs WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        respond($row);
    }

    if ($method === 'DELETE') {
        if (!$id) respond(['error' => 'id is required.'], 422);
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->rowCount() ? respond(['success' => true, 'message' => 'Job deleted.']) : respond(['error' => 'Job not found.'], 404);
    }
}

respond(['error' => 'Method not supported for this resource.'], 405);
