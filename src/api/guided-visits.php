<?php

declare(strict_types=1);

const FORM_TO = 'elann.fraiture@gmail.com';
const FORM_FROM = 'infotourist@val-dieu.net';
const SITE_NAME = 'Val-Dieu';

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

function send_email(string $to, string $subject, string $body, string $replyTo = FORM_FROM): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . SITE_NAME . ' <' . FORM_FROM . '>',
        'Reply-To: ' . clean_header($replyTo),
        'X-Mailer: PHP/' . phpversion(),
    ];

    return mail($to, clean_header($subject), $body, implode("\r\n", $headers), '-f ' . FORM_FROM);
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

if (!send_email(FORM_TO, $subject, $adminBody, $fields['Email'])) {
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
