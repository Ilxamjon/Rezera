<?php

namespace App\Filament\Resources;

use App\Actions\Platform\ChangeUserStatusAction;
use App\Domain\Identity\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Platform';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->disabled(),
            Forms\Components\TextInput::make('phone')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('platform_role')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('platform_role')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(UserStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->value]
                    )->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('block')
                    ->color('danger')
                    ->visible(fn (User $record) => $record->status !== UserStatus::Blocked)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        app(ChangeUserStatusAction::class)->execute(
                            $record,
                            UserStatus::Blocked,
                            auth()->user(),
                            'Blocked from Filament',
                            request(),
                        );
                        Notification::make()->title('User blocked')->danger()->send();
                    }),
                Tables\Actions\Action::make('activate')
                    ->color('success')
                    ->visible(fn (User $record) => $record->status !== UserStatus::Active)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        app(ChangeUserStatusAction::class)->execute(
                            $record,
                            UserStatus::Active,
                            auth()->user(),
                            'Activated from Filament',
                            request(),
                        );
                        Notification::make()->title('User activated')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
