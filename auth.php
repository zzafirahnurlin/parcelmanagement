<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

function currentUserID(): string { return $_SESSION['userID'] ?? ''; }
function currentRole(): string   { return $_SESSION['role'] ?? ''; }
function isLoggedIn(): bool      { return currentUserID() !== ''; }
function homeFor(string $role): string { return BASE_URL . 'dashboard.php'; }
function requireLogin(): void { if (!isLoggedIn()) { header('Location: ' . BASE_URL . 'login.php'); exit; } }
function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles, true)) { header('Location: ' . homeFor(currentRole())); exit; }
}