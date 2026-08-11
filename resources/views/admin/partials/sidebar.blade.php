<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
            <span class="logo-lg"><b>Household OS</b></span>
        </a>
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
            <span class="logo-lg"><b>Household OS</b></span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu">
            </div>
            <ul class="navbar-nav" id="navbar-nav">
                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ Route::is('admin.dashboard') ? 'active' : '' }}">
                        <i class="ri-dashboard-line"></i><span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ Route::is('admin.users.*') ? 'active' : '' }}">
                        <i class="ri-user-line"></i><span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.households.index') }}" class="nav-link {{ Route::is('admin.households.*') ? 'active' : '' }}">
                        <i class="ri-home-line"></i><span>Households</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.tasks.index') }}" class="nav-link {{ Route::is('admin.tasks.*') ? 'active' : '' }}">
                        <i class="ri-task-line"></i><span>Tasks</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.documents.index') }}" class="nav-link {{ Route::is('admin.documents.*') ? 'active' : '' }}">
                        <i class="ri-file-text-line"></i><span>Documents</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.renewals.index') }}" class="nav-link {{ Route::is('admin.renewals.*') ? 'active' : '' }}">
                        <i class="ri-refresh-line"></i><span>Renewals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.subscriptions.index') }}" class="nav-link {{ Route::is('admin.subscriptions.*') ? 'active' : '' }}">
                        <i class="ri-star-line"></i><span>Subscriptions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.payments.index') }}" class="nav-link {{ Route::is('admin.payments.*') ? 'active' : '' }}">
                        <i class="ri-money-dollar-circle-line"></i><span>Payments</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
