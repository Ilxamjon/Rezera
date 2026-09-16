<?php

namespace App\Filament\Resources;

use App\Actions\Platform\ChangeBusinessStatusAction;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Filament\Resources\BusinessResource\Pages;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Platform';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->disabled(),
            Forms\Components\TextInput::make('phone')->disabled(),
            Forms\Components\TextInput::make('city')->disabled(),
            Forms\Components\Select::make('status')
                ->options(collect(BusinessStatus::cases())->mapWithKeys(
                    fn ($c) => [$c->value => $c->value]
                )->all())
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('city')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\IconColumn::make('is_publicly_listed')->boolean()->label('Listed'),
                Tables\Columns\TextColumn::make('verification_status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(BusinessStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->value]
                    )->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('suspend')
                    ->color('warning')
                    ->visible(fn (Business $record) => $record->status !== BusinessStatus::Suspended)
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (Business $record, array $data): void {
                        app(ChangeBusinessStatusAction::class)->execute(
                            $record,
                            BusinessStatus::Suspended,
                            auth()->user(),
                            $data['reason'],
                            request(),
                        );
                        Notification::make()->title('Business suspended')->warning()->send();
                    }),
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->visible(fn (Business $record) => $record->status !== BusinessStatus::Approved)
                    ->requiresConfirmation()
                    ->action(function (Business $record): void {
                        app(ChangeBusinessStatusAction::class)->execute(
                            $record,
                            BusinessStatus::Approved,
                            auth()->user(),
                            'Approved from Filament',
                            request(),
                        );
                        Notification::make()->title('Business approved')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinesses::route('/'),
        ];
    }
}
