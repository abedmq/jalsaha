<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\MailboxLease;
use App\Models\MailMessage;
use App\Models\Subscription;
use App\Models\SubscriptionService;
use App\Models\User;
use App\Services\LeaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    private const RESOURCES = [
        'domains' => [Domain::class, 'domains.manage', 'الدومينات'],
        'services' => [SubscriptionService::class, 'services.manage', 'الخدمات'],
        'mailboxes' => [Mailbox::class, 'mailboxes.manage', 'الإيميلات'],
        'subscriptions' => [Subscription::class, 'subscriptions.manage', 'الاشتراكات'],
        'users' => [User::class, 'users.manage', 'المستخدمون'],
    ];

    public function dashboard()
    {
        $ids = Mailbox::visibleTo(auth()->user())->select('id');
        $subscriptions = Subscription::whereIn('mailbox_id', clone $ids);
        $stats = [
            'الإيميلات' => (clone $ids)->count(),
            'الاشتراكات الفعالة' => (clone $subscriptions)->where('is_enabled', true)->whereDate('starts_at', '<=', today())->whereDate('ends_at', '>=', today())->count(),
            'المنتهية' => (clone $subscriptions)->whereDate('ends_at', '<', today())->count(),
            'تنتهي خلال 7 أيام' => (clone $subscriptions)->where('is_enabled', true)->whereDate('starts_at', '<=', today())->whereBetween('ends_at', [today()->toDateString(), today()->addDays(7)->toDateString()])->count(),
            'الرسائل الواردة' => MailMessage::whereIn('mailbox_id', clone $ids)->count(),
            'الأكواد المستلمة' => MailMessage::whereIn('mailbox_id', clone $ids)->whereNotNull('otp')->count(),
            'مرات الربط' => MailboxLease::whereIn('mailbox_id', clone $ids)->count(),
            'الربط النشط' => Mailbox::whereIn('id', clone $ids)->where('locked_until', '>', now())->count(),
        ];
        if (auth()->user()->is_admin) {
            $stats['المستخدمون الفعالون'] = User::where('is_active', true)->count();
        }
        $expiring = (clone $subscriptions)->with('mailbox', 'service')->where('is_enabled', true)->whereBetween('ends_at', [today()->toDateString(), today()->addDays(7)->toDateString()])->orderBy('ends_at')->limit(10)->get();
        $activity = Mailbox::visibleTo(auth()->user())->withCount(['messages', 'leases'])->latest()->limit(8)->get();
        $userStats = auth()->user()->is_admin ? User::all()->map(fn ($user) => [
            'name' => $user->name, 'active' => $user->is_active,
            'mailboxes' => Mailbox::where('user_id', $user->id)->count(),
            'messages' => MailMessage::whereHas('mailbox', fn ($q) => $q->where('user_id', $user->id))->count(),
            'leases' => MailboxLease::where('user_id', $user->id)->count(),
        ]) : collect();

        return view('dashboard', compact('stats', 'expiring', 'activity', 'userStats'));
    }

    public function index(Request $request, string $resource)
    {
        [$model, , $title] = $this->definition($resource);
        $query = $model::query();
        if ($resource === 'mailboxes') {
            $query->visibleTo($request->user())->with('domain', 'user')->withCount(['messages', 'leases']);
        }
        if ($resource === 'subscriptions') {
            $query->whereHas('mailbox', fn ($q) => $q->visibleTo($request->user()))->with('mailbox', 'service');
        }
        if ($resource === 'users' && ! $request->user()->is_admin) {
            $query->where('is_admin', false);
        }
        if ($term = $request->string('q')->toString()) {
            $query->where(function ($q) use ($term, $resource) {
                if ($resource === 'subscriptions') {
                    $q->whereHas('mailbox', fn ($q) => $q->where('address', 'like', '%'.$term.'%'))->orWhereHas('service', fn ($q) => $q->where('name', 'like', '%'.$term.'%'));
                } else {
                    $q->where($resource === 'mailboxes' ? 'address' : 'name', 'like', '%'.$term.'%');
                }
            });
        }
        if ($resource === 'subscriptions' && in_array($request->input('status'), ['active', 'expired', 'disabled', 'scheduled'], true)) {
            match ($request->input('status')) {
                'active' => $query->where('is_enabled', true)->whereDate('starts_at', '<=', today())->whereDate('ends_at', '>=', today()),
                'expired' => $query->where('is_enabled', true)->whereDate('ends_at', '<', today()),
                'scheduled' => $query->where('is_enabled', true)->whereDate('starts_at', '>', today()),
                'disabled' => $query->where('is_enabled', false),
            };
        }
        $records = $query->latest()->paginate(20)->withQueryString();

        return view('management.index', compact('resource', 'title', 'records'));
    }

    public function create(string $resource)
    {
        [$model, , $title] = $this->definition($resource);

        return $this->form($resource, new $model, $title);
    }

    public function edit(string $resource, int $id)
    {
        [$model, , $title] = $this->definition($resource);
        $record = $model::findOrFail($id);
        $this->assertVisible($resource, $record);

        return $this->form($resource, $record, $title);
    }

    private function form(string $resource, $record, string $title)
    {
        $domains = Domain::where('is_active', true)->orderBy('name')->get();
        $users = auth()->user()->is_admin ? User::orderBy('name')->get() : collect([auth()->user()]);
        $mailboxes = Mailbox::visibleTo(auth()->user())->orderBy('address')->get();
        $services = SubscriptionService::orderBy('name')->get();

        return view('management.form', compact('resource', 'record', 'title', 'domains', 'users', 'mailboxes', 'services'));
    }

    public function store(Request $request, string $resource)
    {
        [$model] = $this->definition($resource);

        return $this->save($request, $resource, new $model);
    }

    public function update(Request $request, string $resource, int $id)
    {
        [$model] = $this->definition($resource);
        $record = $model::findOrFail($id);
        $this->assertVisible($resource, $record);

        return $this->save($request, $resource, $record);
    }

    private function save(Request $request, string $resource, $record)
    {
        if ($resource === 'domains') {
            $request->merge(['name' => strtolower(trim($request->input('name', '')))]);
            $data = $request->validate(['name' => ['required', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', Rule::unique('domains')->ignore($record->id)], 'zone_id' => 'nullable|alpha_num|size:32', 'is_active' => 'required|boolean']);
            abort_if($record->exists && $record->name !== $data['name'] && Mailbox::where('domain_id', $record->id)->exists(), 422, 'لا يمكن تغيير اسم دومين عليه إيميلات؛ أضف دومينًا جديدًا');
        } elseif ($resource === 'services') {
            $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('subscription_services')->ignore($record->id)], 'sender_domains_text' => 'required|string|max:2000', 'is_active' => 'required|boolean']);
            $domains = array_values(array_unique(array_filter(array_map(fn ($value) => strtolower(trim($value)), preg_split('/[\s,]+/', $data['sender_domains_text'])))));
            validator(['domains' => $domains], ['domains' => 'required|array|min:1', 'domains.*' => ['required', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/']])->validate();
            $collision = SubscriptionService::whereKeyNot($record->id ?? 0)->get()->contains(fn ($service) => collect($service->sender_domains)->contains(fn ($existing) => collect($domains)->contains(fn ($new) => $new === $existing || str_ends_with($new, '.'.$existing) || str_ends_with($existing, '.'.$new))));
            abort_if($collision, 422, 'نطاق المرسل مستخدم في خدمة أخرى');
            unset($data['sender_domains_text']);
            $data['sender_domains'] = $domains;
        } elseif ($resource === 'mailboxes') {
            if ($record->exists) {
                $data = $request->validate(['is_active' => 'required|boolean', 'user_id' => 'required|exists:users,id']);
                if (! $request->user()->is_admin) {
                    $data['user_id'] = $record->user_id;
                }
            } else {
                $data = $request->validate(['domain_id' => 'required|exists:domains,id', 'local_part' => ['nullable', 'max:64', 'regex:/^[a-zA-Z0-9](?:[a-zA-Z0-9._-]*[a-zA-Z0-9])?$/'], 'count' => 'required|integer|min:1|max:100', 'user_id' => 'required|exists:users,id', 'is_active' => 'required|boolean']);
                $domain = Domain::findOrFail($data['domain_id']);
                abort_unless($domain->is_active, 422);
                $userId = $request->user()->is_admin ? $data['user_id'] : auth()->id();
                abort_if(! empty($data['local_part']) && $data['count'] > 1, 422, 'اترك الاسم فارغًا لتوليد مجموعة عشوائية');
                DB::transaction(function () use ($data, $domain, $userId) {
                    for ($i = 0; $i < $data['count']; $i++) {
                        $address = strtolower((($data['local_part'] ?? null) ?: Str::lower(Str::random(14))).'@'.$domain->name);
                        validator(['address' => $address], ['address' => 'required|email|max:254|unique:mailboxes,address'])->validate();
                        Mailbox::create(['domain_id' => $domain->id, 'user_id' => $userId, 'address' => $address, 'is_active' => $data['is_active']]);
                    }
                });

                return redirect('/manage/mailboxes')->with('success', 'تم إنشاء الإيميلات');
            }
        } elseif ($resource === 'subscriptions') {
            $data = $request->validate(['mailbox_id' => 'required|exists:mailboxes,id', 'subscription_service_id' => 'required|exists:subscription_services,id', 'starts_at' => 'required|date_format:Y-m-d', 'ends_at' => 'required|date_format:Y-m-d|after_or_equal:starts_at', 'is_enabled' => 'required|boolean', 'notes' => 'nullable|string|max:3000']);
            Mailbox::visibleTo($request->user())->findOrFail($data['mailbox_id']);
            if ($record->exists) {
                abort_unless($record->mailbox_id == $data['mailbox_id'] && $record->subscription_service_id == $data['subscription_service_id'], 422, 'أنشئ اشتراكًا جديدًا لتغيير الإيميل أو الخدمة');
            }
        } else {
            $data = $request->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($record->id)], 'password' => ($record->exists ? 'nullable' : 'required').'|string|min:12|confirmed', 'permissions' => 'nullable|array', 'permissions.*' => [Rule::in(array_keys(config('jalsaha.permissions')))], 'is_active' => 'required|boolean', 'is_admin' => 'required|boolean']);
            if (! $request->user()->is_admin) {
                abort_if($data['is_admin'] || array_diff($data['permissions'] ?? [], $request->user()->permissions ?? []), 403);
            }
            if ($record->id === auth()->id()) {
                abort_unless($data['is_active'] && (! $record->is_admin || $data['is_admin']), 422, 'لا يمكنك تعطيل حسابك أو إزالة دور المدير عنه');
            }
            $data['permissions'] = $data['permissions'] ?? [];
            if (empty($data['password'])) {
                unset($data['password']);
            }
        }
        $record->fill($data)->save();

        return redirect('/manage/'.$resource)->with('success', 'تم حفظ التغييرات');
    }

    public function mailbox(Mailbox $mailbox)
    {
        $this->assertVisible('mailboxes', $mailbox);
        $mailbox->load('domain', 'user', 'subscriptions.service');
        $messages = $mailbox->messages()->with('service')->latest()->paginate(20, ['*'], 'messages');
        $leases = $mailbox->leases()->with('user')->latest()->paginate(15, ['*'], 'leases');
        $grants = $mailbox->grants()->with('subscription.service')->latest()->get();

        return view('mailbox', compact('mailbox', 'messages', 'leases', 'grants'));
    }

    public function inbox()
    {
        abort_unless(auth()->user()->permits('mail.read') || auth()->user()->permits('codes.view'), 403);
        $messages = MailMessage::whereHas('mailbox', fn ($q) => $q->visibleTo(auth()->user()))->with('mailbox', 'service')->latest()->paginate(30);

        return view('inbox', compact('messages'));
    }

    public function message(MailMessage $message)
    {
        abort_unless(auth()->user()->permits('mail.read'), 403);
        $this->assertVisible('mailboxes', $message->mailbox);

        return view('message', compact('message'));
    }

    public function grant(Request $request, Subscription $subscription)
    {
        abort_unless($request->user()->permits('links.manage'), 403);
        $this->assertVisible('subscriptions', $subscription);
        abort_unless($subscription->status === 'active', 422, 'الاشتراك غير فعال');
        $token = Str::random(64);
        AccessGrant::create(['mailbox_id' => $subscription->mailbox_id, 'subscription_id' => $subscription->id, 'token_hash' => hash('sha256', $token), 'expires_at' => $subscription->ends_at->endOfDay()]);

        return back()->with('customer_url', url('/receive/'.$token))->with('success', 'تم إصدار رابط العميل. انسخه الآن؛ لن يظهر مرة أخرى.');
    }

    public function revoke(AccessGrant $grant)
    {
        abort_unless(auth()->user()->permits('links.manage'), 403);
        $this->assertVisible('mailboxes', $grant->mailbox);
        $grant->update(['revoked_at' => now()]);
        DB::transaction(function () use ($grant) {
            $mailbox = Mailbox::whereKey($grant->mailbox_id)->lockForUpdate()->firstOrFail();
            foreach (MailboxLease::where('access_grant_id', $grant->id)->whereNull('released_at')->get() as $lease) {
                app(LeaseService::class)->release($lease, $mailbox, 'revoked');
            }
        });

        return back()->with('success', 'تم إلغاء الرابط وفك الربط المرتبط به');
    }

    private function definition(string $resource): array
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        $definition = self::RESOURCES[$resource];
        abort_unless(auth()->user()->permits($definition[1]), 403);

        return $definition;
    }

    private function assertVisible(string $resource, $record): void
    {
        if (auth()->user()->is_admin) {
            return;
        }
        if ($resource === 'mailboxes') {
            abort_unless($record->user_id === auth()->id(), 404);
        }
        if ($resource === 'subscriptions') {
            abort_unless($record->mailbox->user_id === auth()->id(), 404);
        }
        if ($resource === 'users') {
            abort_if($record->is_admin, 403);
        }
    }
}
