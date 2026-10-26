<?php

namespace Helpers;

class FormHelper
{
    /**
     * Preserve submitted input value
     */
    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Render a select option helper
     */
    public static function isSelected(mixed $value, mixed $compare): string
    {
        return ((string)$value === (string)$compare) ? 'selected' : '';
    }

    /**
     * Escape output for HTML
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate CSRF token
     */
    public static function csrfField(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrf(): bool
    {
        return isset($_POST['csrf_token'])
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }

    /**
     * Set flash message
     */
    public static function setFlash(string $message, string $type = 'info'): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
}
