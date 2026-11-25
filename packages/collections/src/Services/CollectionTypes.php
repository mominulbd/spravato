<?php

declare(strict_types=1);

namespace Uptms\Collections\Services;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Uptms\Collections\Types\CollectionType;
use Uptms\Collections\Types\ContentItemType;
use Uptms\Collections\Types\FaqType;
use Uptms\Collections\Types\InsuranceType;
use Uptms\Collections\Types\TeamType;
use Uptms\Collections\Types\TestimonialType;

/** The tabs of Figma 14–15, in order. */
final class CollectionTypes
{
    /** @var array<string, CollectionType>|null */
    private ?array $types = null;

    /** @return array<string, CollectionType> */
    public function all(): array
    {
        if ($this->types === null) {
            $this->types = [];

            foreach ([
                new TeamType,
                new FaqType,
                new TestimonialType,
                new InsuranceType,
                new ContentItemType('trust-points', 'Trust points', 'trust_points'),
                new ContentItemType('journey-steps', 'Journey steps', 'journey_steps'),
            ] as $type) {
                $this->types[$type->key()] = $type;
            }
        }

        return $this->types;
    }

    public function get(string $key): CollectionType
    {
        return $this->all()[$key] ?? throw new NotFoundHttpException("Unknown collection {$key}.");
    }
}
