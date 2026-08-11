<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Yajra\DataTables\Facades\DataTables;

class DocumentController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            return DataTables::of(Document::with('household', 'createdBy', 'files')
                ->select('id', 'title', 'category', 'due_date', 'household_id'))
                ->addColumn('household_name', fn($d) => $d->household->name ?? 'N/A')
                ->addColumn('category_fmt', fn($d) => ucfirst(str_replace('_', ' ', $d->category)))
                ->addColumn('files_count', fn($d) => $d->files->count())
                ->addColumn('due_date_fmt', fn($d) => $d->due_date ? $d->due_date->format('d M Y') : '-')
                ->make(true);
        }

        return view('admin.pages.documents');
    }
}
