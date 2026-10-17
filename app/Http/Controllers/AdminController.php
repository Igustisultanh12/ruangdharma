<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\VideoGeneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminController extends Controller {
    public function stats() {
        $today = now()->toDateString();
        return response()->json([
            'today_generations'=> VideoGeneration::whereDate('created_at',$today)->count(),
            'total_generations'=> VideoGeneration::count(),
            'active_users'=> User::where('is_active',true)->count(),
            'total_users'=> User::count(),
            'by_model'=> VideoGeneration::whereDate('created_at',$today)->select('model', DB::raw('count(*) as total'))->groupBy('model')->pluck('total','model'),
            'last_7_days'=> VideoGeneration::where('created_at','>=',now()->subDays(6)->startOfDay())->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))->groupBy('date')->orderBy('date')->get(),
        ]);
    }
    public function users() {
        $users = User::withCount(['generations as today_generations' => fn($q)=> $q->whereDate('created_at', now()->toDateString())])->orderBy('created_at')->get();
        return response()->json($users);
    }
    public function updateUser(Request $request, $id) {
        $user = User::findOrFail($id);
        $data = $request->validate(['name'=>'sometimes|string|max:255','role'=>'sometimes|in:admin,pengguna','daily_limit'=>'sometimes|nullable|integer|min:0|max:100','is_active'=>'sometimes|boolean']);
        $user->update($data);
        return response()->json(['user'=>$user->fresh(),'message'=>'Pengguna diperbarui.']);
    }
    public function toggleUser($id) {
        $user = User::findOrFail($id);
        $user->update(['is_active'=> !$user->is_active]);
        return response()->json(['user'=>$user->fresh()]);
    }
    public function allGenerations() {
        return response()->json(VideoGeneration::with('user:id,name,email')->latest()->paginate(30));
    }
}
