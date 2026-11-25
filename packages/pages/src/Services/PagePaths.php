<?php

declare(strict_types=1);

namespace Uptms\Pages\Services;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Uptms\Pages\Models\Page;

/**
 * `/parent/slug` addresses. Reserved first segments belong to routes the
 * website owns (conditions, resources, api …) and can't be taken by a page.
 */
final class PagePaths
{
    public const array RESERVED = ['api', 'admin', 'conditions', 'resources', 'sitemap.xml', 'robots.txt', '_next', 'storage'];

    public function build(?Page $parent, string $slug, bool $isHome = false): string
    {
        if ($isHome) {
            return '/';
        }

        $path = ($parent !== null && ! $parent->is_home ? rtrim($parent->path, '/') : '').'/'.$slug;

        if (in_array(explode('/', ltrim($path, '/'))[0], self::RESERVED, true) && $parent === null) {
            throw new GenericDomainException(ErrorCode::ValidationFailed, 'This address is reserved by the website.', ['slug' => ['This address is reserved by the website.']]);
        }

        return $path;
    }

    public function assertAvailable(string $path, ?int $ignoreId = null): void
    {
        $taken = Page::withTrashed()->where('path', $path)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists();

        if ($taken) {
            throw new GenericDomainException(ErrorCode::ValidationFailed, 'Another page already uses this address.', ['slug' => ["Another page already uses {$path}."]]);
        }
    }

    /** A parent can't be the page itself or one of its descendants. */
    public function assertValidParent(Page $page, ?Page $parent): void
    {
        for ($cursor = $parent; $cursor !== null; $cursor = $cursor->parent) {
            if ($cursor->id === $page->id) {
                throw new GenericDomainException(ErrorCode::ValidationFailed, 'A page cannot sit under itself.', ['parent_id' => ['A page cannot sit under itself.']]);
            }
        }
    }

    /** After a slug or parent change, children follow. */
    public function cascade(Page $page): void
    {
        foreach ($page->children()->withTrashed()->get() as $child) {
            $child->path = $this->build($page, $child->slug);
            $child->saveQuietly();
            $this->cascade($child);
        }
    }
}
