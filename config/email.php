<?php

function icssEmailConfig(): array
{
    $default_email = 'janronniecalayag@gmail.com';

    return [
        'smtp_host' => getenv('ICSS_SMTP_HOST') ?: 'smtp.gmail.com',
        'smtp_port' => (int) (getenv('ICSS_SMTP_PORT') ?: 587),
        'smtp_username' => getenv('ICSS_SMTP_USERNAME') ?: $default_email,
        'smtp_password' => getenv('ICSS_SMTP_PASSWORD') ?: '',
        'smtp_encryption' => getenv('ICSS_SMTP_ENCRYPTION') ?: 'tls',
        'from_email' => getenv('ICSS_SMTP_FROM_EMAIL') ?: (getenv('ICSS_SMTP_USERNAME') ?: $default_email),
        'from_name' => getenv('ICSS_SMTP_FROM_NAME') ?: 'ICSS v2 ERP System',
        'boss_approval_email' => getenv('ICSS_BOSS_APPROVAL_EMAIL') ?: $default_email,
        'app_url' => rtrim(getenv('ICSS_APP_URL') ?: '', '/'),
    ];
}

function icssAppUrl(): string
{
    $config = icssEmailConfig();
    if ($config['app_url'] !== '') {
        return $config['app_url'];
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host)) {
        $lan_ip = gethostbyname(gethostname());
        if ($lan_ip !== gethostname() && filter_var($lan_ip, FILTER_VALIDATE_IP)) {
            $port = str_contains($host, ':') ? ':' . explode(':', $host, 2)[1] : '';
            $host = $lan_ip . $port;
        }
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . $host . '/icssv2';
}
