<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\Field;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\TitleField;
use craft\fs\Local;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Volume;
use yii\base\Exception;

class m260609_010000_use_matrix_assets extends Migration
{
    private const MATRIX_FIELD_HANDLES = [
        'homepageGallery',
        'discoverTimeline',
        'communityNews',
        'supportProjects',
    ];

    private const CONTENT_FIELD_HANDLES = [
        'contentImage',
        'contentAlt',
        'contentDate',
        'contentItemTitle',
        'contentItemText',
    ];

    private const MATRIX_ENTRY_TYPE_HANDLES = [
        'homepageGalleryItem',
        'discoverTimelineItem',
        'communityNewsItem',
        'supportProjectItem',
    ];

    public function safeUp(): bool
    {
        $existing = $this->captureExistingContent();
        $this->removeDuplicateSingleEntries($existing);

        $volume = $this->ensureContentImagesVolume();
        $fields = $this->ensureContentFields($volume);
        $entryTypes = $this->ensureMatrixEntryTypes($fields);

        $this->replaceMatrixFields($entryTypes);
        $this->updateMainEntryLayouts();
        $this->seedMatrixContent($existing, $volume);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260609_010000_use_matrix_assets is non-destructive only in the forward direction; revert manually if needed.\n";
        return false;
    }

    private function captureExistingContent(): array
    {
        return [
            'homepageGallery' => $this->tableRows('homepage', 'homepageGallery', $this->homepageGalleryDefaults()),
            'discoverTimeline' => $this->tableRows('discover', 'discoverTimeline', $this->discoverTimelineDefaults()),
            'communityNews' => $this->tableRows('community', 'communityNews', $this->communityNewsDefaults()),
            'supportProjects' => $this->tableRows('support', 'supportProjects', $this->supportProjectsDefaults()),
        ];
    }

