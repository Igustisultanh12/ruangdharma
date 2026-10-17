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
    public function test_script_generator_canang_sari(): void {
        $svc = new \App\Services\ScriptGeneratorService();
        $r = $svc->generate('Canang Sari', 60, 'narasi');
        $this->assertStringContainsString('Iswara', json_encode($r));
        $this->assertStringContainsString('Om Santi Santi Santi Om', $r['caption']);
    }
}
