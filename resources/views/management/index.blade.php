@extends('layouts.app')
@section('title',$title)
@section('content')
<div class="page-heading"><div><div class="eyebrow">إدارة الحسابات</div><h1>{{ $title }}</h1><span class="muted">{{ $records->total() }} سجل</span></div><a class="button" href="/manage/{{ $resource }}/create">＋ {{ $resource==='mailboxes'?'توليد إيميل':'إضافة جديد' }}</a></div>
@if($resource==='domains')<div class="notice success">أضف الدومين هنا، ثم فعّل Email Routing ووجّه Catch-all إلى Worker في Cloudflare. «آخر استقبال» يثبت وصول رسالة إلى البرنامج، ولا يُعد فحصًا لإعدادات DNS.</div>@endif
<div class="card"><form class="filter" method="get"><input name="q" value="{{ request('q') }}" placeholder="ابحث في {{ $title }}…">@if($resource==='subscriptions')<select name="status"><option value="">كل الحالات</option>@foreach(['active'=>'فعال','expired'=>'منتهي','scheduled'=>'لم يبدأ','disabled'=>'معطل'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>@endif<button class="primary">بحث</button><a href="/manage/{{ $resource }}">مسح</a></form>
<div class="table-wrap"><table><thead><tr><th>#</th>
@if($resource==='domains')<th>الدومين</th><th>آخر استقبال</th><th>الحالة</th>
@elseif($resource==='services')<th>الخدمة</th><th>نطاقات المرسل المسموحة</th><th>الحالة</th>
@elseif($resource==='mailboxes')<th>الإيميل</th><th>المسؤول</th><th>الرسائل / الربط</th><th>الحالة</th>
@elseif($resource==='subscriptions')<th>الإيميل / الخدمة</th><th>بداية / نهاية الاشتراك</th><th>تاريخ الإنشاء</th><th>الحالة</th>
@else<th>المستخدم</th><th>البريد</th><th>الدور</th><th>الحالة</th>@endif
<th>الإجراءات</th></tr></thead><tbody>@forelse($records as $record)<tr><td class="muted">{{ $record->id }}</td>
@if($resource==='domains')<td dir="ltr">{{ $record->name }}</td><td>{{ $record->last_received_at?->format('Y/m/d H:i') ?? 'لم تصل رسائل بعد' }}</td><td><span class="badge {{ $record->is_active?'active':'disabled' }}">{{ $record->is_active?'فعال':'معطل' }}</span></td>
@elseif($resource==='services')<td>{{ $record->name }}</td><td dir="ltr">{{ implode(', ', $record->sender_domains) }}</td><td><span class="badge {{ $record->is_active?'active':'disabled' }}">{{ $record->is_active?'فعال':'معطل' }}</span></td>
@elseif($resource==='mailboxes')<td><a href="/mailboxes/{{ $record->id }}" dir="ltr">{{ $record->address }}</a><span class="subtle">{{ $record->created_at->format('Y/m/d') }}</span></td><td>{{ $record->user->name }}</td><td>{{ $record->messages_count }} / {{ $record->leases_count }}</td><td><span class="badge {{ $record->is_active?'active':'disabled' }}">{{ $record->is_active?'فعال':'معطل' }}</span> @if($record->locked_until?->isFuture())<span class="badge scheduled">مرتبط الآن</span>@endif</td>
@elseif($resource==='subscriptions')<td><a dir="ltr" href="/mailboxes/{{ $record->mailbox_id }}">{{ $record->mailbox->address }}</a><span class="subtle">{{ $record->service->name }}</span></td><td>{{ $record->starts_at->format('Y/m/d') }}<span class="subtle">{{ $record->ends_at->format('Y/m/d') }}</span></td><td>{{ $record->created_at->format('Y/m/d') }}</td><td>@include('components.subscription-status',['subscription'=>$record])</td>
@else<td>{{ $record->name }}</td><td dir="ltr">{{ $record->email }}</td><td>{{ $record->is_admin?'مدير':'مستخدم بصلاحيات محددة' }}</td><td><span class="badge {{ $record->is_active?'active':'disabled' }}">{{ $record->is_active?'فعال':'معطل' }}</span></td>@endif
<td><div class="actions"><a href="/manage/{{ $resource }}/{{ $record->id }}/edit">تعديل</a>@if($resource==='mailboxes')<a href="/mailboxes/{{ $record->id }}">التفاصيل</a>@endif
@if($resource==='subscriptions' && auth()->user()->permits('codes.view'))<a href="/subscriptions/{{ $record->id }}/receive">عرض الكود</a>@endif</div></td></tr>
@empty<tr><td colspan="8" class="empty">لا توجد سجلات مطابقة</td></tr>@endforelse</tbody></table></div>@include('components.pagination',['records'=>$records])</div>
@endsection
