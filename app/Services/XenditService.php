<?php

namespace App\Services;

use App\Models\Order;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class XenditService
{
    protected InvoiceApi $invoiceApi;

    public function __construct()
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
        $this->invoiceApi = new InvoiceApi();
    }

    /**
     * Buat Xendit Invoice untuk pembayaran order.
     *
     * @return array{invoice_id: string, invoice_url: string}
     */
    public function createInvoice(Order $order): array
    {
        $payload = [
            'external_id' => $order->order_number,
            'amount' => (float) $order->total_amount,
            'currency' => 'IDR',
            'description' => "Pembayaran pesanan {$order->order_number}",
            'payment_methods' => ['QRIS', 'OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA'],
            'invoice_duration' => 86400, // 24 jam
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->menu_item_name,
                'quantity' => $item->quantity,
                'price' => (float) $item->unit_price,
            ])->toArray(),
            'success_redirect_url' => config('app.frontend_url', 'http://localhost:5173') . '/orders/' . $order->order_number . '?status=success',
            'failure_redirect_url' => config('app.frontend_url', 'http://localhost:5173') . '/orders/' . $order->order_number . '?status=failed',
        ];

        if ($order->user_id) {
            // Jika user login
            $payload['customer'] = [
                'given_names' => $order->user->name,
                'email' => $order->user->email,
            ];
        } else if ($order->guest_name) {
            // Jika guest, kirim nama saja
            $customerData = [
                'given_names' => $order->guest_name,
            ];

            $payload['customer'] = $customerData;
        }

        $request = new CreateInvoiceRequest($payload);
        $result = $this->invoiceApi->createInvoice($request);

        return [
            'invoice_id' => $result->getId(),
            'invoice_url' => $result->getInvoiceUrl(),
        ];
    }

    /**
     * Cek status invoice Xendit.
     */
    public function getInvoice(string $invoiceId): mixed
    {
        return $this->invoiceApi->getInvoiceById($invoiceId);
    }

    /**
     * Verifikasi webhook callback token dari Xendit.
     */
    public function verifyWebhookToken(string $token): bool
    {
        $secret = config('services.xendit.webhook_secret') ?? '';
        return $secret !== '' && hash_equals($secret, $token);
    }
}
