<?php

declare(strict_types=1);
require __DIR__ . '/../src/api/guided-visits-validation.php';

$now = new DateTimeImmutable('2026-09-11 12:00:00', new DateTimeZone('Europe/Brussels'));
$base = ['adult_count' => '10', 'teen_count' => '3', 'child_count' => '2', 'visit_date' => '2026-10-02', 'visit_time' => '10:00'];
$cases = [
    ['15 participants including toddlers, J+21, 10:00', [], null],
    ['leading zeros', ['adult_count' => '010', 'teen_count' => '003', 'child_count' => '002'], null],
    ['14 participants', ['child_count' => '1'], 'group'],
    ['all age categories count', ['adult_count' => '0', 'teen_count' => '0', 'child_count' => '15'], null],
    ['negative participants', ['teen_count' => '-1'], 'counts'],
    ['fractional participants', ['teen_count' => '3.5'], 'counts'],
    ['exponent participants', ['adult_count' => '1e2'], 'counts'],
    ['missing participants', ['child_count' => ''], 'counts'],
    ['array participants', ['adult_count' => ['15']], 'counts'],
    ['overflow participants', ['adult_count' => str_repeat('9', 100)], 'counts'],
    ['J+20', ['visit_date' => '2026-10-01'], 'date'],
    ['same day', ['visit_date' => '2026-09-11'], 'date'],
    ['J+22', ['visit_date' => '2026-10-03'], null],
    ['impossible date', ['visit_date' => '2026-09-31'], 'date'],
    ['last slot 15:30', ['visit_time' => '15:30'], null],
    ['before opening', ['visit_time' => '09:30'], 'time'],
    ['after last slot', ['visit_time' => '16:00'], 'time'],
    ['quarter hour', ['visit_time' => '10:15'], 'time'],
    ['time with seconds', ['visit_time' => '10:00:00'], 'time'],
];
foreach ($cases as [$name, $changes, $expected]) {
    $actual = guided_visit_validation_error(array_replace($base, $changes), $now);
    if ($actual !== $expected) throw new RuntimeException("Failed: $name");
}
// Calendar days across DST, a year boundary, and Brussels midnight.
foreach ([['2026-03-20T23:30:00Z', '2026-04-11'], ['2026-10-15T12:00:00Z', '2026-11-05'], ['2026-12-20T12:00:00Z', '2027-01-10']] as [$instant, $minimum]) {
    $time = new DateTimeImmutable($instant);
    $values = array_replace($base, ['visit_date' => $minimum]);
    if (guided_visit_validation_error($values, $time) !== null) throw new RuntimeException('Calendar boundary rejected');
    $values['visit_date'] = (new DateTimeImmutable($minimum))->modify('-1 day')->format('Y-m-d');
    if (guided_visit_validation_error($values, $time) !== 'date') throw new RuntimeException('Too-early calendar boundary accepted');
}
echo count($cases) + 6, " validation cases passed.\n";
