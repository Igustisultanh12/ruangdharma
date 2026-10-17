<?php
namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;
    public function definition(): array
    {
        return [
            'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10), 'role' => 'pengguna', 'is_active' => true,
            'avatar' => null, 'daily_limit' => null, 'default_durasi' => 60, 'default_model' => 'narasi',
            'permissions' => null, 'quota_plan_id' => null,
        ];
    }
    public function admin(): static { return $this->state(fn() => ['role' => 'admin']); }
    public function superAdmin(): static { return $this->state(fn() => ['role' => 'super_admin']); }
    public function editor(): static { return $this->state(fn() => ['role' => 'editor']); }
    public function inactive(): static { return $this->state(fn() => ['is_active' => false]); }
}
