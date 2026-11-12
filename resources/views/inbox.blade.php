@extends('layouts.app')
@section('title','البريد والأكواد')
@section('content')
<div class="page-heading"><div><div class="eyebrow">البريد الوارد</div><h1>الرسائل والأكواد</h1><p class="description tight">كل الرسائل الواردة لإيميلاتك، مرتبة من الأحدث.</p></div></div><div class="card">@include('components.messages')</div>
@endsection
