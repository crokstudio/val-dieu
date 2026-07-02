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

$matrixRowsForSection = static function(string $sectionHandle, string $handle, array $keys) use ($matrixRows): array {
    $entries = Entry::find()
        ->section($sectionHandle)
        ->status(null)
        ->orderBy(['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC])
        ->all();

    foreach ($entries as $entry) {
        $rows = $matrixRows($entry, $handle, $keys);

        if ($rows) {
            return $rows;
        }
    }

    return [];
};

$tableRowsForSection = static function(string $sectionHandle, string $handle, array $keys) use ($tableRows): array {
    $entries = Entry::find()
        ->section($sectionHandle)
        ->status(null)
        ->orderBy(['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC])
        ->all();

    foreach ($entries as $entry) {
        $rows = $tableRows($entry, $handle, $keys);

        if ($rows) {
            return $rows;
        }
    }

    return [];
};

return [
    'endpoints' => [
        'api/homepage.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'homepage',
                'orderBy' => ['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC],
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($matrixRowsForSection): array {
                return [
                    'gallery' => $matrixRowsForSection('homepage', 'homepageGallery', ['image', 'alt']),
                ];
            },
        ],
        'api/discover.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'discover',
                'orderBy' => ['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC],
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($matrixRowsForSection): array {
                return [
                    'timeline' => $matrixRowsForSection('discover', 'discoverTimeline', ['date', 'title', 'text', 'image', 'alt']),
                ];
            },
        ],
        'api/community.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'community',
                'orderBy' => ['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC],
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($matrixRowsForSection, $tableRowsForSection): array {
                return [
                    'agenda' => [
                        [
                            'day' => 'Lundi',
                            'subtitle' => '',
                            'slots' => $tableRowsForSection('community', 'communityAgendaMonday', ['time', 'title']),
                        ],
                        [
                            'day' => 'Mardi',
                            'subtitle' => '',
                            'slots' => $tableRowsForSection('community', 'communityAgendaTuesday', ['time', 'title']),
                        ],
                        [
                            'day' => 'Mercredi',
                            'subtitle' => '',
                            'slots' => $tableRowsForSection('community', 'communityAgendaWednesday', ['time', 'title']),
                        ],
                        [
                            'day' => 'Jeudi',
                            'subtitle' => '',
                            'slots' => $tableRowsForSection('community', 'communityAgendaThursday', ['time', 'title']),
                        ],
                        [
                            'day' => 'Vendredi',
                            'subtitle' => '',
                            'slots' => $tableRowsForSection('community', 'communityAgendaFriday', ['time', 'title']),
                        ],
                        [
                            'day' => 'Samedi',
                            'subtitle' => '',
                            'slots' => $tableRowsForSection('community', 'communityAgendaSaturday', ['time', 'title']),
                        ],
                        [
                            'day' => 'Dimanche',
                            'subtitle' => 'et fêtes',
                            'slots' => $tableRowsForSection('community', 'communityAgendaSunday', ['time', 'title']),
                        ],
                    ],
                    'news' => $matrixRowsForSection('community', 'communityNews', ['image', 'alt', 'title', 'text', 'date']),
                ];
            },
        ],
        'api/support.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'support',
                'orderBy' => ['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC],
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry) use ($matrixRowsForSection): array {
                return [
                    'projects' => $matrixRowsForSection('support', 'supportProjects', ['image', 'alt', 'title', 'text']),
                ];
            },
        ],
    ],
];
