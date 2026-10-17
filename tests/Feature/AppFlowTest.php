<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LoginHistory;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoGeneration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User { return User::where('email', 'admin@ruangdharma.id')->first(); }
    protected function user(): User { return User::where('email', 'user@ruangdharma.id')->first(); }

    public function test_login_with_seeded_admin_returns_token_and_logs_history(): void
    {
        $res = $this->postJson('/api/login', ['email' => 'admin@ruangdharma.id', 'password' => 'admin123'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('user.is_admin', true);
        $this->assertNotEmpty($res->json('token'));
        $this->assertNotNull(LoginHistory::latest('id')->first()->created_at, 'created_at harus terisi otomatis');
        $this->assertNotNull(ActivityLog::latest('id')->first()->created_at);

        $this->withToken($res->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'admin@ruangdharma.id');
    }

    public function test_login_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/login', ['email' => 'admin@ruangdharma.id', 'password' => 'salah'])->assertStatus(422);
    }

    public function test_spa_shell_and_unknown_api_route(): void
    {
        $this->withoutVite();
        $this->get('/history')->assertOk()->assertSee('id="app"', false);
        $this->getJson('/api/tidak-ada')->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_generate_flow_quota_versions_comments_and_download(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/generations', ['keyword' => 'Canang Sari', 'durasi' => 30, 'model' => 'ngobrol'])->assertCreated();
        $id = $res->json('generation.id');
        $this->assertCount(6, $res->json('result.naskah'));
        $this->assertStringContainsString('Om Swastyastu', $res->json('result.naskah.0.teks'));
        $this->assertStringContainsString('Om Santi Santi Santi Om', $res->json('result.naskah.5.teks'));
        $this->getJson('/api/quota')->assertJsonPath('used', 1);

        // Edit naskah: kunci lain per bagian (suara, prompt_visual) harus tetap ada.
        $naskah = $res->json('result.naskah');
        $naskah[1]['teks'] = 'Teks baru hasil edit.';
        $this->putJson("/api/generations/{$id}/naskah", ['naskah' => $naskah, 'caption' => 'Caption baru'])->assertOk();
        $g = VideoGeneration::find($id);
        $this->assertSame('Teks baru hasil edit.', $g->naskah[1]['teks']);
        $this->assertArrayHasKey('prompt_visual', $g->naskah[1]);
        $this->assertArrayHasKey('suara', $g->naskah[1]);
        $versions = $this->getJson("/api/generations/{$id}/versions")->assertOk()->json('data.data');
        $this->assertCount(1, $versions);
        $this->assertNotNull($versions[0]['created_at']);
        $this->postJson("/api/generations/{$id}/versions/{$versions[0]['id']}/restore")->assertOk();
        $this->assertNotSame('Teks baru hasil edit.', VideoGeneration::find($id)->naskah[1]['teks']);

        $this->postJson("/api/generations/{$id}/comments", ['body' => 'Mantap'])->assertCreated();
        $this->getJson("/api/generations/{$id}/comments")->assertOk()->assertJsonPath('data.total', 1);

        $this->get("/api/generations/{$id}/download-txt")->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->postJson("/api/generations/{$id}/toggle-favorite")->assertOk()->assertJsonPath('data.is_favorite', true);
        $this->getJson('/api/generations/counts')->assertOk()->assertJsonPath('data.favorites', 1);
        $this->getJson('/api/generations?favorites=1')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_quota_limit_and_blocked_keyword(): void
    {
        $user = $this->user();
        $user->update(['quota_plan_id' => null, 'daily_limit' => 1]);
        Sanctum::actingAs($user);
        $this->postJson('/api/generations', ['keyword' => 'politik', 'durasi' => 60, 'model' => 'narasi'])->assertStatus(422);
        $this->postJson('/api/generations', ['keyword' => 'Karma', 'durasi' => 60, 'model' => 'narasi'])->assertCreated();
        $this->postJson('/api/generations', ['keyword' => 'Karma', 'durasi' => 60, 'model' => 'narasi'])->assertStatus(429);
    }

    public function test_review_workflow_notifies_and_restricts_access(): void
    {
        $user = $this->user();
        $g = VideoGeneration::factory()->create(['user_id' => $user->id]);
        $other = User::factory()->create();

        Sanctum::actingAs($other);
        $this->getJson("/api/generations/{$g->id}")->assertNotFound();
        $this->getJson("/api/generations/{$g->id}/comments")->assertNotFound();
        $this->getJson('/api/review-queue')->assertForbidden();

        Sanctum::actingAs($user);
        $this->postJson("/api/generations/{$g->id}/submit-review")->assertOk();
        $this->assertTrue(Notification::where('user_id', $this->admin()->id)->where('title', 'Naskah menunggu review')->exists());

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/review-queue')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson("/api/generations/{$g->id}")->assertOk();
        $this->postJson("/api/generations/{$g->id}/reject", [])->assertStatus(422);
        $this->postJson("/api/generations/{$g->id}/reject", ['review_note' => 'Perbaiki bagian 2'])->assertOk()->assertJsonPath('data.workflow_status', 'revisi');
        $this->postJson("/api/generations/{$g->id}/approve")->assertOk()->assertJsonPath('data.workflow_status', 'approved');
        $this->postJson("/api/generations/{$g->id}/publish")->assertOk()->assertJsonPath('data.workflow_status', 'published');
        $this->assertSame(3, Notification::where('user_id', $user->id)->count());
    }

    public function test_demoting_admin_removes_admin_access(): void
    {
        $other = User::factory()->create();
        $other->assignRole('admin');
        Sanctum::actingAs($this->admin());
        $this->putJson("/api/admin/users/{$other->id}", ['role' => 'pengguna'])->assertOk();
        $this->assertFalse($other->fresh()->isAdmin());
        $this->putJson("/api/admin/users/{$this->admin()->id}", ['is_active' => false])->assertStatus(422);

        Sanctum::actingAs($other->fresh());
        $this->getJson('/api/admin/stats')->assertForbidden();
    }

    public function test_admin_endpoints_respond(): void
    {
        Sanctum::actingAs($this->admin());
        VideoGeneration::factory()->count(3)->create();
        $this->getJson('/api/admin/stats')->assertOk()->assertJsonCount(7, 'last_7_days');
        $this->getJson('/api/admin/analytics?days=14')->assertOk()->assertJsonCount(14, 'data.per_day');
        $this->getJson('/api/admin/users')->assertOk();
        $this->getJson('/api/admin/history')->assertOk()->assertJsonPath('data.total', 3);
        $this->getJson('/api/admin/activity-logs')->assertOk();
        $this->getJson('/api/admin/login-histories')->assertOk();
        $this->getJson('/api/admin/settings')->assertOk();
        $this->putJson('/api/admin/settings/general', ['daily_limit' => 7, 'blocked_keywords' => ['judi']])->assertOk();
        $this->assertSame(7, Setting::getInt('daily_limit'));
        $this->getJson('/api/admin/roles')->assertOk();
        $this->getJson('/api/admin/permissions')->assertOk();
        $this->getJson('/api/admin/quota-plans')->assertOk()->assertJsonCount(3, 'data');
        $this->get('/api/admin/export/csv')->assertOk();
        $this->get('/api/admin/export/pdf')->assertOk();
        $this->getJson('/api/admin/muse-config')->assertOk()->assertJsonPath('has_token', false);
        $token = $this->postJson('/api/admin/muse-config/generate-token')->assertCreated()->json('muse_api_token');
        $this->getJson('/api/muse/status', ['X-Muse-Token' => $token])->assertOk()->assertJsonPath('connected', true);
        $this->postJson('/api/register', ['name' => 'Baru', 'email' => 'baru@x.id', 'password' => 'rahasia1', 'password_confirmation' => 'rahasia1', 'role' => 'editor'])->assertCreated();
        $this->assertTrue(User::where('email', 'baru@x.id')->first()->hasRole('editor'));
    }

    public function test_realtime_pulse_changes_when_data_changes(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $a = $this->getJson('/api/realtime/pulse')->assertOk()->assertJsonStructure(['stamps' => ['generations', 'notifications', 'me'], 'quota', 'unread_notifications'])->json();
        $this->assertArrayNotHasKey('users', $a['stamps']);
        $this->postJson('/api/generations', ['keyword' => 'Karma', 'durasi' => 60, 'model' => 'narasi'])->assertCreated();
        $b = $this->getJson('/api/realtime/pulse')->json();
        $this->assertNotSame($a['stamps']['generations'], $b['stamps']['generations']);
        $this->assertNotSame($a['stamps']['notifications'], $b['stamps']['notifications']);
        $this->assertSame(1, $b['unread_notifications']);
        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->assertSame(0, $this->getJson('/api/realtime/pulse')->json('unread_notifications'));

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/realtime/pulse')->assertOk()->assertJsonStructure(['stamps' => ['users', 'activity', 'review', 'all_generations']]);
    }

    public function test_muse_webhook_accepts_hmac_signature(): void
    {
        Sanctum::actingAs($this->admin());
        $token = $this->postJson('/api/admin/muse-config/generate-token')->json('muse_api_token');
        $secret = $this->postJson('/api/admin/muse-config/generate-webhook-secret')->json('muse_webhook_secret');
        $job = $this->postJson('/api/muse/generate', ['keyword' => 'Nyepi', 'user_id' => $this->user()->id], ['X-Muse-Token' => $token])->assertCreated()->json('job_id');

        $body = json_encode(['job_id' => $job, 'status' => 'processing']);
        $this->call('POST', '/api/muse/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_MUSE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, $secret)], $body)->assertOk();
        $this->call('POST', '/api/muse/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_MUSE_SIGNATURE' => 'salah'], $body)->assertUnauthorized();
    }

    public function test_profile_update_and_password_change(): void
    {
        Sanctum::actingAs($this->user());
        $this->postJson('/api/profile', ['name' => 'Nama Baru', 'default_durasi' => 90, 'default_model' => 'gabungan'])->assertOk()->assertJsonPath('user.default_durasi', 90);
        $this->postJson('/api/profile/password', ['current_password' => 'salah', 'password' => 'baru1234', 'password_confirmation' => 'baru1234'])->assertStatus(422);
        $this->postJson('/api/profile/password', ['current_password' => 'user123', 'password' => 'baru1234', 'password_confirmation' => 'baru1234'])->assertOk();
    }

    public function test_script_generator_duration_changes_length(): void
    {
        $svc = new \App\Services\ScriptGeneratorService();
        $len = fn (int $d) => mb_strlen(implode(' ', array_column($svc->generate('Karma', $d, 'narasi')['naskah'], 'teks')));
        $this->assertLessThan($len(60), $len(30));
        $this->assertGreaterThan($len(60), $len(90));
        foreach ([30, 60, 90] as $d) {
            foreach (['narasi', 'ngobrol', 'gabungan'] as $m) {
                $r = $svc->generate('Tri Sandhya', $d, $m);
                $this->assertCount(6, $r['naskah']);
                $this->assertStringContainsString('Om Swastyastu', $r['naskah'][0]['teks'].$r['naskah'][1]['teks']);
                $this->assertStringEndsWith('Om Santi Santi Santi Om.', $r['naskah'][5]['teks']);
            }
        }
    }

    public function test_daily_command_runs(): void
    {
        Setting::set('auto_generate_enabled', '1');
        $this->artisan('app:generate-daily')->assertSuccessful();
        $this->assertSame(3, VideoGeneration::where('source', 'auto_daily')->count());
    }

    public function test_media_api_keys_and_webhooks(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200)]);
        $user = $this->user();
        Sanctum::actingAs($user);

        $media = $this->post('/api/media', ['title' => 'Canang', 'file' => \Illuminate\Http\UploadedFile::fake()->image('canang.png', 400, 400), 'tags' => ['upacara']], ['Accept' => 'application/json'])
            ->assertCreated()->json('data');
        $this->getJson('/api/media?tag=upacara')->assertOk()->assertJsonPath('data.total', 1);
        $this->deleteJson("/api/media/{$media['id']}")->assertOk();

        $key = $this->postJson('/api/api-keys', ['name' => 'Dashboard'])->assertCreated()->json('data.key');
        $this->postJson('/api/generations', ['keyword' => 'Karma', 'durasi' => 60, 'model' => 'narasi'])->assertCreated();
        $this->getJson('/api/external/generations', ['X-Api-Key' => $key])->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/external/generations', ['X-Api-Key' => 'salah'])->assertUnauthorized();

        $hook = $this->postJson('/api/webhooks', ['url' => 'https://contoh.test/hook', 'events' => ['generation.completed']])->assertCreated()->json('data.id');
        $this->postJson("/api/webhooks/{$hook}/test")->assertOk()->assertJsonPath('data.status_code', 200);
        // (event generation.completed dikirim setelah respons; di test callback terminating bisa ikut terpanggil)
        $this->assertGreaterThanOrEqual(1, $this->getJson("/api/webhooks/{$hook}/logs")->assertOk()->json('data.total'));
        $this->getJson('/api/app-info')->assertOk()->assertJsonPath('data.app_name', 'Ruang Dharma Studio');
    }

    public function test_generate_via_muse_async_then_webhook_completes(): void
    {
        Sanctum::actingAs($this->admin());
        $token = $this->postJson('/api/admin/muse-config/generate-token')->json('muse_api_token');

        Sanctum::actingAs($this->user());
        $res = $this->postJson('/api/generations', ['keyword' => 'Nyepi', 'durasi' => 60, 'model' => 'narasi', 'via_muse' => true])->assertCreated();
        $gid = $res->json('generation.id');
        $jobId = $res->json('muse_job_id');
        $this->assertSame('waiting_muse', $res->json('generation.status'));

        $this->getJson('/api/muse/jobs/pending', ['X-Muse-Token' => $token])->assertOk()->assertJsonPath('count', 1);
        $this->postJson('/api/muse/webhook', ['job_id' => $jobId, 'status' => 'processing'], ['X-Muse-Token' => $token])->assertOk();
        $this->assertSame('processing', VideoGeneration::find($gid)->status);
        $this->postJson('/api/muse/webhook', ['job_id' => $jobId, 'status' => 'completed', 'result' => ['video_url' => 'https://cdn.test/nyepi.mp4']], ['X-Muse-Token' => $token])->assertOk();

        $g = VideoGeneration::find($gid);
        $this->assertSame('completed', $g->status);
        $this->assertSame('https://cdn.test/nyepi.mp4', $g->video_url);
        $this->assertCount(6, $g->naskah, 'naskah dilengkapi generator lokal bila Muse hanya kirim video');
        $this->assertTrue(Notification::where('user_id', $this->user()->id)->where('title', 'Video via Muse selesai')->exists());
        $this->assertSame(1, VideoGeneration::where('muse_job_id', $jobId)->count());
    }

    public function test_muse_upload_video_links_to_generation(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Sanctum::actingAs($this->admin());
        $token = $this->postJson('/api/admin/muse-config/generate-token')->json('muse_api_token');
        Sanctum::actingAs($this->user());
        $res = $this->postJson('/api/generations', ['keyword' => 'Karma', 'durasi' => 60, 'model' => 'narasi', 'via_muse' => true])->assertCreated();

        $file = \Illuminate\Http\UploadedFile::fake()->create('karma.mp4', 2048, 'video/mp4');
        $this->post('/api/muse/upload-video', ['file' => $file, 'job_id' => $res->json('muse_job_id')], ['Accept' => 'application/json'])->assertUnauthorized();
        $up = $this->post('/api/muse/upload-video', ['file' => $file, 'job_id' => $res->json('muse_job_id')], ['Accept' => 'application/json', 'X-Muse-Token' => $token])->assertCreated();

        $g = VideoGeneration::find($res->json('generation.id'));
        $this->assertStringStartsWith('/storage/videos/karma-job', $g->video_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($up->json('file_path'));
        $this->assertTrue(\App\Models\MediaAsset::where('user_id', $this->user()->id)->where('type', 'video')->exists());
    }
}
