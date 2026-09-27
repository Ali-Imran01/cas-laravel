<?php

namespace App\Http\Requests\Apps;

use App\Domain\Apps\Enums\AppEnvironment;
use App\Domain\Apps\Models\Application;
use App\Domain\Apps\Rules\RedirectUri;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (no route {app}) and update (route {app}). */
class SaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('app') ? 'update' : 'create', Application::class);
    }

    protected function prepareForValidation(): void
    {
        // The form sends redirect URIs as one per line.
        $uris = $this->input('redirect_uris');
        if (is_string($uris)) {
            $uris = array_values(array_filter(array_map('trim', preg_split('/\R/', $uris) ?: [])));
        }

        $this->merge(['redirect_uris' => $uris, 'code' => strtolower(trim((string) $this->input('code')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $app = $this->route('app');
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'environment' => ['required', Rule::enum(AppEnvironment::class)],
            'homepage_url' => ['nullable', 'url:http,https', 'max:255'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'allowed_scopes' => ['required', 'array', 'min:1'],
            'allowed_scopes.*' => ['string', 'distinct', Rule::in(array_keys(config('cas.sso.scopes')))],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:'.config('cas.sso.max_redirect_uris')],
            'redirect_uris.*' => ['string', 'distinct', new RedirectUri],
        ];

        // The code is a stable identifier used in logs and claims, so it is set once at registration.
        return $app ? $rules : $rules + [
            'code' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{1,39}$/', Rule::unique('applications', 'code')],
        ];
    }
}
