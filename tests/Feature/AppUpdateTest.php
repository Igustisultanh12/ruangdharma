<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@ruangdharma.id')->first();
    }

    public function test_mobile_app_update_endpoint_is_accessible_and_returns_json(): void
    {
        $res = $this->getJson('/api/mobile/app-update');
        $res->assertOk();
        $res->assertJsonStructure(['status', 'available']);
    }

    public function test_mobile_app_update_returns_configured_release_data(): void
    {
        $sha = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
        Setting::set('app_update_meta', [
            'version' => '1.3.0',
            'version_code' => 6,
            'notes' => 'Pembaruan otomatis rilis',
            'size' => 19500000,
            'sha256' => $sha,
            'path' => null,
            'download_url' => 'https://github.com/Igustisultanh12/ruangdharma/releases/download/v1.3.0/RUANGDHARMA-1.3.0.apk',
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => 'CI',
            'notified' => 0,
        ]);

        $res = $this->getJson('/api/mobile/app-update');
        $res->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('version', '1.3.0')
            ->assertJsonPath('version_code', 6)
            ->assertJsonPath('sha256', $sha);

        $dl = $this->get('/api/mobile/app-update/download');
        $dl->assertRedirect('https://github.com/Igustisultanh12/ruangdharma/releases/download/v1.3.0/RUANGDHARMA-1.3.0.apk');
    }

    public function test_admin_app_update_requires_admin(): void
    {
        $this->getJson('/api/admin/app-update')->assertUnauthorized();

        Sanctum::actingAs($this->admin());
        $res = $this->getJson('/api/admin/app-update');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['upload_limit_bytes', 'push_ready', 'github_repo']]);
    }
}
