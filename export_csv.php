<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$type = $_GET['type'] ?? 'technicians';

if ($type === 'jobs') {
    $rows = $pdo->query("
        SELECT jobs.id, jobs.title, jobs.client_name, jobs.address, jobs.description,
               jobs.scheduled_date, jobs.status, technicians.full_name AS technician,
               jobs.created_at, jobs.updated_at
        FROM jobs LEFT JOIN technicians ON technicians.id = jobs.technician_id
        ORDER BY jobs.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $filename = 'jobs_export_' . date('Y-m-d') . '.csv';
    $headers = ['ID', 'Title', 'Client', 'Address', 'Description', 'Scheduled Date', 'Status', 'Technician', 'Created At', 'Updated At'];
} else {
    $type = 'technicians';
    $rows = $pdo->query("SELECT * FROM technicians ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filename = 'technicians_export_' . date('Y-m-d') . '.csv';
    $headers = ['ID', 'Full Name', 'Email', 'Phone', 'Specialty', 'Status', 'Hire Date', 'Notes', 'Created At', 'Updated At'];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens accented characters correctly
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $headers);

foreach ($rows as $row) {
    fputcsv($out, array_values($row));
}

fclose($out);
exit;
