<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark" @spa>
            <span class="logo-sm">
                <img src="{{ asset('uploads/company/' . $company->company_logo) }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('uploads/company/' . $company->company_logo) }}" alt="" height="25">
            </span>
        </a>
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light" @spa>
            <span class="logo-sm">
                <img src="{{ asset('uploads/company/' . $company->company_logo) }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('uploads/company/' . $company->company_logo) }}" alt="" height="25">
            </span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">

            <div id="two-column-menu">
            </div>
            <ul class="navbar-nav" id="navbar-nav">

                <li class="nav-item d-none">
                    <a class="nav-link menu-link" href="#sidebarMultilevel" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarMultilevel">
                        <i class="ri-share-line"></i> <span data-key="t-multi-level">Multi Level</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarMultilevel">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-key="t-level-1.1"> Level 1.1 </a>
                            </li>
                            <li class="nav-item">
                                <a href="#sidebarAccount" class="nav-link" data-bs-toggle="collapse" role="button"
                                    aria-expanded="false" aria-controls="sidebarAccount" data-key="t-level-1.2"> Level
                                    1.2
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarAccount">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="#" class="nav-link" data-key="t-level-2.1">
                                                Level 2.1 </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#sidebarCrm" class="nav-link" data-bs-toggle="collapse"
                                                role="button" aria-expanded="false" aria-controls="sidebarCrm"
                                                data-key="t-level-2.2"> Level 2.2
                                            </a>
                                            <div class="collapse menu-dropdown" id="sidebarCrm">
                                                <ul class="nav nav-sm flex-column">
                                                    <li class="nav-item">
                                                        <a href="#" class="nav-link" data-key="t-level-3.1"> Level
                                                            3.1
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a href="#" class="nav-link" data-key="t-level-3.2"> Level
                                                            3.2
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" @spa
                        class="nav-link {{ Route::is('dashboard') ? 'active' : '' }}">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('category.index') }}" @spa
                        class="nav-link {{ Route::is('category.index') ? 'active' : '' }}">
                        <i class="ri-folder-3-line"></i>
                        <span>Categories</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('companyDetails') }}" @spa
                        class="nav-link {{ Route::is('companyDetails') ? 'active' : '' }}">
                        <i class="ri-building-4-line"></i>
                        <span>Company Details</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('page-seo.index') }}" @spa
                        class="nav-link {{ Route::is('page-seo.index') ? 'active' : '' }}">
                        <i class="ri-search-eye-line"></i>
                        <span>Other SEO</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>