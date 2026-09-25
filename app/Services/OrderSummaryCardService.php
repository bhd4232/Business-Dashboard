<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\MoneyFormatter;
use App\Support\PhoneLinks;

/**
 * Builds the shareable "order summary card" shown from the order view page:
 * the same content as a card (for the image) and as plain text (for
 * WhatsApp, WeChat, Messenger and Telegram).
 */
class OrderSummaryCardService
{
    /**
     * @return array{
     *     company: string,
     *     company_phone: ?string,
     *     title: string,
     *     meta: list<array{label: string, value: string}>,
     *     items: list<array{name: string, quantity: int, amount: string}>,
     *     totals: list<array{label: string, value: string, strong: bool}>,
     *     text: string,
     *     whatsapp_url: string,
     *     telegram_url: string,
     *     file_name: string,
     * }
     */
    public function build(Order $order): array
    {
        $order->loadMissing(['company', 'customer', 'items.product', 'items.productVariant', 'latestCourierBooking.provider']);

        $currency = $order->company?->currency ?: 'BDT';
        $money = fn ($amount): string => MoneyFormatter::currency((float) $amount, $currency);

        $meta = array_values(array_filter([
            ['label' => __('Invoice'), 'value' => (string) $order->order_number],
            ['label' => __('Date'), 'value' => (string) $order->order_date?->format('d M Y')],
            ['label' => __('Customer'), 'value' => (string) ($order->customer_name ?: $order->customer?->name)],
            ['label' => __('Phone'), 'value' => (string) $order->customer?->phone],
            ['label' => __('Address'), 'value' => (string) $order->customer?->address],
            ['label' => __('Status'), 'value' => Order::STATUSES[$order->status] ?? (string) str($order->status)->headline()],
            ['label' => __('Courier'), 'value' => (string) $order->latestCourierBooking?->provider?->name],
            ['label' => __('Tracking ID'), 'value' => (string) $order->latestCourierBooking?->tracking_id],
        ], fn (array $row): bool => trim($row['value']) !== ''));

        $items = $order->items
            ->map(fn (OrderItem $item): array => [
                'name' => trim(($item->product?->name ?? __('Item')).($item->variant_label ? ' ('.$item->variant_label.')' : '')),
                'quantity' => (int) $item->quantity,
                'amount' => $money((float) $item->subtotal ?: (int) $item->quantity * (float) $item->unit_price),
            ])
            ->values()
            ->all();

        $totals = array_values(array_filter([
            ['label' => __('Subtotal'), 'value' => (float) $order->subtotal, 'strong' => false, 'always' => true, 'minus' => false],
            ['label' => __('Delivery charge'), 'value' => (float) $order->shipping_fee, 'strong' => false, 'always' => false, 'minus' => false],
            ['label' => __('Discount'), 'value' => (float) $order->discount, 'strong' => false, 'always' => false, 'minus' => true],
            ['label' => __('VAT'), 'value' => (float) $order->vat, 'strong' => false, 'always' => false, 'minus' => false],
            ['label' => __('Total'), 'value' => (float) $order->total_amount, 'strong' => true, 'always' => true, 'minus' => false],
            ['label' => __('Paid'), 'value' => (float) $order->paid_amount, 'strong' => false, 'always' => false, 'minus' => false],
            ['label' => __('Due'), 'value' => (float) $order->due_amount, 'strong' => true, 'always' => true, 'minus' => false],
        ], fn (array $row): bool => $row['always'] || $row['value'] > 0));

        $totals = array_map(fn (array $row): array => [
            'label' => $row['label'],
            'value' => ($row['minus'] ? '- ' : '').$money($row['value']),
            'strong' => $row['strong'],
        ], $totals);

        $company = (string) ($order->company?->name ?? config('app.name'));
        $title = __('Order summary');
        $text = $this->text($company, $title, $meta, $items, $totals);

        return [
            'company' => $company,
            'company_phone' => $order->company?->phone,
            'title' => $title,
            'meta' => $meta,
            'items' => $items,
            'totals' => $totals,
            'text' => $text,
            // Straight to the customer's own chat when the order has a phone
            // number; otherwise WhatsApp asks which chat to send it to.
            'whatsapp_url' => PhoneLinks::whatsappUrl($order->customer?->phone, $text)
                ?? 'https://wa.me/?text='.rawurlencode($text),
            // Telegram's share link needs a `url`; the whole summary goes
            // there so it arrives as one message.
            'telegram_url' => 'https://t.me/share/url?url='.rawurlencode($text),
            'file_name' => 'order-'.str($order->order_number)->slug().'.png',
        ];
    }

    /**
     * @param  list<array{label: string, value: string}>  $meta
     * @param  list<array{name: string, quantity: int, amount: string}>  $items
     * @param  list<array{label: string, value: string, strong: bool}>  $totals
     */
    protected function text(string $company, string $title, array $meta, array $items, array $totals): string
    {
        $lines = ["*{$company}*", $title, ''];

        foreach ($meta as $row) {
            $lines[] = "{$row['label']}: {$row['value']}";
        }

        if ($items !== []) {
            $lines[] = '';
            $lines[] = '*'.__('Items').'*';

            foreach ($items as $index => $item) {
                $lines[] = ($index + 1).". {$item['name']} × {$item['quantity']} = {$item['amount']}";
            }
        }

        $lines[] = '';

        foreach ($totals as $row) {
            $lines[] = $row['strong']
                ? "*{$row['label']}: {$row['value']}*"
                : "{$row['label']}: {$row['value']}";
        }

        return implode("\n", $lines);
    }
}
