<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSeo;
use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Yajra\DataTables\Facades\DataTables;

class PageSeoController extends Controller
{
    private array $pages = [
        'about'    => 'About Us',
        'shop' => 'Shop',
        'contact'  => 'Contact',
    ];

    public function index(Request $request)
    {
        $validKeys = array_keys($this->pages);

        foreach ($this->pages as $key => $label) {
            PageSeo::firstOrCreate(
                ['page_key' => $key],
                ['page_label' => $label]
            );
        }

        PageSeo::whereNotIn('page_key', $validKeys)->delete();

        if ($request->ajax()) {
            return DataTables::of(PageSeo::latest()->get())
                ->addIndexColumn()
                ->addColumn('image', fn($row) => $row->meta_image
                    ? '<img src="' . asset($row->meta_image) . '" class="img-thumbnail" style="max-width:80px;">'
                    : '-')
                ->addColumn('action', function ($row) {
                    return '
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="ri-more-fill align-middle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button class="dropdown-item EditBtn" data-id="' . $row->id . '">
                                        <i class="ri-pencil-fill align-bottom me-2 text-muted"></i> Edit
                                    </button>
                                </li>
                            </ul>
                        </div>';
                })
                ->rawColumns(['image', 'action'])
                ->make(true);
        }

        return spa('admin.page-seo.index');
    }

    public function edit($id)
    {
        return response()->json(PageSeo::findOrFail($id));
    }

    public function update(Request $request)
    {
        $request->validate([
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords'    => 'nullable|string',
            'meta_image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $pageSeo = PageSeo::findOrFail($request->id);
        $data    = $request->only(['meta_title', 'meta_description', 'meta_keywords']);

        if ($request->hasFile('meta_image')) {
            $path = public_path('uploads/page-seo/');
            if (!file_exists($path)) mkdir($path, 0755, true);

            if ($pageSeo->meta_image && file_exists(public_path($pageSeo->meta_image))) {
                unlink(public_path($pageSeo->meta_image));
            }

            $name = time() . '.webp';
            Image::make($request->file('meta_image'))
                ->resize(1200, 630, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('webp', 80)
                ->save($path . $name);

            $data['meta_image'] = 'uploads/page-seo/' . $name;
        }

        $pageSeo->update($data);
        return response()->json(['message' => 'Page SEO updated successfully']);
    }
}
