<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Services\EntitlementService;
use App\Services\FileEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DocumentsController extends Controller
{
    protected $fileService;
    protected $entitlements;

    public function __construct(FileEncryptionService $fileService, EntitlementService $entitlements)
    {
        $this->fileService = $fileService;
        $this->entitlements = $entitlements;
    }

    /**
     * GET /api/households/{household_id}/documents
     */
    public function index(Request $request, $household_id)
    {
        $userId = Auth::id();

        // Caller must be an active member of this household
        $isActiveMember = HouseholdMember::where('household_id', $household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (!$isActiveMember) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this household.',
            ], 403);
        }

        $query = Document::with(['createdBy:id,first_name,last_name,email,avatar', 'files', 'allowedMembers:id'])
            ->where('household_id', $household_id);

        // Visibility filter: only show documents the user can see
        $query->where(function ($q) use ($userId) {
            $q->where('visibility', 'all')
              ->orWhere('created_by_user_id', $userId)
              ->orWhereHas('allowedMembers', function ($uq) use ($userId) {
                  $uq->where('users.id', $userId);
              });
        });

        // Text search
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
            ->map(fn($doc) => $this->formatDocument($doc, $userId));

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * POST /api/households/{household_id}/documents
     */
    public function store(Request $request, $household_id)
    {
        $userId = Auth::id();

        // Must be an active member to create documents in this household
        $isActiveMember = HouseholdMember::where('household_id', $household_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (!$isActiveMember) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to add documents to this household.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            'category'          => 'required|string|max:100',
            'description'       => 'nullable|string|max:2000',
            'due_date'          => 'nullable|date',
            'visibility'        => 'required|in:all,specific',
            'allowed_user_ids'  => 'required_if:visibility,specific|array|min:1',
            'allowed_user_ids.*' => [
                'integer',
                Rule::exists('household_members', 'user_id')
                    ->where('household_id', (int) $household_id)
                    ->where('status', 'active'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $visibility = $request->input('visibility');
        $allowedUserIds = $visibility === 'all' ? [] : $request->input('allowed_user_ids', []);

        $files = [];
        if ($request->hasFile('files')) {
            $raw = $request->file('files');
            $files = is_array($raw) ? $raw : [$raw];

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

            // Entitlement gate: storage is capped per plan (command.txt §19 / §32).
            $additionalBytes = array_reduce($files, fn($sum, $f) => $sum + $f->getSize(), 0);
            $household = Household::findOrFail($household_id);
            if (!$this->entitlements->canUploadDocument($household, $additionalBytes)) {
                $usedMb = round($this->entitlements->getStorageUsed($household) / 1024 / 1024, 1);
                return response()->json([
                    'success' => false,
                    'message' => "Your Document Locker is above your storage allowance ({$usedMb} MB used). Upgrade to add more files.",
                    'code' => 'ENTITLEMENT_LIMIT_STORAGE',
                ], 403);
            }
        }

        $uploadedFiles = [];
        $encryptedPaths = [];

        DB::beginTransaction();

        try {
            $document = Document::create([
                'household_id'       => $household_id,
                'created_by_user_id' => $userId,
                'title'              => $request->title,
                'category'           => $request->category,
                'description'        => $request->description,
                'due_date'           => $request->due_date,
                'visibility'         => $visibility,
            ]);

            // Attach allowed members for 'specific' visibility
            if ($visibility === 'specific' && !empty($allowedUserIds)) {
                $document->allowedMembers()->sync($allowedUserIds);
            }

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

            Log::error('Document store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create document: ' . $e->getMessage(),
            ], 500);
        }

        $document->load(['createdBy:id,first_name,last_name,email,avatar', 'files', 'allowedMembers:id']);

        return response()->json([
            'success' => true,
            'message' => 'Document created successfully',
            'data' => $this->formatDocument($document, $userId),
        ], 201);
    }

    /**
     * GET /api/households/{household_id}/documents/{document_id}
     */
    public function show($household_id, $document_id)
    {
        $userId = Auth::id();

        $document = Document::with(['createdBy:id,first_name,last_name,email,avatar', 'files', 'allowedMembers:id'])
            ->where('household_id', $household_id)
            ->findOrFail($document_id);

        if (!$document->canUserView($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this document.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDocument($document, $userId),
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/documents/{document_id}
     */
    public function update(Request $request, $household_id, $document_id)
    {
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        if (!$document->canUserManage(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Only the document creator can modify this document.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'             => 'sometimes|string|max:255',
            'category'          => 'sometimes|string|max:100',
            'description'       => 'nullable|string|max:2000',
            'due_date'          => 'nullable|date',
            'visibility'        => 'sometimes|in:all,specific',
            'allowed_user_ids'  => [
                Rule::requiredIf(fn() => $request->input('visibility') === 'specific'),
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($request, $document) {
                    $effectiveVisibility = $request->input('visibility', $document->visibility);
                    if ($effectiveVisibility === 'specific' && $request->has('allowed_user_ids')) {
                        if (!is_array($value) || count($value) === 0) {
                            $fail('At least one active household member must be selected for specific visibility.');
                        }
                    }
                },
            ],
            'allowed_user_ids.*' => [
                'integer',
                Rule::exists('household_members', 'user_id')
                    ->where('household_id', (int) $household_id)
                    ->where('status', 'active'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = $request->only(['title', 'category', 'description', 'due_date']);

        if ($request->has('visibility')) {
            $updateData['visibility'] = $request->visibility;

            // If switching to 'all', detach all allowed members
            if ($request->visibility === 'all') {
                $document->allowedMembers()->detach();
            }
        }

        $document->update($updateData);

        // Update allowed members for 'specific' visibility
        $effectiveVisibility = $request->input('visibility', $document->visibility);
        if ($effectiveVisibility === 'specific' && $request->has('allowed_user_ids')) {
            $document->allowedMembers()->sync($request->input('allowed_user_ids', []));
        }

        $document->load(['createdBy:id,first_name,last_name,email,avatar', 'files', 'allowedMembers:id']);

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully',
            'data' => $this->formatDocument($document, Auth::id()),
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/documents/{document_id}
     */
    public function destroy($household_id, $document_id)
    {
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        if (!$document->canUserManage(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Only the document creator can delete this document.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            foreach ($document->files as $file) {
                $this->fileService->delete($file->file_path);
            }

            $document->allowedMembers()->detach();
            $document->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document delete failed: ' . $e->getMessage());

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
     */
    public function uploadFiles(Request $request, $household_id, $document_id)
    {
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        if (!$document->canUserManage(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Only the document creator can upload files to this document.',
            ], 403);
        }

        if (!$request->hasFile('files')) {
            return response()->json([
                'success' => false,
                'message' => 'No files provided.',
            ], 422);
        }

        $raw = $request->file('files');
        $files = is_array($raw) ? $raw : [$raw];

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

        // Entitlement gate: check storage limit before uploading.
        $household = Household::findOrFail($household_id);
        $additionalBytes = array_sum(array_map(fn($f) => $f->getSize(), $files));
        if (!$this->entitlements->canUploadDocument($household, $additionalBytes)) {
            return response()->json([
                'success' => false,
                'message' => 'Storage limit reached. Upgrade to Document Locker or Complete for 5 GB.',
                'code' => 'ENTITLEMENT_LIMIT_DOCUMENTS',
            ], 403);
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

            Log::error('File upload failed: ' . $e->getMessage());

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
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        if (!$document->canUserManage(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Only the document creator can delete files from this document.',
            ], 403);
        }

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
            Log::error('File delete failed: ' . $e->getMessage());

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
     */
    public function downloadFile($household_id, $document_id, $file_id)
    {
        $document = Document::where('household_id', $household_id)->findOrFail($document_id);

        if (!$document->canUserView(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this document.',
            ], 403);
        }

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

    // ==================== FORMAT HELPERS ====================

    private function formatDocument(Document $doc, int $currentUserId): array
    {
        return [
            'id'                => $doc->id,
            'household_id'      => $doc->household_id,
            'title'             => $doc->title,
            'category'          => $doc->category,
            'description'       => $doc->description,
            'due_date'          => $doc->due_date instanceof \DateTimeInterface ? $doc->due_date->format('Y-m-d') : $doc->due_date,
            'visibility'        => $doc->visibility,
            'allowed_user_ids'  => $doc->allowedMembers->pluck('id'),
            'created_by_user_id'=> $doc->created_by_user_id,
            'created_by'        => $doc->createdBy ? [
                'id'    => $doc->createdBy->id,
                'name'  => $doc->createdBy->name,
                'email' => $doc->createdBy->email,
                'avatar'=> $doc->createdBy->avatar,
            ] : null,
            'is_overdue'        => $doc->is_overdue,
            'days_until_due'    => $doc->days_until_due,
            'files'             => $doc->files->map(fn($file) => $this->formatFile($file)),
            'created_at'        => $doc->created_at?->toIso8601String(),
            'updated_at'        => $doc->updated_at?->toIso8601String(),
        ];
    }

    private function formatFile(DocumentFile $file): array
    {
        return [
            'id'                => $file->id,
            'document_id'       => $file->document_id,
            'original_filename' => $file->original_filename,
            'mime_type'         => $file->mime_type,
            'file_size'         => $file->file_size,
            'created_at'        => $file->created_at?->toIso8601String(),
        ];
    }
}
