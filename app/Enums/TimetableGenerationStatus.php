<?php

namespace App\Enums;

enum TimetableGenerationStatus: string
{
    case Generating = 'generating';

    case Draft = 'draft';

    case Published = 'published';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'Generating',
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }
}
