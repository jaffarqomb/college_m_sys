<?php

namespace App\Filament\Admin\Resources\Instructors\Pages;

use App\Filament\Admin\Resources\Instructors\InstructorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstructor extends CreateRecord
{
    protected static string $resource = InstructorResource::class;
}
