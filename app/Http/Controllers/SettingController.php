<?php
namespace App\Http\Controllers;
use App\Models\Setting;
use Illuminate\Http\Request;
class SettingController extends Controller {
    public function index() {
        return response()->json(['daily_limit'=> (int) Setting::getValue('daily_limit','5'),'blocked_keywords'=> json_decode(Setting::getValue('blocked_keywords','[]'), true),'app_name'=> Setting::getValue('app_name','Ruang Dharma Studio')]);
    }
    public function update(Request $request) {
        $data = $request->validate(['daily_limit'=>'sometimes|integer|min:1|max:100','blocked_keywords'=>'sometimes|array','blocked_keywords.*'=>'string','app_name'=>'sometimes|string|max:100']);
        if (isset($data['daily_limit'])) Setting::setValue('daily_limit', (string)$data['daily_limit']);
        if (isset($data['blocked_keywords'])) Setting::setValue('blocked_keywords', json_encode($data['blocked_keywords']));
        if (isset($data['app_name'])) Setting::setValue('app_name', $data['app_name']);
        return response()->json(['message'=>'Pengaturan tersimpan.','settings'=>['daily_limit'=>(int)Setting::getValue('daily_limit'),'blocked_keywords'=>json_decode(Setting::getValue('blocked_keywords','[]'),true)]]);
    }
}
