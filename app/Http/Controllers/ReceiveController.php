<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\Subscription;
use App\Services\LeaseService;
use Illuminate\Http\Request;

class ReceiveController extends Controller
{
    public function show(string $token)
    {
        $grant = $this->grant($token);

        return view('receive', ['grant' => $grant, 'subscription' => $grant->subscription, 'token' => $token]);
    }

    public function acquirePublic(Request $request, string $token, LeaseService $leases)
    {
        $grant = $this->grant($token);
        $request->validate(['email' => 'required|email']);
        abort_unless(strtolower($request->input('email')) === $grant->mailbox->address, 422, 'الإيميل لا يطابق رابط الاشتراك');

        return response()->json($leases->acquire($grant->subscription, grant: $grant));
    }

    public function showInternal(Subscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        return view('receive', ['subscription' => $subscription, 'token' => null, 'grant' => null]);
    }

    public function acquireInternal(Subscription $subscription, LeaseService $leases)
    {
        $this->authorizeSubscription($subscription);

        return response()->json($leases->acquire($subscription, auth()->user()));
    }

    public function poll(Request $request, string $lease, LeaseService $leases)
    {
        return response()->json($leases->poll($lease, $request->header('X-Lease-Token', '')));
    }

    private function grant(string $token): AccessGrant
    {
        $grant = AccessGrant::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($grant->usable() && $grant->mailbox->is_active && $grant->mailbox->domain->is_active && $grant->subscription->service->is_active, 403, 'رابط الاشتراك منتهي أو معطل');

        return $grant;
    }

    private function authorizeSubscription(Subscription $subscription): void
    {
        abort_unless(auth()->user()->permits('codes.view'), 403);
        abort_unless(auth()->user()->is_admin || $subscription->mailbox->user_id === auth()->id(), 404);
    }
}
