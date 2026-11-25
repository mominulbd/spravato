<?php

declare(strict_types=1);

namespace Uptms\Permissions\Services;

/**
 * The permission matrix from Figma screen 26 (docs/ARCHITECTURE.md §5). The
 * database is seeded from this list; it is the one list routes, policies
 * and the admin cite. Keys for later phases are added when their module
 * ships, but the matrix the clinic signed off is registered up front so
 * roles can be configured before the features land.
 */
final class PermissionRegistry
{
    public const string SUPER_ADMIN = 'super_admin';

    /**
     * group → label + `{module}.{action}` → [label, description]
     *
     * @var array<string, array{label: string, permissions: array<string, array{0: string, 1: string}>}>
     */
    private const array GROUPS = [
        'consultations' => [
            'label' => 'Consultation requests',
            'permissions' => [
                'consultations.view' => ['View requests', 'See contact details · every view is logged'],
                'consultations.view_all' => ['See every request', 'Without it, only requests assigned to them'],
                'consultations.update' => ['Update status & notes', 'Change status, assign, add notes'],
                'consultations.export' => ['Export & delete', 'CSV export and permanent deletion'],
            ],
        ],
        'content' => [
            'label' => 'Content',
            'permissions' => [
                'content.edit' => ['Edit pages, posts & collections', 'Save drafts and request review'],
                'content.publish' => ['Publish & schedule', 'Make changes live on uptms.com'],
                'content.review' => ['Approve clinical review', 'Sign off health content as reviewer'],
                'media.manage' => ['Media library', 'Upload, replace and clean up files'],
            ],
        ],
        'seo' => [
            'label' => 'SEO',
            'permissions' => [
                'seo.edit' => ['SEO fields on pages', 'Titles, descriptions, social, schema'],
                'seo.manage' => ['Redirects, sitemap & robots.txt', 'Site-wide search settings'],
            ],
        ],
        'system' => [
            'label' => 'System',
            'permissions' => [
                'admin.access' => ['Sign in to the admin', 'Every staff role needs this'],
                'settings.manage' => ['Clinic settings', 'Address, hours, disclaimers, retention'],
                'users.manage' => ['Users & roles', 'Invite, remove, change roles'],
                'roles.manage' => ['Edit role permissions', 'Change what each role can do'],
                'audit.view' => ['Audit log', 'View and export activity'],
            ],
        ],
    ];

    /** @return list<string> every `{module}.{action}` key */
    public function keys(): array
    {
        $keys = [];

        foreach (self::GROUPS as $group) {
            foreach (array_keys($group['permissions']) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The matrix as the admin renders it.
     *
     * @return list<array{key: string, label: string, permissions: list<array{key: string, label: string, description: string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (self::GROUPS as $key => $group) {
            $permissions = [];

            foreach ($group['permissions'] as $permission => [$label, $description]) {
                $permissions[] = ['key' => $permission, 'label' => $label, 'description' => $description];
            }

            $groups[] = ['key' => $key, 'label' => $group['label'], 'permissions' => $permissions];
        }

        return $groups;
    }

    public function exists(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /** @return array{0: string, 1: string} label, description */
    public function describe(string $key): array
    {
        foreach (self::GROUPS as $group) {
            if (isset($group['permissions'][$key])) {
                return $group['permissions'][$key];
            }
        }

        return [$key, ''];
    }

    /**
     * Seeded roles. `*` means every permission. Grants are editable
     * afterwards in the admin, except the Super Admin's, which bypasses
     * every check anyway.
     *
     * @return array<string, array{name: string, description: string, grants: list<string>}>
     */
    public function roles(): array
    {
        return [
            self::SUPER_ADMIN => [
                'name' => 'Super Admin',
                'description' => 'Everything, including clinical sign-off and role permissions.',
                'grants' => ['*'],
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'Runs the site and the team; cannot manage Super Admins.',
                'grants' => [
                    'admin.access', 'consultations.view', 'consultations.view_all', 'consultations.update', 'consultations.export',
                    'content.edit', 'content.publish', 'media.manage', 'seo.edit', 'seo.manage',
                    'settings.manage', 'users.manage', 'audit.view',
                ],
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Handles requests and publishes content.',
                'grants' => [
                    'admin.access', 'consultations.view', 'consultations.view_all', 'consultations.update',
                    'content.edit', 'content.publish', 'media.manage', 'seo.edit',
                ],
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'Works the requests assigned to them and drafts content.',
                'grants' => ['admin.access', 'consultations.view', 'consultations.update', 'content.edit'],
            ],
        ];
    }

    /**
     * Expand `*` into concrete keys.
     *
     * @param  list<string>  $grants
     * @return list<string>
     */
    public function expand(array $grants): array
    {
        return in_array('*', $grants, true) ? $this->keys() : array_values(array_unique($grants));
    }
}
