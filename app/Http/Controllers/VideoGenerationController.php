<?php
namespace App\Http\Controllers;
use App\Models\VideoGeneration;
use App\Services\QuotaService;
use App\Services\ScriptGeneratorService;
use Illuminate\Http\Request;
class VideoGenerationController extends Controller {
    public function index(Request $request) {
        return response()->json($request->user()->generations()->latest()->paginate(20));
    }
    public function show(Request $request, $id) {
        $g = VideoGeneration::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($g);
    }
    public function quota(Request $request, QuotaService $quota) {
        $user = $request->user();
        return response()->json(['limit'=>$quota->userLimit($user),'used'=>$quota->usedToday($user),'remaining'=>$quota->remaining($user)]);
    }
    public function store(Request $request, ScriptGeneratorService $gen, QuotaService $quota) {
        $data = $request->validate(['keyword'=>'required|string|max:100','durasi'=>'required|in:30,60,90','model'=>'required|in:narasi,ngobrol,gabungan']);
        $check = $quota->check($request->user(), $data['keyword']);
        if (!$check['ok']) return response()->json(['message'=>$check['message']], $check['code']);
        $result = $gen->generate($data['keyword'], (int)$data['durasi'], $data['model']);
        $g = VideoGeneration::create(['user_id'=>$request->user()->id,'keyword'=>$data['keyword'],'durasi'=>(int)$data['durasi'],'model'=>$data['model'],'naskah'=>$result['naskah'],'caption'=>$result['caption'],'prompts'=>$result['prompts'],'status'=>'completed','source'=>'local']);
        return response()->json(['generation'=>$g,'result'=>$result,'quota_remaining'=>$quota->remaining($request->user())], 201);
    }
}
