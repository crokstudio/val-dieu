<?php

declare(strict_types=1);

/** Validate before any mail is sent; all participants count towards the minimum. */
function guided_visit_validation_error(array $values, ?DateTimeImmutable $now = null): ?string
{
    $total = 0;
    foreach (['adult_count', 'teen_count', 'child_count'] as $key) {
        $value = $values[$key] ?? '';
        if (!is_string($value) || !preg_match('/^[0-9]+$/D', $value)) {
            return 'counts';
        }
        $normalized = ltrim($value, '0');
        $count = filter_var($normalized === '' ? '0' : $normalized, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($count === false) return 'counts';
        // Only the threshold matters; cap additions to avoid integer overflow.
        $total += min($count, 15);
    }
    if ($total < 15) return 'group';

    $zone = new DateTimeZone('Europe/Brussels');
    $today = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone)->setTime(0, 0);
    $rawDate = $values['visit_date'] ?? '';
    if (!is_string($rawDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $rawDate)) return 'date';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate, $zone);
    if (!$date || $date->format('Y-m-d') !== $rawDate || $date < $today->modify('+21 days')) return 'date';

    $time = $values['visit_time'] ?? '';
    if (!is_string($time) || !preg_match('/^(1[0-5]):(00|30)$/D', $time)) return 'time';
    return null;
}

function guided_visit_error_message(string $error, string $locale): string
{
    $messages = [
        'fr' => [
            'counts' => 'Merci de saisir un nombre entier positif ou nul pour chaque catégorie de participants.',
            'group' => 'Le groupe doit comprendre au moins 15 personnes, enfants de 0 à 4 ans compris.',
            'date' => 'Merci de choisir une date au moins 21 jours à l’avance.',
            'time' => 'Merci de choisir une heure entre 10 h et 15 h 30, par tranches de 30 minutes.',
        ],
        'en' => [
            'counts' => 'Enter a whole number of zero or more for each participant category.',
            'group' => 'The group must include at least 15 people, including children aged 0 to 4.',
            'date' => 'Choose a date at least 21 days in advance.',
            'time' => 'Choose a time between 10:00 and 15:30, in 30-minute intervals.',
        ],
        'nl' => [
            'counts' => 'Vul voor elke deelnemerscategorie een geheel getal van nul of meer in.',
            'group' => 'De groep moet uit minstens 15 personen bestaan, inclusief kinderen van 0 tot 4 jaar.',
            'date' => 'Kies een datum minstens 21 dagen van tevoren.',
            'time' => 'Kies een tijdstip tussen 10.00 en 15.30 uur, in stappen van 30 minuten.',
        ],
        'de' => [
            'counts' => 'Geben Sie für jede Teilnehmerkategorie eine ganze Zahl ab null ein.',
            'group' => 'Die Gruppe muss mindestens 15 Personen umfassen, einschließlich Kindern von 0 bis 4 Jahren.',
            'date' => 'Wählen Sie ein Datum mindestens 21 Tage im Voraus.',
            'time' => 'Wählen Sie eine Uhrzeit zwischen 10:00 und 15:30 Uhr in Abständen von 30 Minuten.',
        ],
    ];
    return ($messages[$locale] ?? $messages['fr'])[$error];
}
