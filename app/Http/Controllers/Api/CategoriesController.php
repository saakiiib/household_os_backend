<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriesController extends Controller
{
    /**
     * GET /api/households/{household_id}/categories?type=document|renewal
     */
    public function index(Request $request, $household_id)
    {
        $query = Category::where('household_id', $household_id);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $categories = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * POST /api/households/{household_id}/categories
     */
    public function store(Request $request, $household_id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:document,renewal',
            'icon' => 'nullable|string|max:50',
        ]);

        $name = trim($request->input('name'));
        $slug = Str::slug($name);

        // Reserved keyword — vehicle is only for renewals and is system-managed
        if ($slug === 'vehicle') {
            return response()->json([
                'success' => false,
                'message' => '"Vehicle" is a reserved category and cannot be created manually.',
            ], 422);
        }

        // Duplicate check within same type
        $exists = Category::where('household_id', $household_id)
            ->where('type', $request->input('type'))
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A category with this name already exists.',
            ], 409);
        }

        $category = Category::create([
            'household_id' => $household_id,
            'name' => $name,
            'slug' => $slug,
            'type' => $request->input('type'),
            'icon' => $request->input('icon', 'folder_outlined'),
            'is_system' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }

    /**
     * DELETE /api/households/{household_id}/categories/{category_id}
     */
    public function destroy(Request $request, $household_id, $category_id)
    {
        $category = Category::where('id', $category_id)
            ->where('household_id', $household_id)
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        if ($category->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System categories cannot be deleted.',
            ], 403);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted.',
        ]);
    }

    /**
     * POST /api/households/{household_id}/categories/seed
     * Seeds default categories for a household. Separate lists for documents & renewals.
     */
    public function seed($household_id)
    {
        $documentCategories = [
            ['name' => 'Home Insurance', 'slug' => 'home-insurance', 'icon' => 'home_outlined'],
            ['name' => 'Identity', 'slug' => 'identity', 'icon' => 'badge_outlined'],
            ['name' => 'Finance', 'slug' => 'finance', 'icon' => 'account_balance_outlined'],
            ['name' => 'Utilities', 'slug' => 'utilities', 'icon' => 'bolt_outlined'],
            ['name' => 'Medical', 'slug' => 'medical', 'icon' => 'local_hospital_outlined'],
            ['name' => 'Emergency', 'slug' => 'emergency', 'icon' => 'emergency_outlined'],
            ['name' => 'Other', 'slug' => 'other', 'icon' => 'folder_outlined'],
        ];

        $renewalCategories = [
            ['name' => 'Home Insurance', 'slug' => 'home-insurance', 'icon' => 'home_outlined'],
            ['name' => 'Vehicle', 'slug' => 'vehicle', 'icon' => 'directions_car_outlined', 'is_system' => true],
            ['name' => 'Identity', 'slug' => 'identity', 'icon' => 'badge_outlined'],
            ['name' => 'Finance', 'slug' => 'finance', 'icon' => 'account_balance_outlined'],
            ['name' => 'Utilities', 'slug' => 'utilities', 'icon' => 'bolt_outlined'],
            ['name' => 'Medical', 'slug' => 'medical', 'icon' => 'local_hospital_outlined'],
            ['name' => 'Emergency', 'slug' => 'emergency', 'icon' => 'emergency_outlined'],
            ['name' => 'Other', 'slug' => 'other', 'icon' => 'folder_outlined'],
        ];

        foreach ($documentCategories as $cat) {
            Category::updateOrCreate(
                ['household_id' => $household_id, 'slug' => $cat['slug'], 'type' => 'document'],
                ['name' => $cat['name'], 'icon' => $cat['icon'], 'is_system' => false]
            );
        }

        foreach ($renewalCategories as $cat) {
            Category::updateOrCreate(
                ['household_id' => $household_id, 'slug' => $cat['slug'], 'type' => 'renewal'],
                ['name' => $cat['name'], 'icon' => $cat['icon'], 'is_system' => $cat['is_system'] ?? false]
            );
        }

        return response()->json(['success' => true]);
    }
}
