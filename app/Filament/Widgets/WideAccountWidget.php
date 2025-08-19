<?php

namespace App\Filament\Widgets;

use Filament\Notifications\Notification;
use Filament\Widgets\AccountWidget as BaseAccountWidget;

class WideAccountWidget extends BaseAccountWidget
{
    protected static ?string $heading = 'Account';
    protected static string $view = 'filament.widgets.wide-account-widget';

    // full-width in header
    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    /** Shown once after generation */
    public ?string $token = null;

    public function issueToken(): void
    {
        $user = auth()->user();

        // requires Laravel Sanctum's HasApiTokens on User
        $this->token = $user->createToken('Resepedia API')->plainTextToken;

        Notification::make()
            ->title('API token created')
            ->body('Copy the token shown below. It will be shown only once.')
            ->success()
            ->send();
    }

    public function revokeAllTokens(): void
    {
        auth()->user()->tokens()->delete();
        $this->token = null;

        Notification::make()
            ->title('All API tokens revoked')
            ->success()
            ->send();
    }
}
