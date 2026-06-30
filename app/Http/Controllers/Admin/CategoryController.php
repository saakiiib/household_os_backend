<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Str;
use DataTables;
use Intervention\Image\Facades\Image;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $categories = Category::with('parent')->select(['id', 'name', 'image', 'parent_id', 'status'])->orderBy('id', 'desc');
            
            return DataTables::of($categories)
                ->addIndexColumn()
                ->addColumn('image', function ($row) {
                    return $row->image
                        ? '<img src="'.url($row->image).'" class="img-thumbnail avatar-sm">'
                        : '';
                })
                ->addColumn('parent_category', function ($row) {
                    return $row->parent ? $row->parent->name : '<span class="text-muted">None</span>';
                })
                ->addColumn('status', function ($row) {

                    $checked = $row->status ? 'checked' : '';

                    return '
                        <div class="form-check form-switch">
                            <input 
                                class="form-check-input toggle-switch"
                                type="checkbox"
                                data-id="' . $row->id . '"
                                data-url="' . route('category.toggleStatus') . '"
                                data-table="#categoryTable"
                                ' . $checked . '
                            >
                        </div>
                    ';
                })
                ->addColumn('action', function ($row) {
                    return '
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary btn-sm dropdown" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ri-more-fill align-middle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button class="dropdown-item editBtn" rid="'.$row->id.'">
                                        <i class="ri-pencil-fill align-bottom me-2 text-muted"></i> Edit
                                    </button>
                                </li>
                                <li class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item deleteBtn" 
                                            data-delete-url="' . route('category.delete', $row->id) . '" 
                                            data-method="DELETE" 
                                            data-table="#categoryTable">
                                        <i class="ri-delete-bin-fill align-bottom me-2 text-muted"></i> Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    ';
                })
                ->rawColumns(['image', 'parent_category', 'status', 'action'])
                ->make(true);
        }

        $parentCategories = Category::whereNull('parent_id')->where('status', 1)->get();
        return spa('admin.category.index', compact('parentCategories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:categories,name',
            'parent_id' => 'nullable|exists:categories,id'
        ], [
            'name.required' => 'Category name is required.',
            'name.unique' => 'This category already exists.',
            'parent_id.exists' => 'Selected parent category does not exist.',
        ]);
        
        $data = new Category;
        $data->name = $request->name;
        $data->description = $request->description;
        $data->slug = Str::slug($request->name);
        $data->parent_id = $request->parent_id;

        if ($request->hasFile('image')) {
            $uploadedFile = $request->file('image');
            $randomName = mt_rand(10000000, 99999999) . '.webp';
            $destinationPath = public_path('uploads/category/');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            Image::make($uploadedFile)
                ->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('webp', 50)
                ->save($destinationPath . $randomName);

            // Store full path with leading slash
            $data->image = '/uploads/category/' . $randomName;
        }
        
        if ($data->save()) {
            return response()->json([
                'message' => 'Category created successfully!',
                'category' => $data 
            ], 200);
        }

        return response()->json([
            'message' => 'Server error while creating category.'
        ], 500);
    }

    public function edit($id)
    {
        $where = [
            'id'=>$id
        ];
        $info = Category::where($where)->get()->first();
        return response()->json($info);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:categories,name,' . $request->codeid,
            'parent_id' => 'nullable|exists:categories,id'
        ], [
            'name.required' => 'Category name is required.',
            'name.unique' => 'This category already exists.',
            'parent_id.exists' => 'Selected parent category does not exist.',
        ]);

        $data = Category::findOrFail($request->codeid);
        $data->name = $request->name;
        $data->description = $request->description;
        $data->slug = Str::slug($request->name);
        $data->parent_id = $request->parent_id;

        if ($request->hasFile('image')) {
            $uploadedFile = $request->file('image');

            // Delete old image if exists
            if ($data->image && file_exists(public_path($data->image))) {
                @unlink(public_path($data->image));
            }

            $randomName = mt_rand(10000000, 99999999) . '.webp';
            $destinationPath = public_path('uploads/category/');
            if (!file_exists($destinationPath)) mkdir($destinationPath, 0755, true);

            Image::make($uploadedFile)
                ->resize(800, null, fn($c) => $c->aspectRatio())
                ->encode('webp', 50)
                ->save($destinationPath . $randomName)
                ->destroy();

            // Store full path with leading slash
            $data->image = '/uploads/category/' . $randomName;
        }

        if ($data->save()) {
            return response()->json([
                'message' => 'Category updated successfully!'
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to update category. Please try again.'
        ], 500);
    }

    public function delete($id)
    {
        $data = Category::find($id);
        
        if (!$data) {
            return response()->json([
                'message' => 'Category not found.'
            ], 404);
        }

        // Check if category has subcategories
        $hasSubcategories = Category::where('parent_id', $id)->exists();
        if ($hasSubcategories) {
            return response()->json([
                'message' => 'Cannot delete category. It has subcategories. Please delete subcategories first.'
            ], 422);
        }

        // Delete image if exists
        if ($data->image && file_exists(public_path($data->image))) {
            @unlink(public_path($data->image));
        }

        if ($data->delete()) {
            return response()->json([
                'message' => 'Category deleted successfully.'
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to delete category.'
        ], 500);
    }

    public function toggleStatus(Request $request)
    {
        $category = Category::find($request->id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        $category->status = $request->status;

        if ($category->save()) {
            return response()->json([
                'message' => 'Category status updated successfully'
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to update category status'
        ], 500);
    }

    public function parentCategories()
    {
        $parentCategories = Category::whereNull('parent_id')->where('status', 1)
            ->select('id', 'name')
            ->latest()
            ->get();
        
        return response()->json($parentCategories);
    }
}