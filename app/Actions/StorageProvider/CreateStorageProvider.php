<?php

namespace App\Actions\StorageProvider;

use App\Models\StorageProvider;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateStorageProvider
{
    public function create(User $user, array $input, ?int $projectId): StorageProvider
    {
        $this->validate($input);

        $storageProvider = new StorageProvider([
            'user_id' => $user->id,
            'provider' => $input['provider'],
            'profile' => $input['name'],
            'project_id' => $projectId,
        ]);

        $provider = $storageProvider->provider();
        $storageProvider->credentials = $provider->credentialData($input);

        try {
            $connected = $provider->connect($storageProvider->credentials);
        } catch (Throwable $e) {
            Log::error('Failed to connect to storage provider', [
                'provider' => $storageProvider->provider,
                'exception' => get_class($e),
            ]);

            $connected = false;
        }

        if (! $connected) {
            throw ValidationException::withMessages([
                'provider' => $this->connectionError($provider),
            ]);
        }

        $storageProvider->save();

        return $storageProvider;
    }

    private function validate(array $input): void
    {
        $rules = [
            'provider' => [
                'required',
                Rule::in(array_keys(config('storage-provider.providers'))),
            ],
            'name' => [
                'required',
            ],
        ];

        if (isset($input['provider']) && config('storage-provider.providers.'.$input['provider'].'.handler')) {
            $provider = (new StorageProvider(['provider' => $input['provider']]))->provider();
            $rules = array_merge($rules, $provider->validationRules());
        }

        Validator::make($input, $rules)->validate();
    }

    private function connectionError(object $provider): string
    {
        if (method_exists($provider, 'connectionError') && is_string($provider->connectionError())) {
            return $provider->connectionError();
        }

        return __("Couldn't connect to the provider");
    }
}
