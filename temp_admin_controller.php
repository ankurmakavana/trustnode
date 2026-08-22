<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ReleaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Release::query()->with('product');
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'version' => 'required|string',
            'artifact' => 'required|file|mimes:zip|max:512000',
            'release_notes' => 'nullable|string',
            'is_active' => 'boolean',
            'is_latest' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $exists = Release::where('product_id', $request->product_id)
            ->where('version', $request->version)
            ->exists();
            
        if ($exists) {
            return response()->json(['error' => 'Version already exists for this product.'], 422);
        }

        $file = $request->file('artifact');
        $path = $file->store('releases', 'local');
        $checksum = hash_file('sha256', Storage::disk('local')->path($path));
        
        DB::beginTransaction();
        try {
            if ($request->boolean('is_latest')) {
                Release::where('product_id', $request->product_id)->update(['is_latest' => false]);
            }

            $release = Release::create([
                'product_id' => $request->product_id,
                'version' => $request->version,
                'artifact_path' => $path,
                'artifact_filename' => $file->getClientOriginalName(),
                'artifact_size' => $file->getSize(),
                'checksum' => $checksum,
                'release_notes' => $request->release_notes,
                'is_active' => $request->boolean('is_active', true),
                'is_latest' => $request->boolean('is_latest', false),
                'published_at' => $request->published_at ?? now(),
            ]);
            DB::commit();

            return response()->json($release, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function update(Request $request, Release $release)
    {
        $request->validate([
            'release_notes' => 'nullable|string',
            'is_active' => 'boolean',
            'is_latest' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($request->boolean('is_latest') && !$release->is_latest) {
                Release::where('product_id', $release->product_id)->update(['is_latest' => false]);
            }

            $release->update($request->only(['release_notes', 'is_active', 'is_latest']));
            DB::commit();

            return response()->json($release);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
