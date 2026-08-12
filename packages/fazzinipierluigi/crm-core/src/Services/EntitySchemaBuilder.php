<?php

namespace Fazzinipierluigi\CrmCore\Services;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityRelationTargetType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Creates/extends/drops the real, dedicated SQL table backing an
 * installed Entity. This is the only place in the app that runs
 * schema changes outside database/migrations.
 */
class EntitySchemaBuilder
{
    /**
     * Reserved column names every dynamic entity table already uses for
     * its own bookkeeping.
     */
    public const RESERVED_COLUMN_NAMES = [
        'id', 'user_id', 'created_at', 'updated_at', 'deleted_at', 'relatable_type', 'relatable_id',
        'folder_id', 'original_filename', 'stored_path', 'mime_type', 'file_size',
        'mail_account_id', 'folder', 'message_uid', 'uid_validity', 'message_id',
    ];

    /**
     * Create the entity's table with its base ownership columns plus
     * one column per currently-defined field. Calendar entities also get
     * a polymorphic relatable_type/relatable_id pair (the "relationship
     * with an entity" hardcoded on every calendar event, resolved via
     * EntityRelationTargetType the same way a Relation field's target is
     * — but not exposed as an editable EntityField, since it isn't one).
     * Documents entities similarly get a fixed set of upload-bookkeeping
     * columns (see DocumentController) plus a `folder_id` pointing into
     * the entity's own document_folders tree (Fazzinipierluigi\CrmCore\Models\DocumentFolder)
     * — no real FK constraint despite document_folders being a real,
     * static table, see the comment at its column definition below.
     * Email entities get a fixed set of message-bookmark columns (see
     * MailController) instead of a real body/attachments column — the
     * whole point of the mail module is never bulk-storing a mailbox
     * locally, only remembering enough (account + folder + server-side
     * message id) to re-fetch a specific message live on demand.
     */
    public function create(Entity $entity): void
    {
        Schema::create($entity->table_name, function (Blueprint $table) use ($entity) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            foreach ($entity->allFields() as $field) {
                $this->addColumnToBlueprint($table, $field);
            }

            if ($entity->is_calendar) {
                $table->string('relatable_type')->nullable();
                $table->unsignedBigInteger('relatable_id')->nullable();
                $table->index(['relatable_type', 'relatable_id']);
            }

            if ($entity->is_documents) {
                // Deliberately not a real FK to document_folders (unlike
                // a Relation field's FK to another entity's table): this
                // dynamically-created table isn't tracked by Laravel's
                // migration system, and a real FK from it back to a
                // static migration-tracked table confused SQLite's
                // (the test suite's driver) drop/recreate ordering
                // between tests ("no such table: main.document_folders"
                // while dropping an unrelated table). Referential
                // validity is enforced in StoreDocumentRequest instead.
                $table->unsignedBigInteger('folder_id')->nullable();
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
            }

            if ($entity->is_email) {
                // mail_account_id: same "no real FK" reasoning as
                // folder_id above — mail_accounts is a real, static,
                // migration-tracked table, but this dynamic one isn't,
                // so a DB-level FK reproduces the same SQLite
                // drop/recreate ordering failure. Validity is enforced
                // in MailController::attach() instead. The row is a
                // bookmark (account + folder + server-side message id),
                // never the message body — see EntityFieldType-less
                // reserved columns above, mirroring is_calendar's
                // relatable_type/relatable_id pattern.
                $table->unsignedBigInteger('mail_account_id')->nullable();
                $table->string('folder', 500)->nullable();
                $table->string('message_uid', 500)->nullable();
                $table->unsignedBigInteger('uid_validity')->nullable();
                $table->string('message_id', 500)->nullable();
                $table->index(['mail_account_id', 'folder']);
            }

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Add a single new column to an already-installed entity's table,
     * for a field created after the initial install.
     */
    public function addColumn(Entity $entity, EntityField $field): void
    {
        Schema::table($entity->table_name, function (Blueprint $table) use ($field) {
            $this->addColumnToBlueprint($table, $field);
        });
    }

    /**
     * Drop the entity's table entirely (uninstall).
     */
    public function dropTable(Entity $entity): void
    {
        Schema::dropIfExists($entity->table_name);
    }

    /**
     * Drop a single field's physical column from an installed entity's
     * table — the irreversible half of removing a field post-install
     * (see EntityBuilderController::updateInstalled()). A Button field
     * never had a column to begin with, so this is a no-op for it.
     */
    public function dropColumn(Entity $entity, EntityField $field): void
    {
        if ($field->type->isAction()) {
            return;
        }

        $column = $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;

        Schema::table($entity->table_name, function (Blueprint $table) use ($field, $column) {
            if ($field->type === EntityFieldType::Relation) {
                try {
                    $table->dropForeign([$column]);
                } catch (Throwable) {
                    // No FK constraint to drop — either its target never
                    // resolved at creation time, or the driver (sqlite,
                    // in tests) doesn't support altering constraints.
                }
            }

            $table->dropColumn($column);
        });
    }

    private function addColumnToBlueprint(Blueprint $table, EntityField $field): void
    {
        match ($field->type) {
            EntityFieldType::Checkbox => $table->boolean($field->column_name)->default(false),
            EntityFieldType::String, EntityFieldType::Select => $table->string($field->column_name)->nullable(),
            EntityFieldType::IntegerNumber => $table->integer($field->column_name)->nullable(),
            EntityFieldType::DecimalNumber => $table->decimal($field->column_name, 15, 4)->nullable(),
            EntityFieldType::Textarea => $table->text($field->column_name)->nullable(),
            EntityFieldType::RichText => $table->longText($field->column_name)->nullable(),
            EntityFieldType::Relation => $this->addRelationColumn($table, $field),
            EntityFieldType::Date => $table->date($field->column_name)->nullable(),
            EntityFieldType::Time => $table->time($field->column_name)->nullable(),
            EntityFieldType::DateTime => $table->dateTime($field->column_name)->nullable(),
            EntityFieldType::ColorPicker => $table->string($field->column_name, 7)->nullable(),
            EntityFieldType::Code => $table->string($field->column_name)->nullable(),
            EntityFieldType::Button => null,
            EntityFieldType::Table => $table->json($field->column_name)->nullable(),
            EntityFieldType::ProductsBlock => $table->json($field->column_name)->nullable(),
        };
    }

    private function addRelationColumn(Blueprint $table, EntityField $field): void
    {
        $table->unsignedBigInteger("{$field->column_name}_id")->nullable();

        $targetTable = $this->resolveRelationTargetTable($field);

        if ($targetTable !== null) {
            $table->foreign("{$field->column_name}_id")->references('id')->on($targetTable)->nullOnDelete();
        }
    }

    /**
     * Resolve the real table a Relation field should get a foreign key
     * constraint against. Returns null (no DB-level constraint, just a
     * plain nullable id column) when the target can't be resolved to an
     * existing table — e.g. it points at an entity that isn't installed
     * yet, or a model class that no longer exists.
     */
    private function resolveRelationTargetTable(EntityField $field): ?string
    {
        if ($field->relation_target_type === EntityRelationTargetType::Entity) {
            return Entity::where('slug', $field->relation_target)
                ->where('is_installed', true)
                ->value('table_name');
        }

        if ($field->relation_target_type === EntityRelationTargetType::Model) {
            $class = $field->relation_target;

            if (! is_string($class) || ! class_exists($class)) {
                return null;
            }

            return (new $class)->getTable();
        }

        return null;
    }
}
