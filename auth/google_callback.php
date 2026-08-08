<?php
/**
 * Google OAuth Callback Handler
 * 
 * Handles the redirect from Google after user consent.
 * Exchanges auth code for access token, fetches user info,
 * and creates/logs in the user.
 */
session_start();

require_once '../includes/config.php';

// --- 1. Verify state token (CSRF protection) ---
if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    unset($_SESSION['google_oauth_state']);
    header('Location: ../login.php?error=invalid_state');
    exit();
}
unset($_SESSION['google_oauth_state']);

// --- 2. Check for errors from Google ---
if (isset($_GET['error'])) {
    header('Location: ../login.php?error=google_denied');
    exit();
}

// --- 3. Check for authorization code ---
if (!isset($_GET['code'])) {
    header('Location: ../login.php?error=no_code');
    exit();
}

$code = $_GET['code'];

// --- 4. Exchange authorization code for access token ---
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($tokenData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 15,
]);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch) || $httpCode !== 200) {
    curl_close($ch);
    header('Location: ../login.php?error=token_failed');
    exit();
}
curl_close($ch);

$tokenResult = json_decode($tokenResponse, true);

if (!isset($tokenResult['access_token'])) {
    header('Location: ../login.php?error=no_token');
    exit();
}

$accessToken = $tokenResult['access_token'];

// --- 5. Fetch user info from Google ---
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

$ch = curl_init($userInfoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 15,
]);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch) || $httpCode !== 200) {
    curl_close($ch);
    header('Location: ../login.php?error=userinfo_failed');
    exit();
}
curl_close($ch);

$googleUser = json_decode($userResponse, true);

if (!isset($googleUser['id']) || !isset($googleUser['email'])) {
    header('Location: ../login.php?error=invalid_user');
    exit();
}

$googleId    = $googleUser['id'];
$googleEmail = $googleUser['email'];
$googleName  = $googleUser['name'] ?? $googleUser['email'];

// --- 6. Find or create user in database ---

// First, check if user exists by google_id
$stmt = $conn->prepare("SELECT id, name, role FROM users WHERE google_id = ?");
$stmt->bind_param("s", $googleId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // User found by google_id → login
    $_SESSION['user_id']   = $row['id'];
    $_SESSION['user_name'] = $row['name'];
    $_SESSION['role']  = $row['role'];

    if ($row['role'] === 'admin') {
        header('Location: ../admin/index.php');
    } elseif ($row['role'] === 'expert') {
        header('Location: ../expert/index.php');
    } else {
        header('Location: ../dashboard.php');
    }
    exit();
}

// Second, check if user exists by email (existing user linking Google)
$stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email = ?");
$stmt->bind_param("s", $googleEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // User found by email → link google_id and login
    $updateStmt = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
    $updateStmt->bind_param("si", $googleId, $row['id']);
    $updateStmt->execute();

    $_SESSION['user_id']   = $row['id'];
    $_SESSION['user_name'] = $row['name'];
    $_SESSION['role']  = $row['role'];

    if ($row['role'] === 'admin') {
        header('Location: ../admin/index.php');
    } elseif ($row['role'] === 'expert') {
        header('Location: ../expert/index.php');
    } else {
        header('Location: ../dashboard.php');
    }
    exit();
}

// Third, create new user (auto-register via Google)
$stmt = $conn->prepare("INSERT INTO users (name, email, google_id, password, role) VALUES (?, ?, ?, NULL, 'user')");
$stmt->bind_param("sss", $googleName, $googleEmail, $googleId);

if ($stmt->execute()) {
    $newUserId = $conn->insert_id;

    $_SESSION['user_id']   = $newUserId;
    $_SESSION['user_name'] = $googleName;
    $_SESSION['role']  = 'user';

    header('Location: ../dashboard.php');
    exit();
} else {
    header('Location: ../login.php?error=register_failed');
    exit();
}
?>
