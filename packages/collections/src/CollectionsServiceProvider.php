<?php

declare(strict_types=1);

namespace Uptms\Collections;

use App\Support\Packages\ModuleServiceProvider;
use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Models\ContentItem;
use Uptms\Collections\Models\Faq;
use Uptms\Collections\Models\InsurancePartner;
use Uptms\Collections\Models\TeamMember;
use Uptms\Collections\Models\Testimonial;
use Uptms\Collections\Policies\CollectionItemPolicy;
use Uptms\Collections\Services\CollectionReader;
use Uptms\Collections\Services\CollectionTypes;
use Uptms\Collections\Sources\CollectionSource;
use Uptms\Publishing\Services\BlockSources;

final class CollectionsServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        CollectionTypes::class => CollectionTypes::class,
        CollectionReader::class => CollectionReader::class,
    ];

    protected array $morphMap = [
        'team_member' => TeamMember::class,
        'faq' => Faq::class,
        'testimonial' => Testimonial::class,
        'insurance_partner' => InsurancePartner::class,
        'content_item' => ContentItem::class,
    ];

    protected array $policies = [
        CollectionItem::class => CollectionItemPolicy::class,
        TeamMember::class => CollectionItemPolicy::class,
        Faq::class => CollectionItemPolicy::class,
        Testimonial::class => CollectionItemPolicy::class,
        InsurancePartner::class => CollectionItemPolicy::class,
        ContentItem::class => CollectionItemPolicy::class,
    ];

    protected function bootModule(): void
    {
        $sources = $this->app->make(BlockSources::class);
        $types = $this->app->make(CollectionTypes::class);

        foreach ($types->all() as $type) {
            $sources->register($type->key(), fn (): CollectionSource => new CollectionSource($type, $this->app->make(CollectionReader::class)));
        }
    }
}