    private function tableRows(string $sectionHandle, string $fieldHandle, array $fallback): array
    {
        $entries = Entry::find()
            ->section($sectionHandle)
            ->status(null)
            ->orderBy(['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC])
            ->all();

        foreach ($entries as $entry) {
            try {
                $rows = $entry->getFieldValue($fieldHandle);
            } catch (\Throwable) {
                $rows = null;
            }

            if (!empty($rows) && is_iterable($rows)) {
                $normalized = [];
                foreach ($rows as $row) {
                    $normalized[] = is_array($row) ? $row : [];
                }

                if ($normalized) {
                    return $normalized;
                }
            }
        }

        return $fallback;
    }

    private function removeDuplicateSingleEntries(array $existing): void
    {
        $elements = Craft::$app->getElements();

        foreach (['homepage', 'discover', 'community', 'support'] as $sectionHandle) {
            $entries = Entry::find()
                ->section($sectionHandle)
                ->status(null)
                ->orderBy(['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC])
                ->all();

            if (!$entries) {
                continue;
            }

            $keeper = array_shift($entries);
            foreach ($entries as $duplicate) {
                $elements->deleteElement($duplicate, true);
            }

            $keeper->title = $keeper->title ?: ucfirst($sectionHandle);
            $keeper->slug = $sectionHandle;
            $keeper->enabled = true;

            foreach ($this->fieldValuesForSection($sectionHandle, $existing) as $fieldHandle => $value) {
                if (Craft::$app->getFields()->getFieldByHandle($fieldHandle)) {
                    $keeper->setFieldValue($fieldHandle, $value);
                }
            }

            if (!$elements->saveElement($keeper)) {
                throw new Exception("Could not normalize $sectionHandle entry: " . json_encode($keeper->getErrors()));
            }
        }
    }

    private function fieldValuesForSection(string $sectionHandle, array $existing): array
    {
        return match ($sectionHandle) {
            'homepage' => ['homepageGallery' => $existing['homepageGallery']],
            'discover' => ['discoverTimeline' => $existing['discoverTimeline']],
            'community' => ['communityNews' => $existing['communityNews']],
            'support' => ['supportProjects' => $existing['supportProjects']],
            default => [],
        };
    }

    private function ensureContentImagesVolume(): Volume
    {
        $fs = Craft::$app->getFs()->getFilesystemByHandle('contentImages');

        if (!$fs) {
            $fs = new Local([
                'name' => 'Content images',
                'handle' => 'contentImages',
                'hasUrls' => true,
                'url' => '@web/uploads',
                'path' => '@webroot/uploads',
            ]);

            if (!Craft::$app->getFs()->saveFilesystem($fs)) {
                throw new Exception('Could not create contentImages filesystem: ' . json_encode($fs->getErrors()));
            }
        }

        $volume = Craft::$app->getVolumes()->getVolumeByHandle('contentImages');

        if (!$volume) {
            $volume = new Volume([
                'name' => 'Content images',
                'handle' => 'contentImages',
                'fs' => 'contentImages',
                'titleTranslationMethod' => Field::TRANSLATION_METHOD_SITE,
                'altTranslationMethod' => Field::TRANSLATION_METHOD_SITE,
            ]);

            if (!Craft::$app->getVolumes()->saveVolume($volume)) {
                throw new Exception('Could not create contentImages volume: ' . json_encode($volume->getErrors()));
            }

            $volume = Craft::$app->getVolumes()->getVolumeByHandle('contentImages');
        }

        if (!$volume) {
            throw new Exception('contentImages volume is unavailable after creation.');
        }

        Craft::$app->getAssets()->ensureFolderByFullPathAndVolume('', $volume, false);

        return $volume;
    }

    private function ensureContentFields(Volume $volume): array
    {
        $fields = Craft::$app->getFields();
        $volumeSource = "volume:$volume->uid";

        $image = $fields->getFieldByHandle('contentImage');
        if (!$image) {
            $image = new Assets([
                'name' => 'Image',
                'handle' => 'contentImage',
                'instructions' => 'Image uploadée depuis Craft.',
                'translationMethod' => Field::TRANSLATION_METHOD_SITE,
                'sources' => [$volumeSource],
                'defaultUploadLocationSource' => $volumeSource,
                'defaultUploadLocationSubpath' => '',
                'restrictFiles' => true,
                'allowedKinds' => ['image'],
                'maxRelations' => 1,
                'selectionLabel' => 'Ajouter une image',
                'viewMode' => 'cards',
                'showSearchInput' => true,
            ]);

            if (!$fields->saveField($image)) {
                throw new Exception('Could not create contentImage field: ' . json_encode($image->getErrors()));
            }
        }

        return [
            'image' => $image,
            'alt' => $this->ensurePlainTextField('Alt', 'contentAlt', false),
            'date' => $this->ensurePlainTextField('Date', 'contentDate', false),
            'title' => $this->ensurePlainTextField('Titre', 'contentItemTitle', false),
            'text' => $this->ensurePlainTextField('Texte', 'contentItemText', true),
        ];
    }

    private function ensurePlainTextField(string $name, string $handle, bool $multiline): PlainText
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle($handle);

        if ($field) {
            return $field;
        }

        $field = new PlainText([
            'name' => $name,
            'handle' => $handle,
            'translationMethod' => Field::TRANSLATION_METHOD_SITE,
            'multiline' => $multiline,
            'initialRows' => $multiline ? 5 : 1,
            'searchable' => true,
        ]);

        if (!$fields->saveField($field)) {
            throw new Exception("Could not create $handle field: " . json_encode($field->getErrors()));
        }

        return $field;
    }

    private function ensureMatrixEntryTypes(array $fields): array
    {
        return [
            'homepageGalleryItem' => $this->ensureMatrixEntryType('Photo galerie', 'homepageGalleryItem', [
                $fields['image'],
                $fields['alt'],
            ]),
            'discoverTimelineItem' => $this->ensureMatrixEntryType('Date timeline', 'discoverTimelineItem', [
                $fields['date'],
                $fields['title'],
                $fields['text'],
                $fields['image'],
                $fields['alt'],
            ]),
            'communityNewsItem' => $this->ensureMatrixEntryType('Actualité', 'communityNewsItem', [
                $fields['image'],
                $fields['alt'],
                $fields['title'],
                $fields['text'],
                $fields['date'],
            ]),
            'supportProjectItem' => $this->ensureMatrixEntryType('Projet support', 'supportProjectItem', [
                $fields['image'],
                $fields['alt'],
                $fields['title'],
                $fields['text'],
            ]),
        ];
    }

    private function ensureMatrixEntryType(string $name, string $handle, array $fields): EntryType
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle($handle) ?? new EntryType([
            'name' => $name,
            'handle' => $handle,
            'hasTitleField' => false,
            'titleFormat' => $name,
            'showSlugField' => false,
            'showStatusField' => true,
        ]);

        $entryType->name = $name;
        $entryType->handle = $handle;
        $entryType->hasTitleField = false;
        $entryType->titleFormat = $name;
        $entryType->showSlugField = false;

        $entryType->setFieldLayout($this->fieldLayout($fields));

        if (!$entries->saveEntryType($entryType)) {
            throw new Exception("Could not create matrix entry type $handle: " . json_encode($entryType->getErrors()));
        }

        return $entryType;
    }

    private function replaceMatrixFields(array $entryTypes): void
    {
        $fields = Craft::$app->getFields();

        foreach (self::MATRIX_FIELD_HANDLES as $handle) {
            $field = $fields->getFieldByHandle($handle);
            if ($field) {
                $fields->deleteField($field);
            }
        }

        $this->createMatrixField('Homepage gallery', 'homepageGallery', 'Les 5 photos de la galerie de la page index.njk.', [$entryTypes['homepageGalleryItem']], 5, 5, 'Ajouter une photo');
        $this->createMatrixField('Discover timeline', 'discoverTimeline', 'Dates, images, titres et textes de la timeline de discover.njk.', [$entryTypes['discoverTimelineItem']], null, null, 'Ajouter une date');
        $this->createMatrixField('Community news', 'communityNews', 'Actualités de community.njk : photo, titre, texte et date.', [$entryTypes['communityNewsItem']], null, null, 'Ajouter une actualité');
        $this->createMatrixField('Support projects', 'supportProjects', 'Projets de support.njk : titre, image et texte.', [$entryTypes['supportProjectItem']], null, null, 'Ajouter un projet');
    }

    private function createMatrixField(string $name, string $handle, string $instructions, array $entryTypes, ?int $minEntries, ?int $maxEntries, string $buttonLabel): Matrix
    {
        $field = new Matrix([
            'name' => $name,
            'handle' => $handle,
            'instructions' => $instructions,
            'translationMethod' => Field::TRANSLATION_METHOD_SITE,
            'minEntries' => $minEntries,
            'maxEntries' => $maxEntries,
            'viewMode' => Matrix::VIEW_MODE_CARDS,
            'createButtonLabel' => $buttonLabel,
            'enableVersioning' => true,
        ]);
        $field->setEntryTypes($entryTypes);

        if (!Craft::$app->getFields()->saveField($field)) {
            throw new Exception("Could not create matrix field $handle: " . json_encode($field->getErrors()));
        }

        return $field;
    }

    private function updateMainEntryLayouts(): void
    {
        $fields = Craft::$app->getFields();
        $agendaFields = [
            $fields->getFieldByHandle('communityAgendaMonday'),
            $fields->getFieldByHandle('communityAgendaTuesday'),
            $fields->getFieldByHandle('communityAgendaWednesday'),
            $fields->getFieldByHandle('communityAgendaThursday'),
            $fields->getFieldByHandle('communityAgendaFriday'),
            $fields->getFieldByHandle('communityAgendaSaturday'),
            $fields->getFieldByHandle('communityAgendaSunday'),
        ];

        $this->updateEntryTypeLayout('homepage', [
            'Galerie' => [$fields->getFieldByHandle('homepageGallery')],
        ]);
        $this->updateEntryTypeLayout('discover', [
            'Timeline' => [$fields->getFieldByHandle('discoverTimeline')],
        ]);
        $this->updateEntryTypeLayout('community', [
            'Agenda' => array_filter($agendaFields),
            'Actualités' => [$fields->getFieldByHandle('communityNews')],
        ]);
        $this->updateEntryTypeLayout('support', [
            'Projets' => [$fields->getFieldByHandle('supportProjects')],
        ]);
    }

    private function updateEntryTypeLayout(string $handle, array $tabs): void
    {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$entryType) {
            throw new Exception("Missing entry type $handle.");
        }

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

            foreach (array_filter($tabFields) as $field) {
                $elements[] = new CustomField($field, [
                    'required' => false,
                ]);
            }

            $fieldTabs[] = [
                'name' => $tabName,
                'elements' => $elements,
            ];
        }

        $entryType->setFieldLayout(new FieldLayout([
            'type' => Entry::class,
            'tabs' => $fieldTabs,
        ]));

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new Exception("Could not update entry type $handle: " . json_encode($entryType->getErrors()));
        }
    }

    private function fieldLayout(array $fields): FieldLayout
    {
        return new FieldLayout([
            'type' => Entry::class,
            'tabs' => [
                [
                    'name' => 'Contenu',
                    'elements' => array_map(
                        fn($field) => new CustomField($field, ['required' => false]),
                        $fields,
                    ),
                ],
            ],
        ]);
    }

    private function seedMatrixContent(array $existing, Volume $volume): void
    {
        $this->seedSingle('homepage', [
            'homepageGallery' => $this->matrixRows('homepageGalleryItem', $existing['homepageGallery'], $volume, ['image', 'alt']),
        ]);
        $this->seedSingle('discover', [
            'discoverTimeline' => $this->matrixRows('discoverTimelineItem', $existing['discoverTimeline'], $volume, ['date', 'title', 'text', 'image', 'alt']),
        ]);
        $this->seedSingle('community', [
            'communityNews' => $this->matrixRows('communityNewsItem', $existing['communityNews'], $volume, ['image', 'alt', 'title', 'text', 'date']),
        ]);
        $this->seedSingle('support', [
            'supportProjects' => $this->matrixRows('supportProjectItem', $existing['supportProjects'], $volume, ['image', 'alt', 'title', 'text']),
        ]);
    }

    private function seedSingle(string $sectionHandle, array $fieldValues): void
    {
        $entry = Entry::find()
            ->section($sectionHandle)
            ->status(null)
            ->orderBy(['elements.dateUpdated' => SORT_DESC, 'elements.id' => SORT_DESC])
            ->one();

        if (!$entry) {
            throw new Exception("Missing $sectionHandle entry.");
        }

        foreach ($fieldValues as $handle => $value) {
            $entry->setFieldValue($handle, $value);
        }

        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new Exception("Could not seed $sectionHandle matrix content: " . json_encode($entry->getErrors()));
        }
    }

    private function matrixRows(string $type, array $rows, Volume $volume, array $keys): array
    {
        $matrixRows = [];
        $sortOrder = [];
        $index = 1;

        foreach ($rows as $row) {
            $entryId = "new$index";
            $sortOrder[] = $entryId;

            $fields = [];
            foreach ($keys as $key) {
                $value = trim((string)($row[$key] ?? ''));

                match ($key) {
                    'image' => $fields['contentImage'] = $this->assetRelationValue($value, $volume),
                    'alt' => $fields['contentAlt'] = $value,
                    'date' => $fields['contentDate'] = $value,
                    'title' => $fields['contentItemTitle'] = $value,
                    'text' => $fields['contentItemText'] = $value,
                    default => null,
                };
            }

            $matrixRows[$entryId] = [
                'type' => $type,
                'enabled' => true,
                'fields' => $fields,
            ];
            $index++;
        }

        return [
            'sortOrder' => $sortOrder,
            'entries' => $matrixRows,
        ];
    }

    private function assetRelationValue(string $source, Volume $volume): array
    {
        $asset = $this->importAsset($source, $volume);
        return $asset ? [$asset->id] : [];
    }

    private function importAsset(string $source, Volume $volume): ?Asset
    {
        if (!$source || preg_match('/^https?:\/\//', $source)) {
            return null;
        }

        $sourcePath = $this->sourcePath($source);
        if (!$sourcePath || !is_file($sourcePath)) {
            return null;
        }

        $filename = basename($sourcePath);
        $existing = Asset::find()
            ->volumeId($volume->id)
            ->filename($filename)
            ->status(null)
            ->one();

        if ($existing) {
            return $existing;
        }

        $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
        if (!$folder) {
            $folder = Craft::$app->getAssets()->ensureFolderByFullPathAndVolume('', $volume, false);
        }

        $tempPath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('asset-', true) . '-' . $filename;
        if (!copy($sourcePath, $tempPath)) {
            throw new Exception("Could not copy $sourcePath to a temporary asset path.");
        }

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->setFilename($filename);
        $asset->newFolderId = $folder->id;
        $asset->setVolumeId($volume->id);
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new Exception("Could not import asset $source: " . json_encode($asset->getErrors()));
        }

        return $asset;
    }

    private function sourcePath(string $source): ?string
    {
        $source = ltrim($source, '/');
        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $source);

        return is_file($path) ? $path : null;
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

    private function communityNewsDefaults(): array
    {
        $text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.';

        return [
            ['image' => '/assets/medias/img/gallery-home_basilique.jpg', 'alt' => '', 'title' => 'La billeterie pour les concerts de printemps est ouverte!', 'text' => $text, 'date' => '18/03/2026'],
            ['image' => '/assets/medias/img/gallery-home_basilique.jpg', 'alt' => '', 'title' => 'La billeterie pour les concerts de printemps est ouverte!', 'text' => $text, 'date' => '18/03/2026'],
            ['image' => '/assets/medias/img/gallery-home_basilique.jpg', 'alt' => '', 'title' => 'La billeterie pour les concerts de printemps est ouverte!', 'text' => $text, 'date' => '18/03/2026'],
        ];
    }

    private function supportProjectsDefaults(): array
    {
        $text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';

        return [
            ['image' => '/assets/medias/img/gallery-home_basilique.jpg', 'alt' => '', 'title' => 'Projet en cours', 'text' => $text],
            ['image' => '/assets/medias/img/gallery-home_basilique.jpg', 'alt' => '', 'title' => 'Projet en cours', 'text' => $text],
            ['image' => '/assets/medias/img/gallery-home_basilique.jpg', 'alt' => '', 'title' => 'Projet en cours', 'text' => $text],
        ];
    }
}
