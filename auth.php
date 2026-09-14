<?php
/**
 * auth.php - Authentication helpers.
 * Requires db.php to already be included (needs $pdo and a started session).
 */

function currentUser() {
    return $_SESSION['user'] ?? null;
}

/** Redirect to login page if nobody is logged in. Call at the top of protected pages. */
function requireLogin() {
    if (!currentUser()) {
        header('Location: login.php');
        exit;
    }
}

/** Attempt to log a user in. Returns true on success. */
function attemptLogin(PDO $pdo, string $username, string $password): bool {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
        ];
        return true;
    }
    return false;
}

/** Look up a user by their API key. Returns the user row or null. */
function userByApiKey(PDO $pdo, ?string $apiKey) {
    if (!$apiKey) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = :k");
    $stmt->execute(['k' => $apiKey]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

/** Extract an API key from the request (header X-API-Key, or ?api_key=). */
function extractApiKey(): ?string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'X-API-Key') === 0) {
            return $value;
        }
    }
    return $_GET['api_key'] ?? $_POST['api_key'] ?? null;
}
