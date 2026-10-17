<?php
namespace App\Http\Controllers;
use App\Http\Requests\SettingUpdateRequest;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const TABS = [
        'general' => ['app_name', 'app_logo', 'app_description', 'daily_limit', 'blocked_keywords'],
        'muse' => ['muse_base_url', 'muse_timeout'],
        'auto_generate' => ['auto_generate_enabled', 'auto_generate_themes', 'auto_generate_time'],
        'security' => ['password_min_length', 'session_timeout'],
        'notifications' => ['notification_templates'],
    ];

    protected function allSettings(): array
    {
        return [
            'daily_limit' => Setting::getInt('daily_limit', 5),
            'blocked_keywords' => Setting::getArray('blocked_keywords', []),
            'app_name' => Setting::get('app_name', 'Ruang Dharma Studio'),
            'app_logo' => Setting::get('app_logo', ''),
            'app_description' => Setting::get('app_description', ''),
            'notification_templates' => Setting::getArray('notification_templates', []),
            'password_min_length' => Setting::getInt('password_min_length', 6),
            'session_timeout' => Setting::getInt('session_timeout', 120),
            'auto_generate_enabled' => Setting::getBool('auto_generate_enabled', false),
            'auto_generate_themes' => Setting::getArray('auto_generate_themes', ['Rwa Bhineda', 'Canang Sari', 'Tri Sandhya']),
            'auto_generate_time' => Setting::get('auto_generate_time', '07:00'),
            'muse_base_url' => Setting::get('muse_base_url', ''),
            'muse_timeout' => Setting::getInt('muse_timeout', 15),
        ];
    }

    public function index() { return response()->json(['success' => true, 'settings' => $this->allSettings()]); }
    public function getSettings() { return $this->index(); }

    public function update(SettingUpdateRequest $request) { return $this->updateSettings($request); }
    public function updateSettings(SettingUpdateRequest $request)
    {
        $data = $request->validated();
        foreach ($data as $key => $value) Setting::set($key, $value);
        ActivityLogger::fromRequest($request, 'setting.update', 'Admin memperbarui pengaturan: ' . implode(', ', array_keys($data)));
        return response()->json(['success' => true, 'message' => 'Pengaturan tersimpan.', 'settings' => $this->allSettings()]);
    }

    /** Pengaturan per tab: GET /api/admin/settings/{tab}. */
    public function getByTab(Request $request, string $tab)
    {
        $keys = self::TABS[$tab] ?? null;
        if (!$keys) return response()->json(['success' => false, 'message' => "Tab '{$tab}' tidak dikenal. Tab: " . implode(', ', array_keys(self::TABS))], 404);
        $all = $this->allSettings();
        return response()->json(['success' => true, 'tab' => $tab, 'settings' => array_intersect_key($all, array_flip($keys))]);
    }
    public function updateByTab(SettingUpdateRequest $request, string $tab)
    {
        $keys = self::TABS[$tab] ?? null;
        if (!$keys) return response()->json(['success' => false, 'message' => "Tab '{$tab}' tidak dikenal."], 404);
        $data = array_intersect_key($request->validated(), array_flip($keys));
        foreach ($data as $key => $value) Setting::set($key, $value);
        ActivityLogger::fromRequest($request, 'setting.update', "Admin memperbarui pengaturan tab {$tab}");
        return response()->json(['success' => true, 'message' => "Pengaturan tab {$tab} tersimpan.", 'settings' => $this->allSettings()]);
    }
}
