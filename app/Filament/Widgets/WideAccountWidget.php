<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget as BaseAccountWidget;

class WideAccountWidget extends BaseAccountWidget
{
    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }
}
