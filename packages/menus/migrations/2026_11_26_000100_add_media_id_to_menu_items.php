<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A `document` menu item links to a media-library file by id, so replacing
 * the PDF (new address, old file deleted) keeps the link working: the
 * address is resolved when the menu is served. Purging the file empties
 * the reference and the website drops the item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->foreignId('media_id')->nullable()->after('page_id')->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_id');
        });
    }
};
