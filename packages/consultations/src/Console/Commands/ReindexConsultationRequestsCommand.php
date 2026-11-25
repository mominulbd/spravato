<?php

declare(strict_types=1);

namespace Uptms\Consultations\Console\Commands;

use Illuminate\Console\Command;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Services\BlindIndex;

/** Rebuilds the search hashes, e.g. after CONSULTATION_INDEX_KEY changes. */
final class ReindexConsultationRequestsCommand extends Command
{
    protected $signature = 'consultations:reindex';

    protected $description = 'Rebuild the encrypted-search index of consultation requests';

    public function handle(BlindIndex $index): int
    {
        $count = 0;

        ConsultationRequest::query()->chunkById(200, function ($requests) use ($index, &$count): void {
            foreach ($requests as $request) {
                $request->phone_hash = $index->phoneHash($request->phone);
                $request->search_index = $index->build($request->name, $request->phone, $request->email);
                $request->timestamps = false;
                $request->save();
                $count++;
            }
        });

        $this->info("Reindexed {$count} requests.");

        return self::SUCCESS;
    }
}
