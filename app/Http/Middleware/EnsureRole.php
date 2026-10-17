<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Mendukung banyak role: role:admin,editor. Tanpa role = hanya butuh login. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Belum login.'], 401);
        $roles = count($roles) === 1 && str_contains($roles[0], ',') ? explode(',', $roles[0]) : $roles;
        if (!empty($roles) && !$user->hasAnyRole(array_map('trim', $roles))) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Butuh role: ' . implode(', ', $roles)], 403);
        }
        return $next($request);
    }
}
