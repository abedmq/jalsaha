@extends('layouts.app')
@section('title','قراءة الرسالة')
@section('content')
<div class="page-heading"><div><span class="eyebrow">البريد الوارد</span><h1>{{ $message->subject }}</h1></div><a class="button secondary" href="/mailboxes/{{ $message->mailbox_id }}">رجوع للإيميل</a></div>
<div class="card"><p>من: <b dir="ltr">{{ $message->sender }}</b><br>إلى: <b dir="ltr">{{ $message->mailbox->address }}</b><br><span class="muted">{{ $message->received_at->format('Y/m/d H:i:s') }} UTC</span></p><pre class="message-body" dir="auto">{{ $message->body }}</pre></div>
@endsection
