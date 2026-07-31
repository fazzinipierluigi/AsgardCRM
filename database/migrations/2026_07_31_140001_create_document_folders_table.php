<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            // Deliberately not a real FK: a self-referencing constraint
            // here confused SQLite's (used by the test suite)
            // dependency-ordering when dropping/recreating tables between
            // tests ("no such table: main.document_folders" while
            // dropping an unrelated table). Validity (must belong to the
            // same entity) is enforced in StoreDocumentFolderRequest, and
            // DocumentController::destroyFolder() refuses to delete a
            // non-empty folder, so a DB-level cascade was never actually
            // exercised anyway.
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['entity_id', 'parent_id']);

            // MySQL/MariaDB treat NULL as distinct in a unique index, so
            // this only actually enforces uniqueness among siblings that
            // share a real parent — two root-level folders (parent_id
            // NULL) can still collide by name. Checked in application
            // code too (see DocumentController::storeFolder()) so root
            // folders get the same guarantee.
            $table->unique(['entity_id', 'parent_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_folders');
    }
};
