<?php

namespace App\Filament\Resources;

use App\Actions\Businesses\ApproveBusinessVerificationAction;
use App\Actions\Businesses\RejectBusinessVerificationAction;
use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Filament\Resources\BusinessVerificationResource\Pages;
use App\Models\BusinessVerification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BusinessVerificationResource extends Resource
{
    protected static ?string $model = BusinessVerification::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Verifications';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('business.name')->disabled()->label('Business'),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\Textarea::make('rejection_reason')->disabled(),
            Forms\Components\Textarea::make('admin_notes')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['business', 'submittedBy']))
            ->columns([
                Tables\Columns\TextColumn::make('business.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('submittedBy.name')->label('Submitted by'),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(VerificationRequestStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->value]
                    )->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->visible(fn (BusinessVerification $record) => $record->isPending())
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (BusinessVerification $record): void {
                        app(ApproveBusinessVerificationAction::class)->execute(
                            $record,
                            auth()->user(),
                            request(),
                        );
                        Notification::make()->title('Approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->visible(fn (BusinessVerification $record) => $record->isPending())
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->maxLength(500),
                    ])
                    ->action(function (BusinessVerification $record, array $data): void {
                        app(RejectBusinessVerificationAction::class)->execute(
                            $record,
                            auth()->user(),
                            $data['reason'],
                            request(),
                        );
                        Notification::make()->title('Rejected')->warning()->send();
                    }),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinessVerifications::route('/'),
        ];
    }
}
