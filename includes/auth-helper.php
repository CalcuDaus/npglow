<?php
/**
 * NPGLOW Auth & Role Validation Helper
 * Manages access control, role redirection, and purchasing permissions.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get the current user's role
 * @return string|null 'user', 'admin', 'expert', 'reseller', or null
 */
function get_current_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Check if the user is currently authenticated
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the authenticated user is an Administrator
 * @return bool
 */
function is_admin_user() {
    return is_logged_in() && (get_current_user_role() === 'admin');
}

/**
 * Check if the authenticated user is an Expert (Tim Ahli)
 * @return bool
 */
function is_expert_user() {
    return is_logged_in() && (get_current_user_role() === 'expert');
}

/**
 * Check if the authenticated user is a Reseller
 * @return bool
 */
function is_reseller_user() {
    return is_logged_in() && (get_current_user_role() === 'reseller');
}

/**
 * Check if the user is authorized to purchase products
 * Only customer/user role is permitted. Admin, expert, and reseller cannot buy.
 * @return bool
 */
function can_buy_products() {
    $role = get_current_user_role();
    return $role !== 'admin' && $role !== 'expert' && $role !== 'reseller';
}

/**
 * Guard for Landing Page (index.php)
 * Automatically redirects admin and expert away from the customer landing page.
 */
function guard_landing_page() {
    if (is_logged_in()) {
        $role = get_current_user_role();
        if ($role === 'admin') {
            header("Location: admin/index.php");
            exit();
        } elseif ($role === 'expert') {
            header("Location: expert/index.php");
            exit();
        } elseif ($role === 'reseller') {
            header("Location: reseller/index.php");
            exit();
        }
    }
}

/**
 * Guard for Customer Dashboard & Main User App (dashboard.php, konsultasi.php, journal.php, profile.php)
 * Directs staff/experts/admins to their dedicated management portals.
 */
function guard_customer_only($redirectIfGuest = true) {
    if (!is_logged_in()) {
        if ($redirectIfGuest) {
            $redirectUrl = urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
            header("Location: login.php?redirect={$redirectUrl}");
            exit();
        }
        return;
    }

    $role = get_current_user_role();
    if ($role === 'admin') {
        header("Location: admin/index.php");
        exit();
    } elseif ($role === 'expert') {
        header("Location: expert/index.php");
        exit();
    } elseif ($role === 'reseller') {
        header("Location: reseller/index.php");
        exit();
    }
}

/**
 * Guard for Checkout & Payment Flow (checkout.php, payment.php, my-orders.php, order-tracking.php)
 * Strictly ensures only customer accounts can buy or access checkout.
 */
function guard_buyer_only($redirectIfGuest = true) {
    if (!is_logged_in()) {
        if ($redirectIfGuest) {
            $redirectUrl = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
            header("Location: login.php?redirect={$redirectUrl}");
            exit();
        }
        return;
    }

    $role = get_current_user_role();
    if ($role === 'admin') {
        header("Location: admin/index.php?notice=buyer_only");
        exit();
    } elseif ($role === 'expert') {
        header("Location: expert/index.php?notice=buyer_only");
        exit();
    } elseif ($role === 'reseller') {
        header("Location: reseller/index.php?notice=buyer_only");
        exit();
    }
}

/**
 * Guard for Admin Portal
 */
function guard_admin_only() {
    if (!is_logged_in() || !is_admin_user()) {
        header("Location: ../login.php");
        exit();
    }
}

/**
 * Guard for Expert Portal
 */
function guard_expert_only() {
    if (!is_logged_in() || (!is_expert_user() && !is_admin_user())) {
        header("Location: ../login.php");
        exit();
    }
}

/**
 * Guard for Reseller Portal
 */
function guard_reseller_only() {
    if (!is_logged_in() || !is_reseller_user()) {
        header("Location: ../login.php");
        exit();
    }
}
