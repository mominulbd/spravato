<?php

declare(strict_types=1);

namespace Uptms\Settings\Services;

/**
 * What `GET /public/settings` returns: everything the website shell, the
 * location panel and the schema need, and nothing internal (retention,
 * notification address).
 */
final readonly class PublicSettings
{
    public function __construct(
        private Settings $settings,
        private OfficeHours $hours,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $profile = $this->settings->group('profile');
        $announcement = $this->settings->group('announcement');
        $disclaimers = $this->settings->group('disclaimers');
        $phone = (string) $profile['phone'];
        $address = implode(', ', [$profile['street'], $profile['city'].', '.$profile['state'].' '.$profile['zip']]);

        return [
            'clinic' => [
                'name' => $profile['name'],
                'tagline' => $profile['tagline'],
                'service_area_label' => $profile['service_area_label'],
                'service_area' => array_values((array) $profile['service_area']),
                'phone' => ['display' => $phone, 'href' => self::telHref($phone)],
                'email' => $profile['email'],
                'address' => [
                    'street' => $profile['street'],
                    'locality' => $profile['city'],
                    'region' => $profile['state'],
                    'postal_code' => $profile['zip'],
                    'country' => 'US',
                    'single_line' => $address,
                ],
                'geo' => $profile['latitude'] !== null && $profile['longitude'] !== null
                    ? ['lat' => (float) $profile['latitude'], 'lng' => (float) $profile['longitude']]
                    : null,
                'directions_url' => 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($profile['name'].', '.$address),
                'main_site_url' => $profile['main_site_url'] ?: null,
                'social' => array_values((array) $profile['social']),
            ],
            'hours' => [
                'short' => $this->hours->short(),
                'long' => $this->hours->long(),
                'days' => array_map(fn (array $day): array => [
                    'day' => $day['day'],
                    'name' => $day['name'],
                    'is_open' => $day['is_open'],
                    'opens_at' => $day['opens_at'],
                    'closes_at' => $day['closes_at'],
                    'display' => $this->hours->display($day['opens_at'], $day['closes_at']) ?? 'Closed',
                ], $this->hours->week()),
                'closures' => array_map(fn (array $closure): array => ['date' => $closure['date'], 'label' => $closure['label']], $this->hours->closures(upcomingOnly: true)),
            ],
            'announcement' => $announcement['enabled'] === true && $announcement['text'] !== ''
                ? ['text' => $announcement['text'], 'link_label' => $announcement['link_label'] ?: null, 'link_url' => $announcement['link_url'] ?: null]
                : null,
            'legal' => [
                'copyright' => '© '.now()->year.' '.$profile['name'],
                'crisis' => $disclaimers['crisis'],
                'crisis_short' => $disclaimers['crisis_short'],
                'insurance' => $disclaimers['insurance'],
                'testimonials' => $disclaimers['testimonials'],
            ],
        ];
    }

    /** "253-234-4199" → "tel:+12532344199" (US numbers), otherwise digits only. */
    public static function telHref(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return 'tel:+'.$digits;
    }
}
