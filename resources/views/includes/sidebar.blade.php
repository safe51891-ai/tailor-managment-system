<aside class="shop-sidebar" id="shop-navigation" aria-label="Main navigation">
<a class="shop-brand" href="{{ route('dashboard') }}">@if($shopBranding->logo_path)<img class="shop-brand-logo" src="{{ route('shop-profile.logo') }}" alt="Shop logo">@else<span class="shop-brand-mark" aria-hidden="true">{{ mb_substr($shopBranding->name, 0, 1) }}</span>@endif<span><strong>{{ $shopBranding->name }}</strong><small>THE TAILOR'S DESK</small></span></a>
<p class="shop-nav-label">DAILY WORK</p>
<nav class="shop-nav">
@foreach(['dashboard' => ['Overview', '◫', ['dashboard']], 'orders' => ['Orders', '▤', ['orders', 'orders.*']], 'customers' => ['Customers & measurements', '◉', ['customers', 'customers.*', 'measurements.*']]] as $destination => $item)
<a href="{{ route($destination) }}" class="shop-nav-link {{ Request::routeIs(...$item[2]) ? 'is-active' : '' }}" @if(Request::routeIs(...$item[2])) aria-current="page" @endif><span class="shop-nav-icon" aria-hidden="true">{{ $item[1] }}</span>{{ $item[0] }}</a>
@endforeach
</nav>
<p class="shop-nav-label mt-2">BUSINESS MODULES</p>
<nav class="shop-nav" aria-label="Business modules">
@foreach(['staff' => ['Employees', '♙', ['staff', 'designations']], 'expenses' => ['Expenses', '↗', ['expenses', 'expense-categories']], 'income' => ['Income', '↙', ['income', 'income-categories', 'dues.*']]] as $destination => $module)
<a class="shop-nav-link {{ Request::routeIs(...$module[2]) ? 'is-active' : '' }}" href="{{ route($destination) }}" @if(Request::routeIs(...$module[2])) aria-current="page" @endif><span class="shop-nav-icon" aria-hidden="true">{{ $module[1] }}</span>{{ $module[0] }}</a>
@endforeach
</nav>
<nav class="shop-nav shop-settings-nav" aria-label="Shop settings"><a class="shop-nav-link {{ Request::routeIs('shop-profile.*', 'settings') ? 'is-active' : '' }}" href="{{ route('shop-profile.edit') }}" @if(Request::routeIs('shop-profile.*', 'settings')) aria-current="page" @endif><span class="shop-nav-icon" aria-hidden="true">⚙</span>Shop settings</a></nav>
<div class="shop-sidebar-note"><span class="shop-note-dot" aria-hidden="true"></span><strong>A stitch ahead.</strong><p>Customers, measurements and orders.<br>One organised workspace.</p></div>
</aside>
<button class="shop-nav-backdrop" id="shop-nav-backdrop" type="button" aria-label="Close navigation" tabindex="-1" hidden></button>
