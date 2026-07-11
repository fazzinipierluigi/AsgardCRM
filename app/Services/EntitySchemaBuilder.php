<?php

namespace App\Services;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use App\Models\Entity;
use App\Models\EntityField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
    public const RESERVED_COLUMN_NAMES = ['id', 'user_id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Create the entity's table with its base ownership columns plus
     * one column per currently-defined field.
     */
    public function create(Entity $entity): void
    {
        Schema::create($entity->table_name, function (Blueprint $table) use ($entity) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            foreach ($entity->allFields() as $field) {
                $this->addColumnToBlueprint($table, $field);
            }

            $table->timestamps();
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
