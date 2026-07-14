<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentItem;
use App\Services\FileEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentsController extends Controller
{
    protected $fileService;

    public function __construct(FileEncryptionService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * GET /api/households/{household_id}/documents
     */
    public function index(Request $request, $household_id)
    {
        $query = Document::with(['createdBy:id,first_name,last_name,email,avatar', 'items', 'files'])
            ->where('household_id', $household_id);

        // Text search — title, description, category, created by name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhereHas('createdBy', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('due_date_from')) {
            $query->where('due_date', '>=', $request->due_date_from);
        }
        if ($request->filled('due_date_to')) {
            $query->where('due_date', '<=', $request->due_date_to);
        }

        $documents = $query->orderBy('created_at', 'desc')->get()
            ->map(fn($doc) => $this->formatDocument($doc));

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * POST /api/households/{household_id}/documents
     * Creates document + car items + files in a single DB transaction.
     */
    public function store(Request $request, $household_id)
    {
        // 1. Validate non-file fields
        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            'category'          => 'required|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'due_date'          => 'nullable|date',
            'items'             => 'nullable|array',
            'items.*.item_type' => 'required_with:items|in:mot,service,road_tax,insurance',
            'items.*.due_date'  => 'nullable|date',
            'items.*.price'     => 'nullable|numeric|min:0',
            'items.*.notes'     => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 2. Get files and normalize to array (Flutter sends 1 file as single object)
        $files = [];
        if ($request->hasFile('files')) {
            $raw = $request->file('files');
            $files = is_array($raw) ? $raw : [$raw];

            // Validate each file
            foreach ($files as $i => $file) {
                if (!$file instanceof \Illuminate\Http\UploadedFile) {
                    return response()->json([
                        'success' => false,
                        'message' => "File at index {$i} is not a valid upload.",
                    ], 422);
                }
                if ($file->getSize() > 10240 * 1024) {
                    return response()->json([
                        'success' => false,
                        'message' => "File '{$file->getClientOriginalName()}' exceeds 10MB limit.",
                    ], 422);
                }
                $allowed = ['pdf','jpg','jpeg','png','gif','webp','doc','docx'];
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, $allowed)) {
                    return response()->json([
                        'success' => false,
                        'message' => "File type '.{$ext}' is not allowed. Accepted: " . implode(', ', $allowed),
                    ], 422);
                }
            }

            if (count($files) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 10 files allowed.',
                ], 422);
            }
        }

        $uploadedFiles = [];
        $encryptedPaths = [];

        DB::beginTransaction();

        try {
            // 3. Create the document
            $document = Document::create([
                'household_id'       => $household_id,
                'created_by_user_id' => Auth::id(),
                'title'              => $request->title,
                'category'           => $request->category,
                'description'        => $request->description,
                'due_date'           => $request->due_date,
            ]);

            // 4. If car category, create the 4 sub-items
            if ($request->category === 'car' && $request->has('items') && is_array($request->items)) {
                foreach ($request->items as $item) {
                    $document->items()->create([
                        'item_type' => $item['item_type'],
                        'due_date'  => $item['due_date'] ?? null,
                        'price'     => $item['price'] ?? null,
                        'notes'     => $item['notes'] ?? null,
                        'status'    => 'pending',
                    ]);
                }
            }

            // 5. If files attached, encrypt and store them
            foreach ($files as $file) {
                $path = $this->fileService->encryptAndStore($file, 'documents');
                $encryptedPaths[] = $path;

                $docFile = DocumentFile::create([
                    'document_id'       => $document->id,
                    'file_path'         => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type'         => $file->getMimeType(),
                    'file_size'         => $file->getSize(),
                ]);

                $uploadedFiles[] = $this->formatFile($docFile);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($encryptedPaths as $path) {
                $this->fileService->delete($path);
            }

            \Log::error('Document store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create document: ' . $e->getMessage(),
            ], 500);
        }

        $document->load(['createdBy:id,first_name,last_name,email,avatar', 'items', 'files']);

        return response()->json([
            'success' => true,
            'message' => 'Document created successfully',
            'data' => $this->formatDocument($document),
        ], 201);
    }

    /**
     * GET /api/households/{household_id}/documents/{document_id}
     */
    public function show($household_id, $document_id)
    {
        $document = Document::with(['createdBy:id,first_name,last_name,email,avatar', 'items', 'files'])
            ->where('household_id', $household_id)
            ->findOrFail($document_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDocument($document),
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/documents/{document_id}
     */
    public function update(Request $request, $household_id, $document_id)
    {
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        $validator = Validator::make($request->all(), [
            'title'             => 'sometimes|string|max:255',
            'category'          => 'sometimes|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'due_date'          => 'nullable|date',
            'items'             => 'nullable|array',
            'items.*.id'        => 'nullable|integer',
            'items.*.item_type' => 'required_with:items|in:mot,service,road_tax,insurance',
            'items.*.due_date'  => 'nullable|date',
            'items.*.price'     => 'nullable|numeric|min:0',
            'items.*.notes'     => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $document->update($request->only([
                'title', 'category', 'description', 'due_date',
            ]));

            // Update car items if provided
            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $itemData) {
                    if (!empty($itemData['id'])) {
                        // Update existing item
                        $item = DocumentItem::where('id', $itemData['id'])
                            ->where('document_id', $document->id)
                            ->first();
                        if ($item) {
                            $item->update([
                                'due_date' => $itemData['due_date'] ?? null,
                                'price'    => $itemData['price'] ?? null,
                                'notes'    => $itemData['notes'] ?? null,
                            ]);
                        }
                    } else {
                        // Create new item
                        $document->items()->create([
                            'item_type' => $itemData['item_type'],
                            'due_date'  => $itemData['due_date'] ?? null,
                            'price'     => $itemData['price'] ?? null,
                            'notes'     => $itemData['notes'] ?? null,
                            'status'    => 'pending',
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Document update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update document: ' . $e->getMessage(),
            ], 500);
        }

        $document->load(['createdBy:id,first_name,last_name,email,avatar', 'items', 'files']);

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully',
            'data' => $this->formatDocument($document),
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/documents/{document_id}
     */
    public function destroy($household_id, $document_id)
    {
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        DB::beginTransaction();

        try {
            // Delete all associated files from disk
            foreach ($document->files as $file) {
                $this->fileService->delete($file->file_path);
            }

            // Delete from DB (cascades to items and files)
            $document->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Document delete failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * POST /api/households/{household_id}/documents/{document_id}/files
     * Upload one or more encrypted files with DB transaction.
     */
    public function uploadFiles(Request $request, $household_id, $document_id)
    {
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        // 1. Get files and normalize to array
        if (!$request->hasFile('files')) {
            return response()->json([
                'success' => false,
                'message' => 'No files provided.',
            ], 422);
        }

        $raw = $request->file('files');
        $files = is_array($raw) ? $raw : [$raw];

        // Validate each file
        foreach ($files as $i => $file) {
            if (!$file instanceof \Illuminate\Http\UploadedFile) {
                return response()->json([
                    'success' => false,
                    'message' => "File at index {$i} is not a valid upload.",
                ], 422);
            }
            if ($file->getSize() > 10240 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => "File '{$file->getClientOriginalName()}' exceeds 10MB limit.",
                ], 422);
            }
            $allowed = ['pdf','jpg','jpeg','png','gif','webp','doc','docx'];
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowed)) {
                return response()->json([
                    'success' => false,
                    'message' => "File type '.{$ext}' is not allowed. Accepted: " . implode(', ', $allowed),
                ], 422);
            }
        }

        if (count($files) > 10) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum 10 files allowed.',
            ], 422);
        }

        // 2. Validate document_item if provided
        if ($request->document_item_id) {
            $item = DocumentItem::where('id', $request->document_item_id)
                ->where('document_id', $document_id)
                ->first();
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document item not found for this document.',
                ], 422);
            }
        }

        $uploadedFiles = [];
        $encryptedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($files as $file) {
                $path = $this->fileService->encryptAndStore($file, 'documents');
                $encryptedPaths[] = $path;

                $docFile = DocumentFile::create([
                    'document_id'       => $document_id,
                    'document_item_id'  => $request->document_item_id,
                    'file_path'         => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type'         => $file->getMimeType(),
                    'file_size'         => $file->getSize(),
                ]);

                $uploadedFiles[] = $this->formatFile($docFile);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($encryptedPaths as $path) {
                $this->fileService->delete($path);
            }

            \Log::error('File upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload files: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
            'data' => $uploadedFiles,
        ], 201);
    }

    /**
     * DELETE /api/households/{household_id}/documents/{document_id}/files/{file_id}
     */
    public function deleteFile($household_id, $document_id, $file_id)
    {
        Document::where('household_id', $household_id)->findOrFail($document_id);

        $file = DocumentFile::where('id', $file_id)
            ->where('document_id', $document_id)
            ->firstOrFail();

        DB::beginTransaction();

        try {
            $this->fileService->delete($file->file_path);
            $file->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('File delete failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully',
        ]);
    }

    /**
     * GET /api/households/{household_id}/documents/{document_id}/files/{file_id}/download
     * Decrypt and serve the file.
     */
    public function downloadFile($household_id, $document_id, $file_id)
    {
        Document::where('household_id', $household_id)->findOrFail($document_id);

        $file = DocumentFile::where('id', $file_id)
            ->where('document_id', $document_id)
            ->firstOrFail();

        $contents = $this->fileService->decrypt($file->file_path);

        if ($contents === null) {
            return response()->json([
                'success' => false,
                'message' => 'File not found or could not be decrypted.',
            ], 404);
        }

        return response($contents)
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . $file->original_filename . '"');
    }

    /**
     * PATCH /api/households/{household_id}/documents/{document_id}/items/{item_id}
     * Update a car sub-item (due_date, price, status, notes).
     */
    public function updateItem(Request $request, $household_id, $document_id, $item_id)
    {
        Document::where('household_id', $household_id)->findOrFail($document_id);

        $item = DocumentItem::where('id', $item_id)
            ->where('document_id', $document_id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'due_date' => 'sometimes|date',
            'price'    => 'sometimes|numeric|min:0',
            'status'   => 'sometimes|in:pending,completed',
            'notes'    => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item->update($request->only(['due_date', 'price', 'status', 'notes']));

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'data' => $this->formatItem($item),
        ]);
    }

    // ==================== FORMAT HELPERS ====================

    private function formatDocument(Document $doc): array
    {
        return [
            'id'                => $doc->id,
            'household_id'      => $doc->household_id,
            'title'             => $doc->title,
            'category'          => $doc->category,
            'description'       => $doc->description,
            'due_date'          => $doc->due_date instanceof \DateTimeInterface ? $doc->due_date->format('Y-m-d') : $doc->due_date,
            'created_by_user_id'=> $doc->created_by_user_id,
            'created_by'        => $doc->createdBy ? [
                'id'    => $doc->createdBy->id,
                'name'  => $doc->createdBy->name,
                'email' => $doc->createdBy->email,
                'avatar'=> $doc->createdBy->avatar,
            ] : null,
            'is_overdue'        => $doc->is_overdue,
            'days_until_due'    => $doc->days_until_due,
            'items'             => $doc->items->map(fn($item) => $this->formatItem($item)),
            'files'             => $doc->files->map(fn($file) => $this->formatFile($file)),
            'created_at'        => $doc->created_at?->toIso8601String(),
            'updated_at'        => $doc->updated_at?->toIso8601String(),
        ];
    }

    private function formatItem(DocumentItem $item): array
    {
        return [
            'id'            => $item->id,
            'document_id'   => $item->document_id,
            'item_type'     => $item->item_type,
            'due_date'      => $item->due_date instanceof \DateTimeInterface ? $item->due_date->format('Y-m-d') : $item->due_date,
            'price'         => $item->price,
            'status'        => $item->status,
            'notes'         => $item->notes,
            'is_overdue'    => $item->is_overdue,
            'days_until_due'=> $item->days_until_due,
            'files'         => $item->files->map(fn($f) => $this->formatFile($f)),
            'created_at'    => $item->created_at?->toIso8601String(),
            'updated_at'    => $item->updated_at?->toIso8601String(),
        ];
    }

    private function formatFile(DocumentFile $file): array
    {
        return [
            'id'                => $file->id,
            'document_id'       => $file->document_id,
            'document_item_id'  => $file->document_item_id,
            'original_filename' => $file->original_filename,
            'mime_type'         => $file->mime_type,
            'file_size'         => $file->file_size,
            'created_at'        => $file->created_at?->toIso8601String(),
        ];
    }
}
