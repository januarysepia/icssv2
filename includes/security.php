<?php

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid or expired request token. Please go back and try again.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method Not Allowed');
    }
}

function safe_local_redirect($redirect, string $default = 'dashboard/index.php'): string
{
    if (!is_string($redirect) || $redirect === '') {
        return $default;
    }

    $decoded = rawurldecode($redirect);

    if (
        str_contains($decoded, "\r") ||
        str_contains($decoded, "\n") ||
        str_starts_with($decoded, '//') ||
        preg_match('/^[a-z][a-z0-9+.-]*:/i', $decoded)
    ) {
        return $default;
    }

    return ltrim($decoded, '/');
}

