<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'جلسة') — إدارة الاشتراكات</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body>
@auth
<aside class="sidebar"><a class="brand" href="/dashboard"><span class="brand-icon">ج</span> جلسة <small>مساحة الإدارة</small></a><div class="nav-label">مساحة العمل</div><nav>
<a class="{{ request()->is('dashboard') ? 'selected' : '' }}" href="/dashboard">◈ <span>نظرة عامة</span></a>
@foreach(['mailboxes' => ['الإيميلات', 'mailboxes.manage'], 'subscriptions' => ['الاشتراكات', 'subscriptions.manage'], 'domains' => ['الدومينات', 'domains.manage'], 'services' => ['الخدمات', 'services.manage'], 'users' => ['المستخدمون', 'users.manage']] as $key => [$label, $permission])
@if(auth()->user()->permits($permission))<a class="{{ request()->is('manage/'.$key.'*') ? 'selected' : '' }}" href="/manage/{{ $key }}">{{ ['mailboxes'=>'✉','subscriptions'=>'▤','domains'=>'◎','services'=>'▦','users'=>'♧'][$key] }} <span>{{ $label }}</span></a>@endif
@endforeach
@if(auth()->user()->permits('mail.read') || auth()->user()->permits('codes.view'))<a class="{{ request()->is('inbox') ? 'selected' : '' }}" href="/inbox">↙ <span>البريد والأكواد</span></a>@endif
</nav><div class="sidebar-foot"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div>{{ auth()->user()->name }}<small>{{ auth()->user()->is_admin ? 'مدير النظام' : 'عضو الفريق' }}</small></div><form action="/logout" method="post">@csrf<button class="logout" title="تسجيل الخروج">↪</button></form></div></aside>
@endauth
<div class="{{ auth()->check() ? 'workspace' : 'guest-workspace' }}">
@auth<header class="topbar"><span>مساحة العمل <span class="muted">/ @yield('title', 'نظرة عامة')</span></span><span class="muted">{{ now()->format('Y/m/d') }} <span class="dot"></span> جلسة</span></header>@endauth
<main>
@if(session('success'))<div class="notice success">{{ session('success') }}</div>@endif
@if(session('customer_url'))<div class="card"><label>رابط العميل — احتفظ به وشاركه مع العميل المخصص له</label><input dir="ltr" readonly value="{{ session('customer_url') }}" onclick="this.select()"></div>@endif
@if($errors->any())<div class="notice error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main><footer>جلسة · إدارة الحسابات والاشتراكات</footer></div>
@stack('scripts')
</body></html>
