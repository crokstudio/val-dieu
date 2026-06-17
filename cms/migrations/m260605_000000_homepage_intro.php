<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\Field;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\PlainText;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\TitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use yii\base\Exception;

class m260605_000000_homepage_intro extends Migration
{
    private const DEFAULT_INTRO = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.';

    public function safeUp(): bool
    {
        $introField = $this->ensureIntroField();
        $entryType = $this->ensureHomepageEntryType($introField);
        $section = $this->ensureHomepageSection($entryType);
        $this->ensureHomepageEntry($section);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260605_000000_homepage_intro is non-destructive; remove the Homepage section manually if needed.\n";
        return false;
    }

    private function ensureIntroField(): Field
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('introText');

        if ($field) {
            return $field;
        }

        $field = new PlainText([
            'name' => 'Intro text',
            'handle' => 'introText',
            'instructions' => 'Editable intro paragraph displayed on the homepage.',
            'translationMethod' => Field::TRANSLATION_METHOD_SITE,
            'multiline' => true,
            'initialRows' => 6,
            'searchable' => true,
        ]);

        if (!$fields->saveField($field)) {
            throw new Exception('Could not create Intro text field: ' . json_encode($field->getErrors()));
        }

        return $field;
    }

    private function ensureHomepageEntryType(Field $introField): EntryType
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle('homepage') ?? new EntryType([
            'name' => 'Homepage',
            'handle' => 'homepage',
        ]);

        $fieldLayout = new FieldLayout([
            'type' => Entry::class,
        ]);
        $fieldLayout->setTabs([
            [
                'name' => 'Content',
                'elements' => [
                    new TitleField([
                        'label' => 'Internal title',
                        'required' => true,
                    ]),
                    new CustomField($introField, [
                        'required' => true,
                    ]),
                ],
            ],
        ]);

        $entryType->setFieldLayout($fieldLayout);

        if (!$entries->saveEntryType($entryType)) {
            throw new Exception('Could not create Homepage entry type: ' . json_encode($entryType->getErrors()));
        }

        return $entryType;
    }

    private function ensureHomepageSection(EntryType $entryType): Section
    {
        $entries = Craft::$app->getEntries();
        $site = Craft::$app->getSites()->getPrimarySite();
        $section = $entries->getSectionByHandle('homepage') ?? new Section();

        $section->name = 'Homepage';
        $section->handle = 'homepage';
        $section->type = Section::TYPE_SINGLE;
        $section->enableVersioning = true;
        $section->previewTargets = [];
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

        if (!$entries->saveSection($section)) {
            throw new Exception('Could not create Homepage section: ' . json_encode($section->getErrors()));
        }

        return $section;
    }

    private function ensureHomepageEntry(Section $section): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $entryType = $section->getEntryTypes()[0] ?? null;

        if (!$entryType) {
            throw new Exception('Homepage section has no entry type.');
        }

        $entry = Entry::find()
            ->section('homepage')
            ->siteId($site->id)
            ->anyStatus()
            ->one();

        if (!$entry) {
            $entry = new Entry([
                'sectionId' => $section->id,
                'siteId' => $site->id,
            ]);
            $entry->setTypeId($entryType->id);
        }

        $entry->title = $entry->title ?: 'Homepage';
        $entry->slug = $entry->slug ?: 'homepage';
        $entry->enabled = true;

        if (!$entry->getFieldValue('introText')) {
            $entry->setFieldValue('introText', self::DEFAULT_INTRO);
        }

        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new Exception('Could not create Homepage entry: ' . json_encode($entry->getErrors()));
        }
    }
}
