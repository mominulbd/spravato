<?php

declare(strict_types=1);

namespace Uptms\Settings\Services;

/**
 * Every clinic setting: its group (a tab of Figma 24), default and
 * validation rules. Defaults are the clinic's real details from the Figma
 * copy, so a fresh install shows correct information before anyone edits.
 */
final class SettingsRegistry
{
    public const array SOCIAL_NETWORKS = ['facebook', 'instagram', 'linkedin', 'youtube', 'x'];

    /**
     * @return array<string, array<string, array{default: mixed, rules: list<mixed>}>>
     */
    public function definitions(): array
    {
        return [
            'profile' => [
                'name' => ['default' => 'University Place TMS Clinic & General Psychiatry', 'rules' => ['required', 'string', 'max:120']],
                'tagline' => ['default' => 'Deep TMS and general psychiatric care for University Place and Greater Tacoma.', 'rules' => ['nullable', 'string', 'max:200']],
                'street' => ['default' => '4007 Bridgeport Way W, Suite B', 'rules' => ['required', 'string', 'max:120']],
                'city' => ['default' => 'University Place', 'rules' => ['required', 'string', 'max:80']],
                'state' => ['default' => 'WA', 'rules' => ['required', 'string', 'size:2', 'alpha']],
                'zip' => ['default' => '98466', 'rules' => ['required', 'string', 'regex:/^\d{5}(-\d{4})?$/']],
                'phone' => ['default' => '253-234-4199', 'rules' => ['required', 'string', 'regex:/^[0-9 ()+.\-]{7,20}$/']],
                'email' => ['default' => 'contact@uptms.com', 'rules' => ['required', 'email', 'max:190']],
                'latitude' => ['default' => 47.2271, 'rules' => ['nullable', 'numeric', 'between:-90,90']],
                'longitude' => ['default' => -122.5418, 'rules' => ['nullable', 'numeric', 'between:-180,180']],
                'service_area_label' => ['default' => 'University Place & Greater Tacoma', 'rules' => ['required', 'string', 'max:80']],
                'service_area' => ['default' => ['University Place', 'Tacoma', 'Lakewood', 'Fircrest', 'Gig Harbor', 'Puyallup'], 'rules' => ['array', 'max:12']],
                'main_site_url' => ['default' => 'https://uptms.com', 'rules' => ['nullable', 'url:https', 'max:190']],
                'social' => ['default' => [], 'rules' => ['array', 'max:6']],
            ],
            'announcement' => [
                'enabled' => ['default' => false, 'rules' => ['boolean']],
                'text' => ['default' => '', 'rules' => ['nullable', 'required_if_accepted:enabled', 'string', 'max:140']],
                'link_label' => ['default' => '', 'rules' => ['nullable', 'string', 'max:40']],
                'link_url' => ['default' => '', 'rules' => ['nullable', 'required_with:link_label', 'string', 'max:190', 'regex:/^(\/|https:\/\/|tel:|mailto:)/']],
            ],
            'disclaimers' => [
                'crisis' => ['default' => 'This site is not a crisis service. In the U.S., call or text 988 for crisis support; call 911 for an immediate emergency.', 'rules' => ['required', 'string', 'max:240']],
                'crisis_short' => ['default' => 'Not a crisis service. Call or text 988 for crisis support; call 911 for an immediate emergency.', 'rules' => ['required', 'string', 'max:160']],
                'insurance' => ['default' => '', 'rules' => ['nullable', 'string', 'max:500']],
                'testimonials' => ['default' => '', 'rules' => ['nullable', 'string', 'max:300']],
                'quiz' => ['default' => 'This self-check is educational. It is not a diagnosis and does not determine eligibility for treatment. Nothing is sent or saved.', 'rules' => ['nullable', 'string', 'max:300']],
            ],
            'privacy' => [
                'consultation_retention_days' => ['default' => 365, 'rules' => ['required', 'integer', 'between:30,3650']],
                'staff_notification_email' => ['default' => 'contact@uptms.com', 'rules' => ['nullable', 'email', 'max:190']],
                // On, the alert spells the request out; off, it carries a link only.
                // Health information either way once it is on (docs/SETUP.md § Mail).
                'staff_alert_details' => ['default' => true, 'rules' => ['required', 'boolean']],
            ],
            'security' => [
                // Off until an admin turns it on; the auth module reads it through Settings.
                'two_factor_login' => ['default' => (bool) config('auth.two_factor_login', false), 'rules' => ['required', 'boolean']],
            ],
        ];
    }

    /** @return list<string> */
    public function groups(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * Validation rules for one group's payload, including nested list items.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $group): array
    {
        $rules = [];

        foreach ($this->definitions()[$group] ?? [] as $key => $definition) {
            $rules[$key] = $definition['rules'];
        }

        if ($group === 'profile') {
            $rules['service_area.*'] = ['required', 'string', 'max:40', 'distinct'];
            $rules['social.*.network'] = ['required', 'in:'.implode(',', self::SOCIAL_NETWORKS)];
            $rules['social.*.url'] = ['required', 'url:https', 'max:190'];
        }

        return $rules;
    }
}
