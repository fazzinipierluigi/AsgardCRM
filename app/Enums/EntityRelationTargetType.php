<?php

namespace App\Enums;

enum EntityRelationTargetType: string
{
    case Entity = 'entity';
    case Model = 'model';
}
