<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

const SITE_NAME = 'Val-Dieu';
const LEGACY_FORM_TO = 'elann.fraiture@gmail.com';
const FORM_FROM = 'infotourist@val-dieu.net';

function post_value(string $key): string
{
    $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);

    if ($value === null || $value === false) {
        return '';
    }

    $value = trim((string) $value);
    $value = str_replace(["\r", "\0"], '', $value);

    return mb_substr($value, 0, 2000);
}

function clean_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function render_response(string $title, string $message, int $status = 200): never
{
    http_response_code($status);

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $backUrl = $_SERVER['HTTP_REFERER'] ?? '/guided-visits/';
    $safeBackUrl = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} - Val-Dieu</title>
</head>
<body>
    <main>
        <h1>{$safeTitle}</h1>
        <p>{$safeMessage}</p>
        <p><a href="{$safeBackUrl}">Retour au formulaire</a></p>
    </main>
</body>
</html>
HTML;

    exit;
}

/**
 * Load SMTP secrets from the environment or from a PHP file outside the web
 * root. Environment variables take precedence over file values.
 *
 * @return array{mode:string,host:string,port:int,encryption:string,username:string,password:string,from_email:string,from_name:string,to_email:string}
 */
function mail_configuration(): array
{
    static $configuration = null;

    if (is_array($configuration)) {
        return $configuration;
    }

    $configPath = getenv('VALDIEU_MAIL_CONFIG');
    if (!is_string($configPath) || $configPath === '') {
        $configPath = dirname(__DIR__, 2) . '/.val-dieu-mail.php';
    }

    $fileValues = [];
    if (is_file($configPath) && is_readable($configPath)) {
        $loadedValues = require $configPath;
        if (is_array($loadedValues)) {
            $fileValues = $loadedValues;
        }
    }

    $value = static function (string $key, string $default = '') use ($fileValues): string {
        $environmentValue = getenv($key);
        if (is_string($environmentValue) && $environmentValue !== '') {
            return trim($environmentValue);
        }

        $fileValue = $fileValues[$key] ?? $default;
        return is_scalar($fileValue) ? trim((string) $fileValue) : $default;
    };

    $configuration = [
        'mode' => 'mail',
        'host' => $value('VALDIEU_SMTP_HOST'),
        'port' => (int) $value('VALDIEU_SMTP_PORT', '587'),
        'encryption' => strtolower($value('VALDIEU_SMTP_ENCRYPTION', 'tls')),
        'username' => $value('VALDIEU_SMTP_USERNAME'),
        'password' => $value('VALDIEU_SMTP_PASSWORD'),
        'from_email' => $value('VALDIEU_SMTP_FROM_EMAIL', FORM_FROM),
        'from_name' => $value('VALDIEU_SMTP_FROM_NAME', SITE_NAME),
        'to_email' => $value('VALDIEU_FORM_TO_EMAIL', LEGACY_FORM_TO),
    ];

    $smtpKeys = ['host', 'username', 'password'];
    $configuredSmtpKeys = array_filter(
        $smtpKeys,
        static fn(string $key): bool => $configuration[$key] !== '',
    );
    if ($configuredSmtpKeys !== []) {
        foreach ($smtpKeys as $smtpKey) {
            if ($configuration[$smtpKey] === '') {
                throw new RuntimeException("Missing mail configuration: {$smtpKey}");
            }
        }
        $configuration['mode'] = 'smtp';
    }

    if ($configuration['mode'] === 'smtp') {
        if ($configuration['port'] < 1 || $configuration['port'] > 65535) {
            throw new RuntimeException('Invalid SMTP port.');
        }

        if (!in_array($configuration['encryption'], ['tls', 'smtps', 'none'], true)) {
            throw new RuntimeException('Invalid SMTP encryption mode.');
        }
    }

    if (
        !filter_var($configuration['from_email'], FILTER_VALIDATE_EMAIL) ||
        !filter_var($configuration['to_email'], FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException('Invalid configured mail address.');
    }

    return $configuration;
}

function send_email(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    try {
        $configuration = mail_configuration();

        if ($configuration['mode'] === 'mail') {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . clean_header($configuration['from_name']) . ' <' . $configuration['from_email'] . '>',
                'Reply-To: ' . clean_header($replyTo ?? $configuration['from_email']),
                'X-Mailer: PHP/' . phpversion(),
            ];

            return mail(
                clean_header($to),
                clean_header($subject),
                $body,
                implode("\r\n", $headers),
                '-f ' . clean_header($configuration['from_email']),
            );
        }

        $autoloadPath = __DIR__ . '/vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            throw new RuntimeException('PHPMailer dependency is unavailable.');
        }
        require_once $autoloadPath;

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $configuration['host'];
        $mailer->Port = $configuration['port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $configuration['username'];
        $mailer->Password = $configuration['password'];
        $mailer->Timeout = 15;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;

        if ($configuration['encryption'] === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($configuration['encryption'] === 'smtps') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mailer->SMTPAutoTLS = false;
            $mailer->SMTPSecure = '';
        }

        $mailer->setFrom($configuration['from_email'], $configuration['from_name']);
        $mailer->addAddress(clean_header($to));
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mailer->addReplyTo(clean_header($replyTo));
        }
        $mailer->Subject = clean_header($subject);
        $mailer->Body = $body;

        return $mailer->send();
    } catch (Throwable $exception) {
        error_log('Val-Dieu form email failed: ' . $exception->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_response('Formulaire indisponible', 'Cette page accepte uniquement les envois de formulaire.', 405);
}

if (post_value('website') !== '') {
    render_response('Merci', 'Votre demande a bien ete envoyee.');
}

$fields = [
    'Date souhaitee' => post_value('visit_date'),
    'Heure souhaitee' => post_value('visit_time'),
    'Nom du groupe' => post_value('group_name'),
    'Nombre adultes' => post_value('adult_count'),
    'Nombre jeunes 12-17 ans' => post_value('teen_count'),
    'Nombre enfants moins de 12 ans' => post_value('child_count'),
    'Type de visite' => post_value('visit_type'),
    'Email' => post_value('email'),
    'Telephone' => post_value('phone'),
    'Message complementaire' => post_value('message'),
    'Langue du site' => post_value('locale'),
];

$required = [
    'Date souhaitee',
    'Heure souhaitee',
    'Nom du groupe',
    'Nombre adultes',
    'Nombre jeunes 12-17 ans',
    'Nombre enfants moins de 12 ans',
    'Type de visite',
    'Email',
    'Telephone',
];

foreach ($required as $label) {
    if ($fields[$label] === '') {
        render_response('Champ manquant', 'Merci de completer tous les champs obligatoires.', 400);
    }
}

if (!filter_var($fields['Email'], FILTER_VALIDATE_EMAIL)) {
    render_response('Email invalide', "Merci d'indiquer une adresse email valide.", 400);
}

$subject = '[Val-Dieu] Demande de reservation groupe';
$lines = [
    'Nouvelle demande de reservation pour une visite guidee.',
    '',
];

foreach ($fields as $label => $value) {
    $lines[] = $label . ' : ' . ($value !== '' ? $value : '-');
}

$lines[] = '';
$lines[] = 'Envoye depuis : ' . ($_SERVER['HTTP_REFERER'] ?? 'inconnu');
$adminBody = implode("\n", $lines);

try {
    $mailConfiguration = mail_configuration();
} catch (Throwable $exception) {
    error_log('Val-Dieu form configuration failed: ' . $exception->getMessage());
    render_response('Formulaire indisponible', 'Le formulaire est temporairement indisponible. Merci de nous contacter directement.', 503);
}

if (!send_email($mailConfiguration['to_email'], $subject, $adminBody, $fields['Email'])) {
    render_response('Erreur d\'envoi', "Votre demande n'a pas pu etre envoyee. Merci de reessayer ou de nous contacter directement.", 500);
}

$confirmationBody = <<<TEXT
Bonjour,

Nous avons bien recu votre demande de reservation pour une visite guidee de Val-Dieu.

Notre equipe reviendra vers vous pour confirmer la disponibilite et finaliser la reservation.

Recapitulatif :
Date souhaitee : {$fields['Date souhaitee']}
Heure souhaitee : {$fields['Heure souhaitee']}
Nom du groupe : {$fields['Nom du groupe']}
Type de visite : {$fields['Type de visite']}

Val-Dieu
TEXT;

send_email($fields['Email'], 'Votre demande de reservation - Val-Dieu', $confirmationBody);

render_response('Demande envoyee', 'Merci, votre demande a bien ete envoyee. Vous allez recevoir un email de confirmation automatique.');
