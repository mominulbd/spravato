<?php

declare(strict_types=1);

/*
 * Module boundaries (docs/ARCHITECTURE.md §3): dependencies point down the
 * module catalogue, never up, and the shared kernel knows no module. Each
 * phase adds the rules for the modules it introduces.
 */

arch('the kernel depends on no module')
    ->expect('App\Support')
    ->not->toUse('Uptms');

arch('the kernel uses strict types')
    ->expect('App\Support')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

// Phase 1: audit_log ← permissions ← users ← auth.
arch('modules use strict types')
    ->expect('Uptms')
    ->toUseStrictTypes();

arch('the audit log depends on no other module')
    ->expect('Uptms\AuditLog')
    ->not->toUse(['Uptms\Permissions', 'Uptms\Users', 'Uptms\Auth']);

arch('permissions know nothing of users or sign-in')
    ->expect('Uptms\Permissions')
    ->not->toUse(['Uptms\Users', 'Uptms\Auth']);

arch('users know nothing of sign-in')
    ->expect('Uptms\Users')
    ->not->toUse('Uptms\Auth');

arch('sign-in reads the two-factor switch only through the settings service')
    ->expect('Uptms\Auth')
    ->not->toUse(['Uptms\Settings\Models', 'Uptms\Settings\Http']);

arch('controllers stay thin: no direct mail or notifications')
    ->expect('Uptms\*\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\Mail', 'Illuminate\Support\Facades\Notification']);

// Posts, Quiz and Conditions were removed for Spravato (docs/DEVELOPMENT-PLAN.md D6).
arch('no code refers to the removed posts, quiz or conditions modules')
    ->expect(['App', 'Database', 'Uptms'])
    ->not->toUse(['Uptms\Posts', 'Uptms\Quiz', 'Uptms\Conditions']);

// Phase 2: publishing ← media ← settings, collections; pages ← menus.
arch('publishing is the shared content kernel and knows no content module')
    ->expect('Uptms\Publishing')
    ->not->toUse(['Uptms\Media', 'Uptms\Settings', 'Uptms\Collections', 'Uptms\Pages', 'Uptms\Menus', 'Uptms\Users', 'Uptms\Auth']);

arch('media knows no content module')
    ->expect('Uptms\Media')
    ->not->toUse(['Uptms\Settings', 'Uptms\Collections', 'Uptms\Pages', 'Uptms\Menus', 'Uptms\Users', 'Uptms\Auth']);

arch('content modules never reach for users or sign-in')
    ->expect(['Uptms\Settings', 'Uptms\Collections', 'Uptms\Pages', 'Uptms\Menus'])
    ->not->toUse(['Uptms\Users', 'Uptms\Auth']);

arch('pages read other modules only through block sources')
    ->expect('Uptms\Pages')
    ->not->toUse(['Uptms\Settings', 'Uptms\Collections', 'Uptms\Menus']);

arch('collections and settings stand alone')
    ->expect(['Uptms\Collections', 'Uptms\Settings'])
    ->not->toUse(['Uptms\Pages', 'Uptms\Menus']);

arch('menus use pages only through the page directory')
    ->expect('Uptms\Menus')
    ->not->toUse(['Uptms\Pages\Models', 'Uptms\Collections']);

// Phase 3: consultations sit above users, settings and publishing.
arch('lower modules know nothing of consultations')
    ->expect(['Uptms\AuditLog', 'Uptms\Permissions', 'Uptms\Users', 'Uptms\Auth', 'Uptms\Publishing', 'Uptms\Media', 'Uptms\Settings', 'Uptms\Collections', 'Uptms\Pages', 'Uptms\Menus'])
    ->not->toUse('Uptms\Consultations');

arch('consultations reach other modules only through their services')
    ->expect('Uptms\Consultations')
    ->not->toUse(['Uptms\Users\Models', 'Uptms\AuditLog\Models', 'Uptms\Settings\Models', 'Uptms\Auth', 'Uptms\Pages', 'Uptms\Collections', 'Uptms\Menus', 'Uptms\Media']);

arch('consultation requests are never model-audited (no patient data in the log)')
    ->expect('Uptms\Consultations\Models')
    ->not->toUse('Uptms\AuditLog\Concerns\Auditable');

// Phase 4: seo sits under every content module; content describes itself through SeoSources.
arch('seo knows no content module')
    ->expect('Uptms\Seo')
    ->not->toUse(['Uptms\Pages', 'Uptms\Collections', 'Uptms\Menus', 'Uptms\Consultations', 'Uptms\Users', 'Uptms\Auth', 'Uptms\Media\Models', 'Uptms\Settings\Models']);

arch('content modules use the SEO module only through its services and contracts')
    ->expect('Uptms\Pages')
    ->not->toUse('Uptms\Seo\Models');

arch('redirects learn about moves from events and about pages from the SEO index')
    ->expect('Uptms\Redirects')
    ->not->toUse(['Uptms\Pages', 'Uptms\Users', 'Uptms\Auth', 'Uptms\Seo\Models']);
