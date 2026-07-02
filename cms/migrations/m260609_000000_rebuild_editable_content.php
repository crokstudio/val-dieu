<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\Field;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Matrix;
use craft\fields\Table;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\TitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use yii\base\Exception;

class m260609_000000_rebuild_editable_content extends Migration
{
    private const LEGACY_SECTIONS = ['homepage', 'visit', 'discover', 'community', 'support'];
    private const LEGACY_ENTRY_TYPES = ['homepage', 'visitpage', 'discover', 'community', 'support'];
    private const LEGACY_FIELDS = [
        'introText',
        'pageTitle',
        'homepageGallery',
        'discoverTimeline',
        'communityAgendaMonday',
        'communityAgendaTuesday',
        'communityAgendaWednesday',
        'communityAgendaThursday',
        'communityAgendaFriday',
        'communityAgendaSaturday',
        'communityAgendaSunday',
        'communityNews',
        'supportProjects',
    ];

    public function safeUp(): bool
    {
        if ($this->hasMatrixContentModel()) {
            echo "Existing Matrix content model detected; skipping destructive rebuild.\n";
            return true;
        }

        $this->deleteExistingContentModel();

        $homepageGallery = $this->createTableField(
            'Homepage gallery',
            'homepageGallery',
            'Les 5 photos de la galerie de la page index.njk.',
            $this->imageColumns(),
            [
                'minRows' => 5,
                'maxRows' => 5,
                'addRowLabel' => 'Ajouter une photo',
                'defaults' => $this->homepageGalleryDefaults(),
            ],
        );

        $discoverTimeline = $this->createTableField(
            'Discover timeline',
            'discoverTimeline',
            'Dates, images, titres et textes de la timeline de discover.njk.',
            $this->timelineColumns(),
            [
                'addRowLabel' => 'Ajouter une date',
                'defaults' => $this->discoverTimelineDefaults(),
            ],
        );

        $agendaFields = [
            'communityAgendaMonday' => $this->createAgendaField('Agenda - Lundi', 'communityAgendaMonday', $this->agendaMondayDefaults()),
            'communityAgendaTuesday' => $this->createAgendaField('Agenda - Mardi', 'communityAgendaTuesday', $this->agendaTuesdayDefaults()),
            'communityAgendaWednesday' => $this->createAgendaField('Agenda - Mercredi', 'communityAgendaWednesday', $this->agendaWednesdayDefaults()),
            'communityAgendaThursday' => $this->createAgendaField('Agenda - Jeudi', 'communityAgendaThursday', $this->agendaThursdayDefaults()),
            'communityAgendaFriday' => $this->createAgendaField('Agenda - Vendredi', 'communityAgendaFriday', $this->agendaFridayDefaults()),
            'communityAgendaSaturday' => $this->createAgendaField('Agenda - Samedi', 'communityAgendaSaturday', $this->agendaSaturdayDefaults()),
            'communityAgendaSunday' => $this->createAgendaField('Agenda - Dimanche et fêtes', 'communityAgendaSunday', $this->agendaSundayDefaults()),
        ];

        $communityNews = $this->createTableField(
            'Community news',
            'communityNews',
            'Actualités de community.njk : photo, titre, texte et date.',
            $this->newsColumns(),
            [
                'addRowLabel' => 'Ajouter une actualité',
                'defaults' => $this->communityNewsDefaults(),
            ],
        );

        $supportProjects = $this->createTableField(
            'Support projects',
            'supportProjects',
            'Projets de support.njk : titre, image et texte.',
            $this->projectColumns(),
            [
                'addRowLabel' => 'Ajouter un projet',
                'defaults' => $this->supportProjectsDefaults(),
            ],
        );

        $homepageSection = $this->createSingleSection(
            'Homepage',
            'homepage',
            $this->createEntryType('Homepage', 'homepage', [
                'Galerie' => [$homepageGallery],
            ]),
        );
        $this->createSingleEntry($homepageSection, 'Homepage', [
            'homepageGallery' => $this->homepageGalleryDefaults(),
        ]);

        $discoverSection = $this->createSingleSection(
            'Discover',
            'discover',
            $this->createEntryType('Discover', 'discover', [
                'Timeline' => [$discoverTimeline],
            ]),
        );
        $this->createSingleEntry($discoverSection, 'Discover', [
            'discoverTimeline' => $this->discoverTimelineDefaults(),
        ]);

        $communitySection = $this->createSingleSection(
            'Community',
            'community',
            $this->createEntryType('Community', 'community', [
                'Agenda' => array_values($agendaFields),
                'Actualités' => [$communityNews],
            ]),
        );
        $this->createSingleEntry($communitySection, 'Community', array_merge(
            [
                'communityNews' => $this->communityNewsDefaults(),
            ],
            $this->agendaFieldDefaults(),
        ));

        $supportSection = $this->createSingleSection(
            'Support',
            'support',
            $this->createEntryType('Support', 'support', [
                'Projets' => [$supportProjects],
            ]),
        );
        $this->createSingleEntry($supportSection, 'Support', [
            'supportProjects' => $this->supportProjectsDefaults(),
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260609_000000_rebuild_editable_content is destructive and cannot be reverted automatically.\n";
        return false;
    }

    private function hasMatrixContentModel(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        return $entries->getSectionByHandle('discover') !== null
            && $fields->getFieldByHandle('discoverTimeline') instanceof Matrix
            && $fields->getFieldByHandle('homepageGallery') instanceof Matrix
            && $fields->getFieldByHandle('communityNews') instanceof Matrix
            && $fields->getFieldByHandle('supportProjects') instanceof Matrix;
    }

    private function deleteExistingContentModel(): void
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        foreach (self::LEGACY_SECTIONS as $handle) {
            $section = $entries->getSectionByHandle($handle);
            if ($section) {
                $entries->deleteSection($section);
            }
        }

        foreach (self::LEGACY_ENTRY_TYPES as $handle) {
            $entryType = $entries->getEntryTypeByHandle($handle);
            if ($entryType) {
                $entries->deleteEntryType($entryType);
            }
        }

        foreach (self::LEGACY_FIELDS as $handle) {
            $field = $fields->getFieldByHandle($handle);
            if ($field) {
                $fields->deleteField($field);
            }
        }
    }

    private function createTableField(string $name, string $handle, string $instructions, array $columns, array $settings = []): Table
    {
        $field = new Table([
            'name' => $name,
            'handle' => $handle,
            'instructions' => $instructions,
            'translationMethod' => Field::TRANSLATION_METHOD_SITE,
            'columns' => $columns,
            'defaults' => $settings['defaults'] ?? [],
            'minRows' => $settings['minRows'] ?? null,
            'maxRows' => $settings['maxRows'] ?? null,
            'addRowLabel' => $settings['addRowLabel'] ?? null,
            'searchable' => true,
        ]);

        if (!Craft::$app->getFields()->saveField($field)) {
            throw new Exception("Could not create field $handle: " . json_encode($field->getErrors()));
        }

        return $field;
    }

    private function createAgendaField(string $name, string $handle, array $defaults): Table
    {
        return $this->createTableField(
            $name,
            $handle,
            "Créneaux horaires pour $name.",
            $this->agendaColumns(),
            [
                'addRowLabel' => 'Ajouter un créneau',
                'defaults' => $defaults,
            ],
        );
    }

    private function createEntryType(string $name, string $handle, array $tabs): EntryType
    {
        $entryType = new EntryType([
            'name' => $name,
            'handle' => $handle,
        ]);

        $fieldTabs = [];
        $isFirstTab = true;

        foreach ($tabs as $tabName => $tabFields) {
            $elements = [];

            if ($isFirstTab) {
                $elements[] = new TitleField([
                    'label' => 'Internal title',
                    'required' => true,
                ]);
                $isFirstTab = false;
            }

            foreach ($tabFields as $field) {
                $elements[] = new CustomField($field, [
                    'required' => false,
                ]);
            }

            $fieldTabs[] = [
                'name' => $tabName,
                'elements' => $elements,
            ];
        }

        $fieldLayout = new FieldLayout([
            'type' => Entry::class,
        ]);
        $fieldLayout->setTabs($fieldTabs);
        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new Exception("Could not create entry type $handle: " . json_encode($entryType->getErrors()));
        }

        return $entryType;
    }

