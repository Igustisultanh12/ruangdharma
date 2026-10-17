<?php
namespace Database\Seeders;
use App\Models\Permission;
use App\Models\QuotaPlan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Quota Plans ----------
        $free = QuotaPlan::firstOrCreate(['name' => 'Free'], ['daily_limit' => 5, 'price' => 0, 'description' => 'Paket gratis 5 video/hari.', 'is_active' => true]);
        $pro = QuotaPlan::firstOrCreate(['name' => 'Pro'], ['daily_limit' => 20, 'price' => 50000, 'description' => 'Paket Pro 20 video/hari.', 'is_active' => true]);
        QuotaPlan::firstOrCreate(['name' => 'Unlimited'], ['daily_limit' => 10000, 'price' => 150000, 'description' => 'Paket tanpa batas praktis.', 'is_active' => true]);

        // ---------- Roles & Permissions ----------
        $permDefs = [
            ['name' => 'generate.create', 'group' => 'generate', 'display_name' => 'Buat video'],
            ['name' => 'generate.review', 'group' => 'generate', 'display_name' => 'Review / approve video'],
            ['name' => 'generate.publish', 'group' => 'generate', 'display_name' => 'Publish video'],
            ['name' => 'users.manage', 'group' => 'users', 'display_name' => 'Kelola pengguna'],
            ['name' => 'settings.manage', 'group' => 'settings', 'display_name' => 'Kelola pengaturan'],
            ['name' => 'roles.manage', 'group' => 'roles', 'display_name' => 'Kelola role & permission'],
            ['name' => 'analytics.view', 'group' => 'analytics', 'display_name' => 'Lihat analitik'],
            ['name' => 'media.manage', 'group' => 'media', 'display_name' => 'Kelola media'],
        ];
        foreach ($permDefs as $p) Permission::firstOrCreate(['name' => $p['name']], $p);
        $allPermIds = Permission::pluck('id')->all();

        $roles = [
            'super_admin' => ['display_name' => 'Super Admin', 'permissions' => $allPermIds],
            'admin' => ['display_name' => 'Admin', 'permissions' => $allPermIds],
            'editor' => ['display_name' => 'Editor', 'permissions' => Permission::whereIn('name', ['generate.create', 'generate.review', 'generate.publish', 'media.manage'])->pluck('id')->all()],
            'pengguna' => ['display_name' => 'Pengguna', 'permissions' => Permission::whereIn('name', ['generate.create'])->pluck('id')->all()],
            'viewer' => ['display_name' => 'Viewer', 'permissions' => []],
        ];
        foreach ($roles as $name => $def) {
            $role = Role::firstOrCreate(['name' => $name], ['display_name' => $def['display_name']]);
            $role->permissions()->sync($def['permissions']);
        }

        // ---------- Users ----------
        $admin = User::firstOrCreate(['email' => 'admin@ruangdharma.id'], [
            'name' => 'Admin Ruang Dharma', 'password' => Hash::make('admin123'), 'role' => 'admin',
            'is_active' => true, 'default_durasi' => 60, 'default_model' => 'narasi', 'quota_plan_id' => $pro->id,
        ]);
        $admin->assignRole('admin');
        $user = User::firstOrCreate(['email' => 'user@ruangdharma.id'], [
            'name' => 'Pengguna Demo', 'password' => Hash::make('user123'), 'role' => 'pengguna',
            'is_active' => true, 'default_durasi' => 60, 'default_model' => 'narasi', 'quota_plan_id' => $free->id,
        ]);
        $user->assignRole('pengguna');

        // ---------- Settings ----------
        Setting::set('daily_limit', '5');
        Setting::set('blocked_keywords', ['politik', 'judi', 'pornografi', 'kekerasan']);
        Setting::set('app_name', 'Ruang Dharma Studio');
        Setting::set('app_logo', '');
        Setting::set('app_description', 'Studio pembuat video cerita Hindu untuk anak.');
        Setting::set('notification_templates', []);
        Setting::set('password_min_length', '6');
        Setting::set('session_timeout', '120');
        Setting::set('auto_generate_enabled', '0');
        Setting::set('auto_generate_themes', ['Rwa Bhineda', 'Canang Sari', 'Tri Sandhya']);
        Setting::set('auto_generate_time', '07:00');
        Setting::set('muse_base_url', '');
        Setting::set('muse_timeout', '15');
        // muse_api_token & muse_webhook_secret TIDAK di-seed — admin generate lewat menu Admin > Integrasi Muse.
    }
}
