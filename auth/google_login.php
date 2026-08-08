<?php
/**
 * Google OAuth Login Initiator
 * 
 * Generates the Google OAuth consent URL and redirects the user.
 * Includes CSRF protection via state token.
 */
session_start();

require_once '../includes/config.php';

// Generate CSRF state token
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

// Build Google OAuth URL
$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $authUrl);
exit();
?>
