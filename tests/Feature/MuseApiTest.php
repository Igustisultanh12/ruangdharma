<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class MuseApiTest extends TestCase {
    use RefreshDatabase;
    public function test_health_ok(): void { $this->getJson('/api/muse/health')->assertOk()->assertJsonPath('status','ok'); }
    public function test_muse_generate_needs_token(): void { $this->postJson('/api/muse/generate', ['keyword'=>'Rwa Bhineda'])->assertStatus(401); }
    public function test_muse_generate_with_token(): void {
        // Fallback .env dibaca lewat config('services.muse.token') — tetap jalan setelah config:cache.
        config(['services.muse.token' => 'test-token-12345678901234567890']);
        $this->postJson('/api/muse/generate', ['keyword'=>'Rwa Bhineda','durasi'=>60,'model'=>'narasi'], ['X-Muse-Token'=>'test-token-12345678901234567890'])
            ->assertStatus(201)->assertJsonPath('status','completed');
        $this->getJson('/api/muse/status', ['Authorization'=>'Bearer test-token-12345678901234567890'])->assertOk();
    }
    public function test_generate_respects_blocked_keyword(): void {
        $user = User::factory()->create();
        \App\Models\Setting::setValue('blocked_keywords', json_encode(['politik']));
        $this->actingAs($user)->postJson('/api/generations', ['keyword'=>'politik hari ini','durasi'=>60,'model'=>'narasi'])->assertStatus(422);
    }
    public function test_muse_profiles_list_for_user_has_no_token(): void {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/muse-profiles')->assertOk()
            ->assertJsonStructure(['success', 'data']);
        // Daftar profil TIDAK BOLEH mengandung token/secret utuh.
        $this->assertStringNotContainsString('test-profile-token', (string) $this->actingAs($user)->getJson('/api/muse-profiles')->getContent());
    }
    public function test_profile_token_authenticates_pending_jobs(): void {
        \App\Models\MuseProfile::create(['name' => 'Muse Kedua', 'slug' => 'muse-kedua', 'api_token' => 'test-profile-token-1234567890', 'webhook_secret' => 'test-profile-secret-1234567890', 'is_active' => true, 'is_default' => false]);
        $this->getJson('/api/muse/jobs/pending', ['X-Muse-Token' => 'test-profile-token-1234567890'])->assertOk();
        $this->getJson('/api/muse/jobs/pending', ['X-Muse-Token' => 'token-salah-12345678901234567890'])->assertStatus(401);
    }
    public function test_generate_assigns_muse_profile(): void {
        $user = User::factory()->create();
        $profile = \App\Models\MuseProfile::create(['name' => 'Muse Kedua', 'slug' => 'muse-kedua', 'api_token' => 'test-profile-token-1234567890', 'webhook_secret' => 'test-profile-secret-1234567890', 'is_active' => true, 'is_default' => false]);
        $this->actingAs($user)->postJson('/api/generations', ['keyword' => 'Rwa Bhineda', 'durasi' => 60, 'model' => 'narasi', 'via_muse' => true, 'muse_profile_id' => $profile->id])
            ->assertStatus(201)->assertJsonPath('muse_profile_id', $profile->id);
        $this->assertDatabaseHas('muse_jobs', ['muse_profile_id' => $profile->id]);
    }
    public function test_generate_rejects_inactive_muse_profile(): void {
        $user = User::factory()->create();
        $profile = \App\Models\MuseProfile::create(['name' => 'Muse Nonaktif', 'slug' => 'muse-nonaktif', 'api_token' => 'test-profile-token-1234567890', 'webhook_secret' => 'test-profile-secret-1234567890', 'is_active' => false, 'is_default' => false]);
        $this->actingAs($user)->postJson('/api/generations', ['keyword' => 'Rwa Bhineda', 'durasi' => 60, 'model' => 'narasi', 'via_muse' => true, 'muse_profile_id' => $profile->id])
            ->assertStatus(422);
    }
    public function test_script_generator_canang_sari(): void {
        $svc = new \App\Services\ScriptGeneratorService();
        $r = $svc->generate('Canang Sari', 60, 'narasi');
        $this->assertStringContainsString('Iswara', json_encode($r));
        $this->assertStringContainsString('Om Santi Santi Santi Om', $r['caption']);
    }
}
