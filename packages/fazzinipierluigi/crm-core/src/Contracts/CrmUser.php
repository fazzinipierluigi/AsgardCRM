<?php

namespace Fazzinipierluigi\CrmCore\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Marker contract implemented by the host application's own User model.
 * Bound in config('crm.user_model'); resolved via the container instead
 * of a hardcoded App\Models\User reference throughout the package.
 */
interface CrmUser extends Authenticatable {}
