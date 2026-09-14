<?php
/**
 * db.php - Shared database bootstrap.
 * Creates the SQLite database and tables on first run, seeds sample data.
 * Included by every page in the app.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbFile = __DIR__ . '/technicians.db';
$isNewDb = !file_exists($dbFile);

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// ---- Technicians table ----
$pdo->exec("
    CREATE TABLE IF NOT EXISTS technicians (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name     TEXT NOT NULL,
        email         TEXT,
        phone         TEXT,
        specialty     TEXT,
        status        TEXT NOT NULL DEFAULT 'active',   -- active | inactive | on_leave
        hire_date     TEXT,
        notes         TEXT,
        created_at    TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
    )
");

// ---- Jobs table ----
$pdo->exec("
    CREATE TABLE IF NOT EXISTS jobs (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        title           TEXT NOT NULL,
        client_name     TEXT,
        address         TEXT,
        description     TEXT,
        scheduled_date  TEXT,
        status          TEXT NOT NULL DEFAULT 'pending', -- pending | in_progress | completed | cancelled
        technician_id   INTEGER,
        created_at      TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
    )
");

// ---- Users table (for login + API key) ----
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        username       TEXT NOT NULL UNIQUE,
        password_hash  TEXT NOT NULL,
        api_key        TEXT NOT NULL UNIQUE,
        created_at     TEXT NOT NULL DEFAULT (datetime('now'))
    )
");

// ---- Seed data on first run ----
if ($isNewDb) {
    $seedTech = $pdo->prepare("
        INSERT INTO technicians (full_name, email, phone, specialty, status, hire_date, notes)
        VALUES (:full_name, :email, :phone, :specialty, :status, :hire_date, :notes)
    ");
    $technicians = [
        ['full_name' => 'John Reyes',   'email' => 'john.reyes@example.com',   'phone' => '0917-111-2222', 'specialty' => 'HVAC',       'status' => 'active',   'hire_date' => '2022-03-15', 'notes' => 'Certified HVAC technician, handles residential AC units.'],
        ['full_name' => 'Maria Santos', 'email' => 'maria.santos@example.com', 'phone' => '0918-222-3333', 'specialty' => 'Electrical', 'status' => 'active',   'hire_date' => '2021-07-01', 'notes' => 'Licensed electrician, 5 years experience.'],
        ['full_name' => 'Carlos Dizon', 'email' => 'carlos.dizon@example.com', 'phone' => '0919-333-4444', 'specialty' => 'Plumbing',   'status' => 'on_leave', 'hire_date' => '2023-01-10', 'notes' => 'On leave until next month.'],
    ];
    foreach ($technicians as $t) {
        $seedTech->execute($t);
    }

    $seedJob = $pdo->prepare("
        INSERT INTO jobs (title, client_name, address, description, scheduled_date, status, technician_id)
        VALUES (:title, :client_name, :address, :description, :scheduled_date, :status, :technician_id)
    ");
    $jobs = [
        ['title' => 'AC unit not cooling',        'client_name' => 'Anna Cruz',     'address' => '123 Mabini St, Cebu City',  'description' => 'Customer reports AC blows warm air.',            'scheduled_date' => date('Y-m-d', strtotime('+1 day')),  'status' => 'pending',     'technician_id' => 1],
        ['title' => 'Panel breaker tripping',     'client_name' => 'Mark Lim',      'address' => '45 Osmena Blvd, Cebu City', 'description' => 'Breaker trips every time the AC starts.',        'scheduled_date' => date('Y-m-d'),                       'status' => 'in_progress','technician_id' => 2],
        ['title' => 'Leaking kitchen faucet',     'client_name' => 'Grace Uy',      'address' => '78 Colon St, Cebu City',   'description' => 'Slow drip under the kitchen sink.',              'scheduled_date' => date('Y-m-d', strtotime('-2 days')), 'status' => 'completed',  'technician_id' => 3],
        ['title' => 'New outlet installation',    'client_name' => 'Peter Tan',     'address' => '9 Fuente Circle, Cebu City','description' => 'Add 2 new outlets in home office.',              'scheduled_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'pending',     'technician_id' => null],
    ];
    foreach ($jobs as $j) {
        $seedJob->execute($j);
    }

    // Default admin account: username "admin", password "admin123"
    $apiKey = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users (username, password_hash, api_key) VALUES (:u, :p, :k)")
        ->execute([
            'u' => 'admin',
            'p' => password_hash('admin123', PASSWORD_DEFAULT),
            'k' => $apiKey,
        ]);
}