    private function createSingleSection(string $name, string $handle, EntryType $entryType): Section
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $section = new Section([
            'name' => $name,
            'handle' => $handle,
            'type' => Section::TYPE_SINGLE,
            'enableVersioning' => true,
            'previewTargets' => [],
        ]);

        $section->setEntryTypes([$entryType]);
        $section->setSiteSettings([
            new Section_SiteSettings([
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'hasUrls' => false,
                'uriFormat' => null,
                'template' => null,
            ]),
        ]);

        if (!Craft::$app->getEntries()->saveSection($section)) {
            throw new Exception("Could not create section $handle: " . json_encode($section->getErrors()));
        }

        return $section;
    }

    private function createSingleEntry(Section $section, string $title, array $fieldValues): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $entryType = $section->getEntryTypes()[0] ?? null;

        if (!$entryType) {
            throw new Exception("Section {$section->handle} has no entry type.");
        }

        $entry = new Entry([
            'sectionId' => $section->id,
            'siteId' => $site->id,
        ]);
        $entry->setTypeId($entryType->id);
        $entry->title = $title;
        $entry->slug = $section->handle;
        $entry->enabled = true;

        foreach ($fieldValues as $handle => $value) {
            $entry->setFieldValue($handle, $value);
        }

        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new Exception("Could not create entry {$section->handle}: " . json_encode($entry->getErrors()));
        }
    }

    private function imageColumns(): array
    {
        return [
            'col1' => ['heading' => 'Image', 'handle' => 'image', 'type' => 'singleline'],
            'col2' => ['heading' => 'Alt', 'handle' => 'alt', 'type' => 'singleline'],
        ];
    }

    private function timelineColumns(): array
    {
        return [
            'col1' => ['heading' => 'Date', 'handle' => 'date', 'type' => 'singleline'],
            'col2' => ['heading' => 'Titre', 'handle' => 'title', 'type' => 'singleline'],
            'col3' => ['heading' => 'Texte', 'handle' => 'text', 'type' => 'multiline'],
            'col4' => ['heading' => 'Image', 'handle' => 'image', 'type' => 'singleline'],
            'col5' => ['heading' => 'Alt', 'handle' => 'alt', 'type' => 'singleline'],
        ];
    }

    private function agendaColumns(): array
    {
        return [
            'col1' => ['heading' => 'Horaire', 'handle' => 'time', 'type' => 'singleline'],
            'col2' => ['heading' => 'Texte', 'handle' => 'title', 'type' => 'singleline'],
        ];
    }

    private function newsColumns(): array
    {
        return [
            'col1' => ['heading' => 'Photo', 'handle' => 'image', 'type' => 'singleline'],
            'col2' => ['heading' => 'Alt', 'handle' => 'alt', 'type' => 'singleline'],
            'col3' => ['heading' => 'Titre', 'handle' => 'title', 'type' => 'singleline'],
            'col4' => ['heading' => 'Texte', 'handle' => 'text', 'type' => 'multiline'],
            'col5' => ['heading' => 'Date', 'handle' => 'date', 'type' => 'singleline'],
        ];
    }

    private function projectColumns(): array
    {
        return [
            'col1' => ['heading' => 'Image', 'handle' => 'image', 'type' => 'singleline'],
            'col2' => ['heading' => 'Alt', 'handle' => 'alt', 'type' => 'singleline'],
            'col3' => ['heading' => 'Titre', 'handle' => 'title', 'type' => 'singleline'],
            'col4' => ['heading' => 'Texte', 'handle' => 'text', 'type' => 'multiline'],
        ];
    }

    private function homepageGalleryDefaults(): array
    {
        return [
            ['image' => 'assets/medias/img/gallery-home_basilique.jpg', 'alt' => 'gallery image'],
            ['image' => 'assets/medias/img/gallery-home_parc.jpg', 'alt' => 'gallery image'],
            ['image' => 'assets/medias/img/gallery-home_cloche.jpg', 'alt' => 'gallery image'],
            ['image' => 'assets/medias/img/gallery-home_cassecroute.jpg', 'alt' => 'gallery image'],
            ['image' => 'assets/medias/img/gallery-home_brasserie.jpg', 'alt' => 'gallery image'],
        ];
    }

    private function discoverTimelineDefaults(): array
    {
        return [
            [
                'date' => '530',
                'title' => 'La règle de saint Benoît',
                'text' => 'Ora et Labora, prie et travaille. On résume ainsi la règle écrite par Benoît de Nursie vers 530 au mont Cassin. C’est la règle de vie monastique la plus suivie aujourd’hui.',
                'image' => 'assets/medias/img/timeline_530.jpg',
                'alt' => '',
            ],
            [
                'date' => '1098',
                'title' => 'Fondation de l’abbaye de Cîteaux (Bourgogne)',
                'text' => 'En 1098, le moine bénédictin Robert de Molesmes fonde un nouveau monastère en Bourgogne : l’abbaye de Cîteaux. Il souhaite réformer la vie monastique en encourageant un retour strict à la Règle de saint Benoît : pauvreté, simplicité, travail manuel.',
                'image' => 'assets/medias/img/timeline_1098.png',
                'alt' => '',
            ],
            [
                'date' => '1115',
                'title' => 'De Cîteaux au Val-Dieu',
                'text' => 'Au 12e siècle, le modèle de vie monacale des cisterciens connait une expansion fulgurante. Plus de 700 abbayes masculines sont fondées à travers l’Europe. Elles sont installées dans des vallées isolées, à proximité de l’eau. Leurs plans sont toujours similaires et rassemblent toutes les fonctions utiles aux moines : réfectoire, dortoir, jardin, caves, bibliothèque, zones d’artisanat et agricoles (forge, moulin, brasserie, etc.) <br> Ainsi, un groupe de moines guidés par Bernard fonde l’abbaye de Clairvaux en 1115. Bernard de Clairvaux aura un grand rayonnement à travers ses écrits et sa vie. Il participe à la fondation de 68 abbayes. Parmi elles, l’abbaye d’Eberbach près de Mayence en Allemagne. Vers 1180, Eberbach envoie un groupe de moines fonder l’abbaye de Hocht (Lanaken, nord de Maastricht). Ces moines cisterciens quittent quelques années plus tard Hocht pour s’installer définitivement au Val-Dieu.',
                'image' => 'assets/medias/img/timeline_1115.png',
                'alt' => '',
            ],
        ];
    }

    private function agendaFieldDefaults(): array
    {
        return [
            'communityAgendaMonday' => $this->agendaMondayDefaults(),
            'communityAgendaTuesday' => $this->agendaTuesdayDefaults(),
            'communityAgendaWednesday' => $this->agendaWednesdayDefaults(),
            'communityAgendaThursday' => $this->agendaThursdayDefaults(),
            'communityAgendaFriday' => $this->agendaFridayDefaults(),
            'communityAgendaSaturday' => $this->agendaSaturdayDefaults(),
            'communityAgendaSunday' => $this->agendaSundayDefaults(),
        ];
    }

    private function agendaMondayDefaults(): array
    {
        return [
            ['time' => '8h', 'title' => 'Laudes'],
            ['time' => '18h-18h40', 'title' => 'Vêpres + Eucharistie'],
            ['time' => '18h40-19h15', 'title' => 'Lectio Divina'],
        ];
    }

    private function agendaTuesdayDefaults(): array
    {
        return [
            ['time' => '8h', 'title' => 'Laudes'],
            ['time' => '17h30-18h', 'title' => 'Adoration'],
            ['time' => '18h-18h40', 'title' => 'Vêpres + Eucharistie'],
        ];
    }

    private function agendaWednesdayDefaults(): array
    {
        return [
            ['time' => '8h', 'title' => 'Laudes'],
            ['time' => '18h-18h40', 'title' => 'Vêpres + Eucharistie'],
        ];
    }

    private function agendaThursdayDefaults(): array
    {
        return [
            ['time' => '8h', 'title' => 'Laudes'],
            ['time' => '18h-18h40', 'title' => 'Vêpres + Eucharistie'],
            ['time' => '18h40-19h15', 'title' => 'Lectio Divina'],
            ['time' => '20h30', 'title' => 'Assemblée de prière'],
        ];
    }

    private function agendaFridayDefaults(): array
    {
        return [
            ['time' => '8h', 'title' => 'Laudes'],
            ['time' => '17h30-18h', 'title' => 'Adoration'],
            ['time' => '18h-18h40', 'title' => 'Vêpres + Eucharistie'],
        ];
    }

    private function agendaSaturdayDefaults(): array
    {
        return [
            ['time' => '8h', 'title' => 'Laudes'],
            ['time' => '18h-18h40', 'title' => 'Vêpres'],
        ];
    }

    private function agendaSundayDefaults(): array
    {
        return [
            ['time' => '8h30', 'title' => 'Laudes'],
            ['time' => '11h-12h', 'title' => 'Eucharistie'],
            ['time' => '16h-16h15', 'title' => 'Bénédiction'],
            ['time' => '16h15-16h40', 'title' => 'Vêpres'],
        ];
    }

    private function communityNewsDefaults(): array
    {
        return [
            [
                'image' => '/assets/medias/img/gallery-home_basilique.jpg',
                'alt' => '',
                'title' => 'La billeterie pour les concerts de printemps est ouverte!',
                'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
                'date' => '18/03/2026',
            ],
            [
                'image' => '/assets/medias/img/gallery-home_basilique.jpg',
                'alt' => '',
                'title' => 'La billeterie pour les concerts de printemps est ouverte!',
                'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
                'date' => '18/03/2026',
            ],
            [
                'image' => '/assets/medias/img/gallery-home_basilique.jpg',
                'alt' => '',
                'title' => 'La billeterie pour les concerts de printemps est ouverte!',
                'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
                'date' => '18/03/2026',
            ],
        ];
    }

    private function supportProjectsDefaults(): array
    {
        return [
            [
                'image' => '/assets/medias/img/gallery-home_basilique.jpg',
                'alt' => '',
                'title' => 'Projet en cours',
                'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
            ],
            [
                'image' => '/assets/medias/img/gallery-home_basilique.jpg',
                'alt' => '',
                'title' => 'Projet en cours',
                'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
            ],
            [
                'image' => '/assets/medias/img/gallery-home_basilique.jpg',
                'alt' => '',
                'title' => 'Projet en cours',
                'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
            ],
        ];
    }
}
