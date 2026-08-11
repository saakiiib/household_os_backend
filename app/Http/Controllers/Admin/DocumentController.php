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
                ->addColumn('title_link', function ($d) {
                    return '<a href="' . route('admin.documents.show', $d) . '" class="fw-semibold text-body">' . e($d->title) . '</a>';
                })
                ->addColumn('household_link', function ($d) {
                    if (!$d->household) return 'N/A';
                    return '<a href="' . route('admin.households.show', $d->household) . '" class="text-body">' . e($d->household->name) . '</a>';
                })
                ->addColumn('category_fmt', fn($d) => ucfirst(str_replace('_', ' ', $d->category)))
                ->addColumn('files_count', fn($d) => $d->files->count())
                ->addColumn('due_date_fmt', fn($d) => $d->due_date ? $d->due_date->format('d M Y') : '-')
                ->rawColumns(['title_link', 'household_link'])
                ->make(true);
        }

        return view('admin.pages.documents');
    }

    public function show(Document $document)
    {
        $document->load('household', 'createdBy', 'files');

        $siblingDocs = Document::where('household_id', $document->household_id)
            ->where('id', '!=', $document->id)
            ->with('files')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.pages.document-show', compact('document', 'siblingDocs'));
    }
}
