<?php

use craft\elements\db\EntryQuery;
use craft\elements\Entry;

$tableRows = static function(Entry $entry, string $handle, array $keys): array {
    $rows = $entry->getFieldValue($handle) ?: [];
    $normalizedRows = [];

    foreach ($rows as $row) {
        $normalizedRow = [];
        $hasContent = false;

        foreach ($keys as $key) {
            $value = trim((string)($row[$key] ?? ''));
            $normalizedRow[$key] = $value;
            $hasContent = $hasContent || $value !== '';
        }

        if ($hasContent) {
            $normalizedRows[] = $normalizedRow;
        }
    }

    return $normalizedRows;
};

$agendaRows = static function(Entry $entry, string $handle) use ($tableRows): array {
    return $tableRows($entry, $handle, ['time', 'title']);
};

$matrixEntries = static function(Entry $entry, string $handle): array {
    $value = $entry->getFieldValue($handle);

    if ($value instanceof EntryQuery) {
        return $value->status(null)->all();
    }

    if (is_object($value) && method_exists($value, 'all')) {
        return $value->all();
    }

    return [];
};

$matrixRows = static function(Entry $entry, string $handle, array $keys) use ($matrixEntries): array {
    $rows = [];

    foreach ($matrixEntries($entry, $handle) as $block) {
        $asset = null;
        $imageField = $block->getFieldValue('contentImage');

        if ($imageField && method_exists($imageField, 'one')) {
            $asset = $imageField->one();
        }

        $row = [];
        $hasContent = false;

        foreach ($keys as $key) {
            $value = match ($key) {
                'image' => $asset?->getUrl() ?? '',
                'alt' => trim((string)$block->getFieldValue('contentAlt')) ?: (string)($asset?->alt ?? ''),
                'date' => trim((string)$block->getFieldValue('contentDate')),
                'title' => trim((string)$block->getFieldValue('contentItemTitle')),
                'text' => trim((string)$block->getFieldValue('contentItemText')),
                default => '',
            };

            $row[$key] = $value;
            $hasContent = $hasContent || $value !== '';
        }

        if ($hasContent) {
            $rows[] = $row;
        }
    }

    return $rows;
};

return [
    'endpoints' => [
        'api/homepage.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'homepage',
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($matrixRows): array {
                return [
                    'gallery' => $matrixRows($entry, 'homepageGallery', ['image', 'alt']),
                ];
            },
        ],
        'api/discover.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'discover',
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($matrixRows): array {
                return [
                    'timeline' => $matrixRows($entry, 'discoverTimeline', ['date', 'title', 'text', 'image', 'alt']),
                ];
            },
        ],
        'api/community.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'community',
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($agendaRows, $matrixRows): array {
                return [
                    'agenda' => [
                        [
                            'day' => 'Lundi',
                            'subtitle' => '',
                            'slots' => $agendaRows($entry, 'communityAgendaMonday'),
                        ],
                        [
                            'day' => 'Mardi',
                            'subtitle' => '',
                            'slots' => $agendaRows($entry, 'communityAgendaTuesday'),
                        ],
                        [
                            'day' => 'Mercredi',
                            'subtitle' => '',
                            'slots' => $agendaRows($entry, 'communityAgendaWednesday'),
                        ],
                        [
                            'day' => 'Jeudi',
                            'subtitle' => '',
                            'slots' => $agendaRows($entry, 'communityAgendaThursday'),
                        ],
                        [
                            'day' => 'Vendredi',
                            'subtitle' => '',
                            'slots' => $agendaRows($entry, 'communityAgendaFriday'),
                        ],
                        [
                            'day' => 'Samedi',
                            'subtitle' => '',
                            'slots' => $agendaRows($entry, 'communityAgendaSaturday'),
                        ],
                        [
                            'day' => 'Dimanche',
                            'subtitle' => 'et fêtes',
                            'slots' => $agendaRows($entry, 'communityAgendaSunday'),
                        ],
                    ],
                    'news' => $matrixRows($entry, 'communityNews', ['image', 'alt', 'title', 'text', 'date']),
                ];
            },
        ],
        'api/support.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'support',
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($matrixRows): array {
                return [
                    'projects' => $matrixRows($entry, 'supportProjects', ['image', 'alt', 'title', 'text']),
                ];
            },
        ],
    ],
];
