<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\MuseJob;
use App\Services\MuseService;
use App\Services\QuotaService;
use App\Services\ScriptGeneratorService;
use App\Models\User;
use Illuminate\Http\Request;
class MuseController extends Controller {
    protected function authorize(Request $request, MuseService $muse): ?\Illuminate\Http\JsonResponse {
        $tokenHeader = $request->header('X-Muse-Token');
        $bearer = $request->bearerToken();
        if (!$muse->verifyToken($tokenHeader, $bearer)) return response()->json(['message'=>'Unauthorized. Header X-Muse-Token tidak valid.'], 401);
        return null;
    }
    public function health() {
        try { \Illuminate\Support\Facades\DB::connection()->getPdo(); $db='ok'; } catch (\Throwable $e) { $db='error'; }
        return response()->json(['status'=>'ok','service'=>'ruang-dharma-studio','time'=>now()->toIso8601String(),'db'=>$db,'version'=>'1.0.0']);
    }
    public function status(Request $request, MuseService $muse) {
        if ($r = $this->authorize($request, $muse)) return $r;
        return response()->json($muse->status());
    }
    public function generate(Request $request, MuseService $muse, QuotaService $quota, ScriptGeneratorService $gen) {
        if ($r = $this->authorize($request, $muse)) return $r;
        $data = $request->validate(['keyword'=>'required|string|max:100','durasi'=>'sometimes|in:30,60,90','model'=>'sometimes|in:narasi,ngobrol,gabungan','user_id'=>'sometimes|nullable|integer|exists:users,id','callback_url'=>'sometimes|nullable|url']);
        $durasi = (int)($data['durasi'] ?? 60); $model = $data['model'] ?? 'narasi';
        if ($blocked = $quota->isBlocked($data['keyword'])) return response()->json(['message'=>"Kata kunci '{$blocked}' diblokir."], 422);
        $user = isset($data['user_id']) ? User::find($data['user_id']) : null;
        if ($user) { $check = $quota->check($user, $data['keyword']); if (!$check['ok']) return response()->json(['message'=>$check['message']], $check['code']); }
        $job = $muse->createJob($user?->id, $data['keyword'], $durasi, $model, $data['callback_url'] ?? null);
        // Jalankan generator lokal langsung (sinkron) agar Muse langsung dapat hasil; di produksi bisa dispatch job queue.
        try {
            $result = $gen->generate($data['keyword'], $durasi, $model);
            $muse->completeJob($job, $result);
            return response()->json(['job_id'=>$job->id,'status'=>'completed','keyword'=>$job->keyword,'durasi'=>$durasi,'model'=>$model,'result'=>$result,'estimated_seconds'=>45], 201);
        } catch (\Throwable $e) {
            $muse->failJob($job, $e->getMessage());
            return response()->json(['job_id'=>$job->id,'status'=>'failed','message'=>$e->getMessage()], 500);
        }
    }
    public function showJob(Request $request, $id, MuseService $muse) {
        if ($r = $this->authorize($request, $muse)) return $r;
        $job = MuseJob::findOrFail($id);
        return response()->json($job);
    }
    public function webhook(Request $request, MuseService $muse) {
        if ($r = $this->authorize($request, $muse)) return $r;
        $data = $request->validate(['job_id'=>'required|integer|exists:muse_jobs,id','status'=>'required|in:completed,failed,processing','result'=>'sometimes|array','error'=>'sometimes|string']);
        $job = MuseJob::findOrFail($data['job_id']);
        if ($data['status']==='completed') $muse->completeJob($job, $data['result'] ?? []);
        elseif ($data['status']==='failed') $muse->failJob($job, $data['error'] ?? 'Gagal dari Muse');
        else $job->update(['status'=>'processing']);
        return response()->json(['ok'=>true,'job_id'=>$job->id,'status'=>$job->fresh()->status]);
    }
}
