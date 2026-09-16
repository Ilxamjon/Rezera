<?php

namespace App\Filament\Pages\Auth;

use App\Support\Phone\PhoneNormalizer;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Phone')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        try {
            $phone = PhoneNormalizer::normalize((string) ($data['phone'] ?? ''));
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'data.phone' => __('auth.invalid_credentials'),
            ]);
        }

        return [
            'phone' => $phone,
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('auth.invalid_credentials'),
        ]);
    }
}
