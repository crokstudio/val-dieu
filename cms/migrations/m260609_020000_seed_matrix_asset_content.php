<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Volume;
use yii\base\Exception;

class m260609_020000_seed_matrix_asset_content extends Migration
{
    public function safeUp(): bool
    {
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('contentImages');
        if (!$volume) {
            throw new Exception('Missing contentImages volume.');
        }

        $this->seedIfNeeded('homepage', 'homepageGallery', 'homepageGalleryItem', $this->homepageGalleryDefaults(), $volume, ['image', 'alt'], 5);
        $this->seedIfNeeded('discover', 'discoverTimeline', 'discoverTimelineItem', $this->discoverTimelineDefaults(), $volume, ['date', 'title', 'text', 'image', 'alt']);
        $this->seedIfNeeded('community', 'communityNews', 'communityNewsItem', $this->communityNewsDefaults(), $volume, ['image', 'alt', 'title', 'text', 'date']);
        $this->seedIfNeeded('support', 'supportProjects', 'supportProjectItem', $this->supportProjectsDefaults(), $volume, ['image', 'alt', 'title', 'text']);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260609_020000_seed_matrix_asset_content cannot be reverted automatically without risking editor content.\n";
        return false;
    }

    private function seedIfNeeded(string $sectionHandle, string $fieldHandle, string $entryTypeHandle, array $rows, Volume $volume, array $keys, ?int $expectedCount = null): void
    {
        $entry = Entry::find()
            ->section($sectionHandle)
            ->status(null)
            ->one();

        if (!$entry) {
            throw new Exception("Missing $sectionHandle entry.");
        }

        $value = $entry->getFieldValue($fieldHandle);
        $count = is_object($value) && method_exists($value, 'status') ? $value->status(null)->count() : 0;

        if ($expectedCount !== null ? $count === $expectedCount : $count > 0) {
            return;
        }

        $entry->setFieldValue($fieldHandle, $this->matrixValue($entryTypeHandle, $rows, $volume, $keys));

        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new Exception("Could not seed $fieldHandle: " . json_encode($entry->getErrors()));
        }
    }

    private function matrixValue(string $entryTypeHandle, array $rows, Volume $volume, array $keys): array
    {
        $entries = [];
        $sortOrder = [];

        foreach (array_values($rows) as $index => $row) {
            $id = 'new' . ($index + 1);
            $sortOrder[] = $id;

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

            $entries[$id] = [
                'type' => $entryTypeHandle,
                'enabled' => true,
                'fresh' => true,
                'fields' => $fields,
            ];
        }

        return [
            'sortOrder' => $sortOrder,
            'entries' => $entries,
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
        if (!$sourcePath) {
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

        $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)
            ?? Craft::$app->getAssets()->ensureFolderByFullPathAndVolume('', $volume, false);
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
        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($source, '/'));

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
