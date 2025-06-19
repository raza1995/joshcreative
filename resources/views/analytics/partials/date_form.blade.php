{{-- Analytics Nav Bar --}}
<nav class="nav nav-pills mb-3">
  <a class="nav-link {{ request()->routeIs('analytics.sources') ? 'active' : '' }}" href="{{ route('analytics.sources') }}">Sources</a>
  <a class="nav-link {{ request()->routeIs('analytics.products') ? 'active' : '' }}" href="{{ route('analytics.products') }}">Products</a>
  <a class="nav-link {{ request()->routeIs('analytics.landing_sites') ? 'active' : '' }}" href="{{ route('analytics.landing_sites') }}">Landing Sites</a>
    <a class="nav-link {{ request()->routeIs('analytics.funnel_fallout') ? 'active' : '' }}" href="{{ route('analytics.funnel_fallout') }}">Funnel Fallout</a>
</nav>

{{-- Date Filter Form --}}
<form class="row g-2 mb-4" method="GET">
  <div class="col-auto">
    <input type="date" name="start_date"
           value="{{ request('start_date', now()->subDays(7)->toDateString()) }}"
           class="form-control">
  </div>
  <div class="col-auto">
    <input type="date" name="end_date"
           value="{{ request('end_date', now()->toDateString()) }}"
           class="form-control">
  </div>
  <div class="col-auto">
    <button class="btn btn-primary">Apply</button>
  </div>
</form>
