<?php

declare(strict_types=1);

namespace Uptms\Media\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Uptms\Media\Models\Media;

/**
 * Rows only; no file is written. Tests that need a real file upload one.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid7();
        $name = Str::slug($this->faker->word().' '.$this->faker->word()).'.jpg';

        return [
            'uuid' => $uuid,
            'disk' => 'public',
            'path' => "media/{$uuid}/abc123-{$name}",
            'filename' => $name,
            'original_name' => $name,
            'mime' => 'image/jpeg',
            'kind' => Media::IMAGE,
            'size' => 120_000,
            'original_size' => 900_000,
            'width' => 1600,
            'height' => 1200,
            'alt' => $this->faker->sentence(5),
            'is_decorative' => false,
        ];
    }

    public function withoutAlt(): static
    {
        return $this->state(['alt' => null]);
    }

    public function document(): static
    {
        return $this->state(['mime' => 'application/pdf', 'kind' => Media::DOCUMENT, 'width' => null, 'height' => null, 'alt' => null]);
    }
}
