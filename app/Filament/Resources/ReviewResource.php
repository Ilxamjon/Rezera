<?php

namespace App\Filament\Resources;

use App\Actions\Reviews\ModerateReviewAction;
use App\Domain\Reviews\Enums\ReviewStatus;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Moderation';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('rating')->disabled(),
            Forms\Components\Textarea::make('body')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['business', 'user']))
            ->columns([
                Tables\Columns\TextColumn::make('business.name')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Author'),
                Tables\Columns\TextColumn::make('rating'),
                Tables\Columns\TextColumn::make('body')->limit(40),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->value]
                    )->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('hide')
                    ->color('warning')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Hidden)
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (Review $record, array $data): void {
                        app(ModerateReviewAction::class)->updateStatus(
                            auth()->user(),
                            $record,
                            ReviewStatus::Hidden,
                            $data['reason'],
                            request(),
                        );
                        Notification::make()->title('Review hidden')->warning()->send();
                    }),
                Tables\Actions\Action::make('publish')
                    ->color('success')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::Published)
                    ->requiresConfirmation()
                    ->action(function (Review $record): void {
                        app(ModerateReviewAction::class)->updateStatus(
                            auth()->user(),
                            $record,
                            ReviewStatus::Published,
                            null,
                            request(),
                        );
                        Notification::make()->title('Review published')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
        ];
    }
}
