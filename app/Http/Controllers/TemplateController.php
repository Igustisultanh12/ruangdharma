<?php
namespace App\Http\Controllers;
use App\Services\TemplateService;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request, TemplateService $templates)
    {
        $all = $templates->all();
        if ($request->filled('search')) {
            $s = mb_strtolower($request->input('search'));
            $all = array_values(array_filter($all, fn($t) => str_contains(mb_strtolower($t['keyword'] . ' ' . $t['judul'] . ' ' . $t['deskripsi']), $s)));
        }
        return response()->json(['success' => true, 'data' => $all]);
    }
    public function show(Request $request, string $keyword, TemplateService $templates)
    {
        $tpl = $templates->find($keyword);
        if (!$tpl) return response()->json(['success' => false, 'message' => "Template '{$keyword}' tidak ditemukan."], 404);
        return response()->json(['success' => true, 'data' => $tpl]);
    }
}
