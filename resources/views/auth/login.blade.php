@extends('layouts.app')
@section('title', 'تسجيل الدخول')
@section('content')
<div class="login-card"><div class="guest-brand">جلسة<span class="dot"></span></div><div class="card"><span class="eyebrow">مساحة العمل</span><h1>أهلًا بعودتك</h1><p class="muted">سجّل دخولك لإدارة الحسابات والاشتراكات.</p><form action="/login" method="post">@csrf
<div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" dir="ltr" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
<div class="field"><label>كلمة المرور</label><input type="password" name="password" required autocomplete="current-password"></div>
<button class="primary">تسجيل الدخول ←</button></form></div></div>
@endsection
