<?php

declare(strict_types=1);

namespace Uptms\Permissions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Uptms\Permissions\Models\Role;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');
        $ignore = $role instanceof Role ? $role->id : null;

        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                $ignore === null ? 'required' : 'prohibited',
                'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'slug')->ignore($ignore),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_two_factor' => ['required', 'boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'key')],
        ];
    }
}
