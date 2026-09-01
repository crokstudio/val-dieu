<?php

declare(strict_types=1);

// Copy this file to $HOME/.val-dieu-mail.php on the OVH account, set mode 600,
// and replace every placeholder. Never commit the production copy.
return [
    'VALDIEU_SMTP_HOST' => 'smtp.example.com',
    'VALDIEU_SMTP_PORT' => 587,
    'VALDIEU_SMTP_ENCRYPTION' => 'tls',
    'VALDIEU_SMTP_USERNAME' => 'smtp-user@example.com',
    'VALDIEU_SMTP_PASSWORD' => 'replace-with-a-secret',
    'VALDIEU_SMTP_FROM_EMAIL' => 'infotourist@val-dieu.net',
    'VALDIEU_SMTP_FROM_NAME' => 'Val-Dieu',
    'VALDIEU_FORM_TO_EMAIL' => 'infotourist@val-dieu.net',
];
