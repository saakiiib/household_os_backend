<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;

class DocumentController extends Controller
{
    public function index()
    {
        $totalDocuments = \App\Models\Document::count();
        $dueSoonDocuments = \App\Models\Document::whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->count();
        $overdueDocuments = \App\Models\Document::whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();
        $documentsThisMonth = \App\Models\Document::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('admin.pages.documents', compact(
            'totalDocuments', 'dueSoonDocuments', 'overdueDocuments', 'documentsThisMonth'
        ));
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
