<?php

namespace App\Support\Forms;

use App\DTOs\Forms\FormDefinitionData;
use App\DTOs\Forms\PrerequisiteDocumentData;
use App\Enums\Forms\FormCategory;
use InvalidArgumentException;

final class StartupFormCatalog
{
    /** @var array<int, FormDefinitionData>|null */
    private ?array $formsById = null;

    /**
     * @return list<FormDefinitionData>
     */
    public function all(): array
    {
        return array_values($this->definitions());
    }

    public function find(int $id): ?FormDefinitionData
    {
        return $this->definitions()[$id] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forFrontend(): array
    {
        return array_map(
            static fn (FormDefinitionData $form): array => $form->toArray(),
            $this->all()
        );
    }

    /**
     * @return array<int, FormDefinitionData>
     */
    private function definitions(): array
    {
        if ($this->formsById !== null) {
            return $this->formsById;
        }

        $forms = [];
        foreach (config('startup_forms', []) as $definition) {
            if (! is_array($definition)
                || ! isset($definition['id'], $definition['name'], $definition['category'], $definition['attachment_only'])
                || ! is_int($definition['id'])
                || ! is_string($definition['name'])
                || ! is_string($definition['category'])
                || ! is_bool($definition['attachment_only'])) {
                throw new InvalidArgumentException('Startup form configuration has an invalid definition.');
            }

            $category = FormCategory::tryFrom($definition['category']);
            if ($category === null) {
                throw new InvalidArgumentException('Startup form configuration contains an unknown category.');
            }

            $prerequisiteConfig = $definition['prerequisite'] ?? null;
            $prerequisite = null;
            if ($prerequisiteConfig !== null) {
                if (! is_array($prerequisiteConfig)
                    || ! isset(
                        $prerequisiteConfig['key'],
                        $prerequisiteConfig['required'],
                        $prerequisiteConfig['allowed_extensions'],
                        $prerequisiteConfig['max_kilobytes'],
                        $prerequisiteConfig['filename_pattern'],
                    )
                    || ! is_string($prerequisiteConfig['key'])
                    || ! is_bool($prerequisiteConfig['required'])
                    || ! is_array($prerequisiteConfig['allowed_extensions'])
                    || ! is_int($prerequisiteConfig['max_kilobytes'])
                    || ! is_string($prerequisiteConfig['filename_pattern'])) {
                    throw new InvalidArgumentException('Startup form prerequisite configuration is invalid.');
                }

                $prerequisite = new PrerequisiteDocumentData(
                    key: $prerequisiteConfig['key'],
                    label: $definition['name'],
                    required: $prerequisiteConfig['required'],
                    allowedExtensions: $prerequisiteConfig['allowed_extensions'],
                    maxKilobytes: $prerequisiteConfig['max_kilobytes'],
                    filenamePattern: $prerequisiteConfig['filename_pattern'],
                );
            }

            if ($definition['attachment_only'] !== ($prerequisite !== null)) {
                throw new InvalidArgumentException('Attachment-only forms must define prerequisite documents.');
            }

            $forms[$definition['id']] = new FormDefinitionData(
                id: $definition['id'],
                name: $definition['name'],
                category: $category,
                attachmentOnly: $definition['attachment_only'],
                prerequisite: $prerequisite,
            );
        }

        if (count($forms) !== count(config('startup_forms', []))) {
            throw new InvalidArgumentException('Startup form catalog contains duplicate form IDs.');
        }

        return $this->formsById = $forms;
    }
}
