<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\MailMessage;
use App\Models\SubscriptionService;
use App\Services\OtpExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InboundEmailController extends Controller
{
    public function __invoke(Request $request, OtpExtractor $extractor)
    {
        $secret = config('jalsaha.webhook_secret');
        $timestamp = $request->header('X-Jalsaha-Timestamp', '');
        abort_unless(is_string($secret) && strlen($secret) >= 32, 503);
        abort_unless(ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 401);
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        abort_unless(hash_equals($expected, $request->header('X-Jalsaha-Signature', '')), 401);
        abort_if(strlen($request->getContent()) > 150000, 413);
        $data = $request->validate([
            'recipient' => 'required|email|max:254', 'sender' => 'required|email|max:254',
            'subject' => 'required|string|max:500', 'text' => 'present|nullable|string|max:100000',
            'source_received_at' => 'required|date|before_or_equal:'.now()->addMinute()->toIso8601String(),
            'fingerprint' => 'required|regex:/^[a-f0-9]{64}$/',
        ]);
        $mailbox = Mailbox::where('address', strtolower($data['recipient']))->where('is_active', true)->whereHas('domain', fn ($q) => $q->where('is_active', true))->first();
        if (! $mailbox) {
            return response()->json(['ignored' => true], 202);
        }
        $senderDomain = strtolower(substr(strrchr($data['sender'], '@'), 1));
        $service = SubscriptionService::where('is_active', true)->get()->first(fn ($service) => collect($service->sender_domains)->contains(fn ($domain) => $senderDomain === $domain || str_ends_with($senderDomain, '.'.$domain)));
        $code = $service ? $extractor->extract($data['subject'].' '.($data['text'] ?? '')) : null;
        DB::transaction(function () use ($mailbox, $service, $data, $code) {
            MailMessage::firstOrCreate(['fingerprint' => $data['fingerprint']], [
                'mailbox_id' => $mailbox->id, 'subscription_service_id' => $service?->id,
                'sender' => strtolower($data['sender']), 'subject' => $data['subject'],
                'body' => $data['text'] ?? '', 'otp' => $code, 'received_at' => Carbon::parse($data['source_received_at'])->utc(),
            ]);
            Domain::whereKey($mailbox->domain_id)->update(['last_received_at' => now()]);
        });

        return response()->json(['accepted' => true]);
    }
}
