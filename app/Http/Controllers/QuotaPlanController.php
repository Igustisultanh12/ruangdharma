<?php
namespace App\Http\Controllers;
use App\Models\QuotaPlan;
use App\Models\User;
use Illuminate\Http\Request;

class QuotaPlanController extends Controller
{
    public function index() { return response()->json(['success' => true, 'data' => QuotaPlan::orderBy('daily_limit')->get()]); }
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100|unique:quota_plans,name', 'daily_limit' => 'required|integer|min:1|max:10000', 'price' => 'sometimes|integer|min:0', 'description' => 'nullable|string|max:500', 'is_active' => 'sometimes|boolean']);
        $plan = QuotaPlan::create($data);
        return response()->json(['success' => true, 'message' => 'Paket dibuat.', 'data' => $plan], 201);
    }
    public function update(Request $request, $id)
    {
        $plan = QuotaPlan::findOrFail($id);
        $data = $request->validate(['name' => 'sometimes|string|max:100', 'daily_limit' => 'sometimes|integer|min:1|max:10000', 'price' => 'sometimes|integer|min:0', 'description' => 'sometimes|nullable|string|max:500', 'is_active' => 'sometimes|boolean']);
        $plan->update($data);
        return response()->json(['success' => true, 'message' => 'Paket diperbarui.', 'data' => $plan->fresh()]);
    }
    public function destroy($id)
    {
        $plan = QuotaPlan::findOrFail($id);
        if ($plan->users()->exists()) return response()->json(['success' => false, 'message' => 'Paket masih dipakai pengguna, tidak bisa dihapus.'], 422);
        $plan->delete();
        return response()->json(['success' => true, 'message' => 'Paket dihapus.']);
    }
    /** Assign paket ke user. */
    public function assign(Request $request, $userId)
    {
        $data = $request->validate(['quota_plan_id' => 'required|integer|exists:quota_plans,id']);
        $user = User::findOrFail($userId);
        $user->update(['quota_plan_id' => $data['quota_plan_id']]);
        return response()->json(['success' => true, 'message' => 'Paket ditetapkan ke user.', 'user' => AuthController::userPayload($user->fresh()->load('quotaPlan'))]);
    }
}
