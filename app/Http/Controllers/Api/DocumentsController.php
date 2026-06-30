<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAccess;
use App\Models\HouseholdMember;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentsController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get active household member record for current user.
     */
    private function getMembership(int $householdId): ?HouseholdMember
    {
        return HouseholdMember::where('household_id', $householdId)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();
    }

    /**
     * Format a document for API responses.
     */
    private function formatDocument(Document $doc): array
    {
        return [
            'id'                 => $doc->id,
            'household_id'       => $doc->household_id,
            'title'              => $doc->title,
            'category'           => $doc->category,
            'description'        => $doc->description,
            'file_type'          => $doc->file_type,
            'file_size'          => $doc->file_size_bytes,
            'uploaded_by'        => $doc->uploadedBy ? ['id' => $doc->uploadedBy->id, 'name' => $doc->uploadedBy->name] : null,
            'is_encrypted'       => $doc->is_encrypted,
            'encryption_method'  => $doc->encryption_method,
            'checksum'           => $doc->checksum,
            'expiry_date'        => $doc->expiry_date ? $doc->expiry_date->toDateString() : null,
            'shared_with_roles'  => $doc->shared_with_roles,
            'shared_with_users'  => $doc->shared_with_users,
            'is_sensitive'       => $doc->is_sensitive,
            'download_count'     => $doc->download_count,
            'created_at'         => $doc->created_at,
            'updated_at'         => $doc->updated_at,
        ];
    }

    /**
     * Check if the user has access to this specific document based on permissions.
     */
    private function userHasAccess(Document $doc, HouseholdMember $membership): bool
    {
        // 1. Admins/Co-admins always have access
        if ($membership->isAdminOrCoAdmin()) {
            return true;
        }

        // 2. The owner (uploader) always has access
        if ($doc->uploaded_by_user_id === Auth::id()) {
            return true;
        }

        // 3. Check shared with roles
        if (!empty($doc->shared_with_roles) && in_array($membership->role, $doc->shared_with_roles)) {
            return true;
        }

        // 4. Check shared with specific users
        if (!empty($doc->shared_with_users) && in_array(Auth::id(), $doc->shared_with_users)) {
            return true;
        }

        // 5. If sharing properties are null/empty, defaults to all active household members
        if (empty($doc->shared_with_roles) && empty($doc->shared_with_users)) {
            return true;
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Endpoints
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/households/{household_id}/documents
     * List all documents user has access to. Optional search and category filters.
     */
    public function index(Request $request, $household_id)
    {
        $membership = $this->getMembership($household_id);
        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $query = Document::with('uploadedBy')
            ->where('household_id', $household_id);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        // Filter documents by user permissions
        $filtered = $documents->filter(fn($doc) => $this->userHasAccess($doc, $membership));

        return response()->json([
            'success' => true,
            'data'    => $filtered->map(fn($d) => $this->formatDocument($d))->values(),
        ]);
    }

    /**
     * POST /api/households/{household_id}/documents
     * Upload and encrypt a new document.
     */
    public function store(Request $request, $household_id)
    {
        $membership = $this->getMembership($household_id);
        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'file'               => 'required|file|max:10240', // 10MB max
            'title'              => 'required|string|max:255',
            'category'           => 'required|in:insurance,passport,medical,school,warranty,contract,deed,utility_bill,tax,other',
            'description'        => 'nullable|string',
            'expiry_date'        => 'nullable|date',
            'shared_with_roles'  => 'nullable|array',
            'shared_with_roles.*'=> 'in:admin,co-admin,member',
            'shared_with_users'  => 'nullable|array',
            'shared_with_users.*'=> 'integer|exists:users,id',
            'is_sensitive'       => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $uploadedFile = $request->file('file');
        $plainText    = file_get_contents($uploadedFile->getRealPath());

        // 1. Perform Envelope Encryption
        $enc = EncryptionService::encrypt($plainText);

        // 2. Generate a random physical storage name
        $originalExt  = strtolower($uploadedFile->getClientOriginalExtension());
        $storedName   = Str::uuid()->toString() . '.' . $originalExt;
        $relativeDir  = 'uploads/documents/' . $household_id;
        $fullPath     = $relativeDir . '/' . $storedName;

        // 3. Store the ciphertext
        Storage::disk('local')->put($fullPath, $enc['ciphertext']);

        // 4. Store the encryption key (encrypted with APP_KEY)
        // In a production app, we would decrypt with APP_KEY. Since Laravel's decrypt() helper handles APP_KEY encryption natively:
        $encryptedEnvelopeKey = encrypt(json_encode([
            'key' => $enc['key'],
            'iv'  => $enc['iv']
        ]));

        // 5. Generate checksum (SHA256 of plain text to allow post-decryption validation)
        $checksum = hash('sha256', $plainText);

        $document = Document::create([
            'household_id'        => $household_id,
            'uploaded_by_user_id' => Auth::id(),
            'title'               => $request->title,
            'category'            => $request->category,
            'description'         => $request->description,
            'file_type'           => $originalExt ?: 'bin',
            'file_name_original'  => $uploadedFile->getClientOriginalName(),
            'file_name_stored'    => $storedName,
            'file_path'           => $fullPath,
            'file_size_bytes'     => $uploadedFile->getSize(),
            'is_encrypted'        => true,
            'encryption_method'   => 'AES-256-CBC',
            'encryption_key_hash' => $encryptedEnvelopeKey,
            'mime_type'           => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
            'checksum'            => $checksum,
            'expiry_date'         => $request->expiry_date,
            'shared_with_roles'   => $request->shared_with_roles,
            'shared_with_users'   => $request->shared_with_users,
            'is_sensitive'        => $request->is_sensitive ?? false,
            'download_count'      => 0,
        ]);

        // 6. Log access
        DocumentAccess::create([
            'document_id'         => $document->id,
            'accessed_by_user_id' => Auth::id(),
            'action'              => 'uploaded',
            'ip_address'          => $request->ip() ?: '127.0.0.1',
            'user_agent'          => $request->userAgent(),
        ]);

        $document->load('uploadedBy');

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded and encrypted successfully',
            'data'    => $this->formatDocument($document),
        ], 201);
    }

    /**
     * GET /api/documents/{document_id}/download
     * Decrypt and stream a document for download.
     */
    public function download(Request $request, $document_id)
    {
        $doc = Document::findOrFail($document_id);
        $membership = $this->getMembership($doc->household_id);

        if (!$membership || !$this->userHasAccess($doc, $membership)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Retrieve file from disk
        if (!Storage::disk('local')->exists($doc->file_path)) {
            return response()->json(['success' => false, 'message' => 'Physical file not found.'], 404);
        }

        $cipherText = Storage::disk('local')->get($doc->file_path);
        $plainText  = $cipherText;

        if ($doc->is_encrypted) {
            // Decrypt key & IV using APP_KEY (via Laravel's decrypt helper)
            try {
                $envelope = json_decode(decrypt($doc->encryption_key_hash), true);
                $plainText = EncryptionService::decrypt($cipherText, $envelope['key'], $envelope['iv']);
                
                if ($plainText === null) {
                    return response()->json(['success' => false, 'message' => 'Decryption failed.'], 500);
                }
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Decryption key error.'], 500);
            }
        }

        // Integrity verification
        if (hash('sha256', $plainText) !== $doc->checksum) {
            return response()->json(['success' => false, 'message' => 'File integrity check failed.'], 500);
        }

        // Increment download count
        $doc->increment('download_count');

        // Log access
        DocumentAccess::create([
            'document_id'         => $doc->id,
            'accessed_by_user_id' => Auth::id(),
            'action'              => 'downloaded',
            'ip_address'          => $request->ip() ?: '127.0.0.1',
            'user_agent'          => $request->userAgent(),
        ]);

        return response($plainText, 200, [
            'Content-Type'        => $doc->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $doc->file_name_original . '"',
        ]);
    }

    /**
     * DELETE /api/documents/{document_id}
     * Remove metadata, log access, and delete physical file from disk.
     */
    public function destroy(Request $request, $document_id)
    {
        $doc = Document::findOrFail($document_id);
        $membership = $this->getMembership($doc->household_id);

        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Only admins, co-admins, or the uploader can delete
        if (!$membership->isAdminOrCoAdmin() && $doc->uploaded_by_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins, co-admins, or the document uploader can delete this document.',
            ], 403);
        }

        // Delete physical file
        if (Storage::disk('local')->exists($doc->file_path)) {
            Storage::disk('local')->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }
}
