<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored override of a platform setting. Read through App\Platform\Settings\Settings, never directly.
 *
 * @property string $key
 * @property string|null $value
 * @property int|null $updated_by
 */
#[Fillable(['key', 'value', 'updated_by'])]
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;
}
