@extends('layouts.app')
@section('title','استقبال الكود')
@section('content')
<div class="{{ auth()->check()?'':'guest-brand' }}">{{ auth()->check()?'':'جلسة' }}</div>
<div class="card receive" id="receiver" data-key="{{ $token ? 'public-'.$token : 'internal-'.$subscription->id }}" data-link-url="{{ $token ? '/receive/'.$token.'/link' : '/subscriptions/'.$subscription->id.'/link' }}">
<span class="eyebrow">{{ $subscription->service->name }}</span><h1>كود الدخول، بكل سهولة</h1><p class="description">اربط الإيميل بهذه الصفحة لعرض آخر كود غير مستخدم وصل خلال دقيقة، أو انتظر وصول كود جديد.</p>
<label for="email">الإيميل المرتبط بالاشتراك</label><input id="email" type="email" dir="ltr" autocomplete="off" placeholder="name@example.com" @if(!$token)value="{{ $subscription->mailbox->address }}" readonly @endif>
<div id="status-box" class="receive-status"><span id="status" class="badge disabled">الربط غير فعال</span><div id="countdown" class="timer" dir="ltr">—</div><small id="hint">الربط متاح لمدة 10 دقائق، ويُفك بعد دقيقة من عرض الكود.</small></div>
<div id="code-box" hidden><span class="muted">الكود المستلم</span><div class="code" id="code"></div><span class="help" id="received"></span><p class="help">يبقى الكود ظاهرًا بعد فك الربط. انتهاؤه الفعلي تحدده الخدمة.</p></div>
<p id="error" role="alert" class="notice error" hidden></p><div class="actions"><button id="link" class="primary">ربط الإيميل</button><button id="copy" class="button secondary" hidden>نسخ الكود</button></div>
<p class="help">لا يمكن ربط نفس الإيميل بأكثر من صفحة في الوقت نفسه.</p></div>
@endsection
@push('scripts')<script src="{{ asset('js/receive.js') }}" defer></script>@endpush
