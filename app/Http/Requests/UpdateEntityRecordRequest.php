<?php

namespace App\Http\Requests;

/**
 * Same per-entity field rules as creation — nothing about validation
 * changes between create and update for a record.
 */
class UpdateEntityRecordRequest extends StoreEntityRecordRequest {}
