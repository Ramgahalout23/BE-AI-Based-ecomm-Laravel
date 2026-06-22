<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    /**
     * Generate a PDF invoice for a given order.
     */
    public function generateInvoice(string $orderId): \Barryvdh\DomPDF\PDF
    {
        $order = Order::with([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'payment',
        ])->findOrFail($orderId);

        $data = [
            'order' => $order,
            'company' => [
                'name' => 'THREVOLT',
                'tagline' => 'Premium Fashion & Lifestyle',
                'email' => 'support@threvolt.com',
                'logo' => '',
            ],
            'invoiceNumber' => 'INV-' . ($order->order_number ?? strtoupper(substr($order->id, 0, 12))),
            'generatedAt' => now()->format('F d, Y'),
        ];

        $pdf = Pdf::loadView('invoices.standard', $data);
        $pdf->setPaper('A4');
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        return $pdf;
    }

    /**
     * Generate invoice PDF and return as download response.
     */
    public function downloadInvoice(string $orderId): \Illuminate\Http\Response
    {
        $pdf = $this->generateInvoice($orderId);
        $order = Order::findOrFail($orderId);
        $filename = "invoice-{$order->order_number}.pdf";
        return $pdf->download($filename);
    }

    /**
     * Generate invoice PDF and stream to browser.
     */
    public function streamInvoice(string $orderId): \Illuminate\Http\Response
    {
        $pdf = $this->generateInvoice($orderId);
        return $pdf->stream();
    }
}
