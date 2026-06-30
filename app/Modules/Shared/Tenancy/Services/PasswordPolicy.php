<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Validator;

final readonly class PasswordPolicy
{
    private const array DEFAULT_RULES = [
        'min_length' => 8,
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => false,
    ];

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private ?Tenant $tenant = null,
    ) {
        $this->config = $this->loadConfig();
    }

    /**
     * @return array<int, string>
     */
    public function rules(): array
    {
        $config = $this->loadConfig();
        $rules = ['min:' . ($config['min_length'] ?? 8)];

        if ($config['require_mixed_case'] ?? true) {
            $rules[] = 'regex:/[a-z]/';
            $rules[] = 'regex:/[A-Z]/';
        }

        if ($config['require_numbers'] ?? true) {
            $rules[] = 'regex:/[0-9]/';
        }

        if ($config['require_symbols'] ?? false) {
            $rules[] = 'regex:/[!@#$%^&*(),.?":{}|<>]/';
        }

        return $rules;
    }

    public function validate(string $password): ?string
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => $this->rules()],
            $this->messages(),
        );

        if ($validator->fails()) {
            return $validator->errors()->first('password');
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'password.min' => __('Password must be at least :min characters.'),
            'password.regex' => __('Password must contain uppercase letters, lowercase letters, and numbers.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        if (! $this->tenant) {
            return self::DEFAULT_RULES;
        }

        $data = $this->tenant->getAttribute('data');
        $tenantConfig = is_array($data) ? ($data['password_policy'] ?? null) : null;

        return $tenantConfig ? array_merge(self::DEFAULT_RULES, $tenantConfig) : self::DEFAULT_RULES;
    }

    public static function default(): self
    {
        return new self;
    }
}
