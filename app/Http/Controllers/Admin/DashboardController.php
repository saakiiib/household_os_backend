<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Household;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $totalHouseholds = Household::count();

        return view('admin.pages.dashboard', compact('totalUsers', 'totalHouseholds'));
    }
}
