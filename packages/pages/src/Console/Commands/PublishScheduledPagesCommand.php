<?php

declare(strict_types=1);

namespace Uptms\Pages\Console\Commands;

use Illuminate\Console\Command;
use Uptms\Pages\Actions\PublishPageAction;
use Uptms\Pages\Models\Page;
use Uptms\Publishing\Models\Revision;

/** Every minute: scheduled versions whose time has come go live. */
final class PublishScheduledPagesCommand extends Command
{
    protected $signature = 'pages:publish-scheduled';

    protected $description = 'Publish page versions whose scheduled time has passed';

    public function handle(PublishPageAction $publish): int
    {
        $count = 0;

        Page::query()
            ->whereNotNull('scheduled_revision_id')
            ->where('scheduled_at', '<=', now())
            ->each(function (Page $page) use ($publish, &$count): void {
                $revision = Revision::query()->find($page->scheduled_revision_id);

                if ($revision instanceof Revision) {
                    $publish->execute($page, $revision);
                    $count++;
                }
            });

        $this->info("Published {$count} scheduled page(s).");

        return self::SUCCESS;
    }
}
