<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable log of every version the app has moved to, each stamped
 * with the migrations `batch` number in effect at that point — lets the
 * update wizard compute exactly how many batches to roll back when
 * downgrading to a previously-installed version.
 */
#[Fillable(['version', 'migrations_batch'])]
class VersionHistory extends Model
{
    const UPDATED_AT = null;

    protected $table = 'version_history';
}
