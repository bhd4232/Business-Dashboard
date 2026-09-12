<?php

namespace App\Services\Crm;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Order;
use App\Services\CourierAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesQualificationService
{
    public const FIELDS = ['product_interest', 'variant_preference', 'quantity', 'budget', 'delivery_area', 'purchase_timeframe', 'language'];

    public function update(Conversation $conversation, array $input): array
    {
        $data = Validator::make($input, [
            'field' => ['required', 'in:'.implode(',', self::FIELDS)],
            'value' => ['required', 'string', 'max:250'],
            'evidence_message_id' => ['required', 'integer'],
            'evidence_quote' => ['required', 'string', 'min:1', 'max:500'],
        ])->validate();
        $evidence = $conversation->messages()->whereKey($data['evidence_message_id'])->where('direction', 'incoming')->first();
        if (! $evidence || ! str_contains((string) $evidence->body, $data['evidence_quote'])
            || ! str_contains(mb_strtolower($data['evidence_quote']), mb_strtolower($data['value']))) {
            return ['error' => 'Use an exact customer quote containing the value; do not infer confirmed preferences.'];
        }

        return DB::transaction(function () use ($conversation, $data): array {
            $locked = Conversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->lead_id) {
                if (blank($locked->contact_phone)) {
                    return ['error' => 'A phone number is required before creating a lead.'];
                }
                $lead = Lead::query()->firstOrCreate(
                    ['company_id' => $locked->company_id, 'phone' => $locked->contact_phone],
                    ['name' => $locked->contact_name ?: $locked->contact_phone, 'source' => $locked->provider === 'whatsapp' ? 'whatsapp' : 'facebook', 'status' => 'new', 'assigned_to' => $locked->assigned_to],
                );
                $locked->update(['lead_id' => $lead->getKey()]);
                $conversation->setAttribute('lead_id', $lead->getKey());
            }
            $lead = Lead::query()->where('company_id', $locked->company_id)->whereKey($locked->lead_id)->lockForUpdate()->firstOrFail();
            $facts = $lead->qualification ?? [];
            $facts[$data['field']] = ['value' => $data['value'], 'evidence_message_id' => $data['evidence_message_id'], 'quote' => $data['evidence_quote']];
            $score = min(100, count($facts) * 15);
            $summary = collect($facts)->map(fn ($fact, $field) => $field.': '.($fact['value'] ?? ''))->implode("\n");
            $lead->update(['qualification' => $facts, 'qualification_score' => $score,
                'temperature' => $lead->temperature_locked ? $lead->temperature : ($score >= 75 ? 'hot' : ($score >= 30 ? 'warm' : 'cold')), 'qualification_summary' => $summary]);
            if ($lead->temperature === 'hot' && ! $lead->assigned_to) {
                app(CourierAlertService::class)->alert((int) $lead->company_id, 'crm-hot-lead', 'lead-'.$lead->getKey(), 'Unassigned hot lead', $lead->name.' needs a sales owner.');
            }

            return ['saved' => true, 'score' => $score, 'temperature' => $lead->temperature, 'missing' => array_values(array_diff(self::FIELDS, array_keys($facts)))];
        });
    }

    public function context(Conversation $conversation): array
    {
        $lead = $conversation->lead()->where('company_id', $conversation->company_id)->first();
        $customerId = $conversation->customer_id ?: $lead?->converted_customer_id;
        $orders = $customerId ? Order::query()->where('company_id', $conversation->company_id)->where('customer_id', $customerId)->latest('id')->limit(3)->get(['order_number', 'status'])->toArray() : [];

        return ['qualification' => $lead?->qualification ?? [], 'summary' => $lead?->qualification_summary, 'recent_orders' => $orders,
            'missing' => array_values(array_diff(self::FIELDS, array_keys($lead?->qualification ?? [])))];
    }
}
