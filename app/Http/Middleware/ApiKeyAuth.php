<?php
namespace App\Http\Middleware;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Auth eksternal via header X-Api-Key (key mentah di-hash SHA-256 lalu dicocokkan). */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Api-Key');
        if (!$key) return response()->json(['success' => false, 'message' => 'Header X-Api-Key wajib diisi.'], 401);
        $record = ApiKey::where('key_hash', hash('sha256', $key))->where('is_active', true)->first();
        if (!$record) return response()->json(['success' => false, 'message' => 'API key tidak valid atau nonaktif.'], 401);
        $record->touchUsage();
        $request->setUserResolver(fn() => $record->user);
        $request->attributes->set('api_key', $record);
        return $next($request);
    }
}
