<?php
/**
 * layout_top.php - Opens the HTML page + renders the sidebar.
 * Expects $pageTitle (string) and $activePage (one of: technicians, jobs, export, api) to be set
 * by the including page before this file is included.
 */
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Service Technicians Manager') ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">&#128295; Techs Manager</div>
        <nav>
            <a href="index.php" class="<?= ($activePage ?? '') === 'technicians' ? 'active' : '' ?>">&#128101; Technicians</a>
            <a href="jobs.php" class="<?= ($activePage ?? '') === 'jobs' ? 'active' : '' ?>">&#128203; Jobs</a>
            <a href="export_csv.php?type=technicians" class="<?= ($activePage ?? '') === 'export' ? 'active' : '' ?>">&#11015; Export CSV</a>
            <a href="api_info.php" class="<?= ($activePage ?? '') === 'api' ? 'active' : '' ?>">&#128272; API Access</a>
        </nav>
        <div class="sidebar-footer">
            Logged in as<br><strong style="color:#e5e7eb;"><?= htmlspecialchars($user['username'] ?? '') ?></strong>
        </div>
    </aside>
    <div class="main">
        <div class="topbar">
            <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
            <div class="user-chip">
                <span><?= htmlspecialchars($user['username'] ?? '') ?></span>
                <a href="logout.php">Log out</a>
            </div>
        </div>
        <div class="container">
