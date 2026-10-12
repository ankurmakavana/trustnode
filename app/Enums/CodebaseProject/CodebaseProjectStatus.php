<?php

namespace App\Enums\CodebaseProject;

enum CodebaseProjectStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
}
