<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AdminController extends Controller
{
    public function index()
    {
        if (request()->ajax() && request()->has('draw')) {
            $query = User::where('is_admin', true);

            return DataTables::of($query)
                ->addColumn('name', fn($u) => e($u->name))
                ->addColumn('email', fn($u) => e($u->email))
                ->addColumn('added', fn($u) => $u->created_at->format('d M Y'))
                ->addColumn('status', fn($u) => '<span class="badge bg-soft-success text-success">Admin</span>')
                ->addColumn('action', function ($u) {
                    if ($u->id === auth()->id()) {
                        return '<span class="text-muted">—</span>';
                    }

                    return '<button type="button" class="btn btn-sm btn-soft-danger remove-admin" data-id="' . $u->id . '">Remove</button>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        $totalAdmins = User::where('is_admin', true)->count();

        return view('admin.pages.admins', compact('totalAdmins'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => true,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Admin created. They can now log in with the email and password.',
            ]);
        }

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin created. They can now log in with the email and password.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            if (request()->ajax() || request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove your own admin access.',
                ], 422);
            }

            return redirect()->route('admin.admins.index')
                ->with('error', 'You cannot remove your own admin access.');
        }

        $user->update(['is_admin' => false]);

        if (request()->ajax() || request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Admin access removed.',
            ]);
        }

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin access removed.');
    }
}
