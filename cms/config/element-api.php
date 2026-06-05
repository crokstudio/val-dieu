<?php

use craft\elements\Entry;

return [
    'endpoints' => [
        'api/homepage.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'homepage',
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry): array {
                return [
                    'introText' => (string)$entry->getFieldValue('introText'),
                ];
            },
        ],
        'api/visit.json' => [
            'elementType' => Entry::class,
            'criteria' => [
                'section' => 'visit',
            ],
            'one' => true,
            'cache' => false,
            'transformer' => static function(Entry $entry): array {
                return [
                    'pageTitle' => (string)$entry->getFieldValue('pageTitle'),
                ];
            },
        ],
    ],
];
