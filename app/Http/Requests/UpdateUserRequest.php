<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Validation\UserValidation;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return UserValidation::rules($user instanceof User ? $user : null);
    }
}
