<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class ContentPlan
{
    /**
     * @param  array<int, array{heading: string, purpose: string}>  $outline
     * @param  array<int, string>  $keyPoints
     * @param  array<int, string>  $productionTasks
     * @param  array<int, string>  $risksOrMissingInformation
     */
    public function __construct(
        public string $suggestedTitle,
        public string $contentBrief,
        public array $outline,
        public array $keyPoints,
        public array $productionTasks,
        public array $risksOrMissingInformation,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        self::validate($data);

        /** @var array<int, array{heading: string, purpose: string}> $outline */
        $outline = $data['outline'];

        /** @var array<int, string> $keyPoints */
        $keyPoints = $data['key_points'];

        /** @var array<int, string> $productionTasks */
        $productionTasks = $data['production_tasks'];

        /** @var array<int, string> $risks */
        $risks = $data['risks_or_missing_information'];

        return new self(
            suggestedTitle: $data['suggested_title'],
            contentBrief: $data['content_brief'],
            outline: $outline,
            keyPoints: $keyPoints,
            productionTasks: $productionTasks,
            risksOrMissingInformation: $risks,
        );
    }

    /**
     * @return array{
     *     suggested_title: string,
     *     content_brief: string,
     *     outline: array<int, array{heading: string, purpose: string}>,
     *     key_points: array<int, string>,
     *     production_tasks: array<int, string>,
     *     risks_or_missing_information: array<int, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'suggested_title' => $this->suggestedTitle,
            'content_brief' => $this->contentBrief,
            'outline' => $this->outline,
            'key_points' => $this->keyPoints,
            'production_tasks' => $this->productionTasks,
            'risks_or_missing_information' => $this->risksOrMissingInformation,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function validate(array $data): void
    {
        $required = [
            'suggested_title',
            'content_brief',
            'outline',
            'key_points',
            'production_tasks',
            'risks_or_missing_information',
        ];

        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    "Content plan is missing required field: {$field}"
                );
            }
        }

        if (! is_string($data['suggested_title'])) {
            throw new InvalidArgumentException('suggested_title must be a string.');
        }

        if (! is_string($data['content_brief'])) {
            throw new InvalidArgumentException('content_brief must be a string.');
        }

        self::validateStringList($data['key_points'], 'key_points');
        self::validateStringList($data['production_tasks'], 'production_tasks');
        self::validateStringList(
            $data['risks_or_missing_information'],
            'risks_or_missing_information'
        );

        if (! is_array($data['outline'])) {
            throw new InvalidArgumentException('outline must be an array.');
        }

        foreach ($data['outline'] as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException(
                    'Each outline item must be an object.'
                );
            }

            if (! isset($item['heading']) || ! is_string($item['heading'])) {
                throw new InvalidArgumentException(
                    'Each outline item must have a string heading.'
                );
            }

            if (! isset($item['purpose']) || ! is_string($item['purpose'])) {
                throw new InvalidArgumentException(
                    'Each outline item must have a string purpose.'
                );
            }
        }
    }

    private static function validateStringList(
        mixed $value,
        string $field,
    ): void {
        if (! is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an array.");
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException(
                    "Every item in {$field} must be a string."
                );
            }
        }
    }
}
