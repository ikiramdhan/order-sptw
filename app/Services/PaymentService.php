<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /** Catat pembayaran invoice outlet (bisa bertahap). */
    public static function recordInvoicePayment(Invoice $invoice, int $amount, string $paidAt, string $method, ?string $notes, User $user): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $paidAt, $method, $notes, $user) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'dibatalkan') {
                throw new \DomainException('Invoice dibatalkan.');
            }
            if ($amount <= 0) {
                throw new \DomainException('Jumlah bayar harus lebih dari 0.');
            }
            if ($amount > $invoice->remaining()) {
                throw new \DomainException('Jumlah bayar melebihi sisa tagihan ('.\App\Support\Fmt::rp($invoice->remaining()).').');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'method' => $method,
                'notes' => $notes,
                'recorded_by' => $user->id,
            ]);

            $invoice->paid += $amount;
            $invoice->refreshStatus();

            ActivityLog::record('payment_recorded', "{$invoice->number}: ".\App\Support\Fmt::rp($amount)." ({$method})", $invoice, $user->id);

            return $payment;
        });
    }

    /** Catat pembayaran ke supplier. */
    public static function recordSupplierPayment(SupplierPurchase $purchase, int $amount, string $paidAt, string $method, ?string $notes, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($purchase, $amount, $paidAt, $method, $notes, $user) {
            $purchase = SupplierPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($amount <= 0) {
                throw new \DomainException('Jumlah bayar harus lebih dari 0.');
            }
            if ($amount > $purchase->remaining()) {
                throw new \DomainException('Jumlah bayar melebihi sisa tagihan ('.\App\Support\Fmt::rp($purchase->remaining()).').');
            }

            $payment = SupplierPayment::create([
                'supplier_purchase_id' => $purchase->id,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'method' => $method,
                'notes' => $notes,
                'recorded_by' => $user->id,
            ]);

            $purchase->paid += $amount;
            $purchase->refreshStatus();

            ActivityLog::record('supplier_payment', "{$purchase->number}: ".\App\Support\Fmt::rp($amount), $purchase, $user->id);

            return $payment;
        });
    }
}
