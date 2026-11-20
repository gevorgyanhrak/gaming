<?php

namespace App\Platform\Payments;

use App\Enums\FishkaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\ChipPackage;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buying Fishkas (HayBet item 6, ADR 0027). Orders snapshot their package and go through the selected provider;
 * Fishkas are credited only when that provider confirms the payment in a verified webhook — once per order (the
 * order row is locked and the ledger entry has a one-time key). There is no way to mark an order paid from the
 * client, and no provider ships: until the owner connects one, buying is unavailable and says so.
 */
class Payments
{
    /** @param iterable<PaymentProvider> $providers */
    public function __construct(
        private readonly Settings $settings,
        private readonly FishkaWallets $wallets,
        private readonly iterable $providers,
    ) {}

    /** The provider players buy through now, or null (switched off, or the selected one isn't installed). */
    public function active(): ?PaymentProvider
    {
        if (! $this->settings->get('payments.enabled')) {
            return null;
        }

        return $this->provider((string) $this->settings->get('payments.provider'));
    }

    public function provider(string $id): ?PaymentProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->id() === $id) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * An order for the package, or the same one again for the same client key (a double tap or a retry).
     *
     * @return array{order: PaymentOrder, redirect_url: string|null}
     *
     * @throws PaymentsUnavailable
     */
    public function order(User $user, ChipPackage $package, string $clientKey): array
    {
        $provider = $this->active() ?? throw new PaymentsUnavailable;
        if (! $package->is_enabled) {
            throw new PaymentsUnavailable;
        }

        $existing = PaymentOrder::query()->where('user_id', $user->id)->where('client_key', $clientKey)->first();
        if ($existing !== null) {
            return ['order' => $existing, 'redirect_url' => null];
        }

        $order = PaymentOrder::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'chip_package_id' => $package->id,
            'price_cents' => $package->price_cents,
            'currency' => $package->currency,
            'chips' => $package->totalChips(),
            'provider' => $provider->id(),
            'status' => PaymentStatus::Pending,
            'client_key' => $clientKey,
        ]);
        $checkout = $provider->checkout($order);
        $order->forceFill(['provider_reference' => $checkout->reference])->save();

        return ['order' => $order, 'redirect_url' => $checkout->redirectUrl];
    }

    /**
     * Applies a verified provider event to its order. Final states stay final (a late "failed" after "paid" changes
     * nothing); "paid" credits the order's Fishkas once. A refund is recorded for the admin; Fishkas aren't taken
     * back automatically (they may already be played).
     */
    public function apply(PaymentProvider $provider, ProviderEvent $event): ?PaymentOrder
    {
        return DB::transaction(function () use ($provider, $event): ?PaymentOrder {
            $order = PaymentOrder::query()->where('provider', $provider->id())
                ->where('provider_reference', $event->reference)->lockForUpdate()->first();
            if ($order === null) {
                return null;
            }

            $refund = $order->status === PaymentStatus::Paid && $event->status === PaymentStatus::Refunded;
            if ($order->status->isFinal() && ! $refund) {
                return $order;
            }

            if ($event->status === PaymentStatus::Paid) {
                $transaction = $this->wallets->credit($order->user, $order->chips, FishkaTransactionType::Purchase, [
                    'idempotency_key' => "payment:{$order->public_id}",
                    'reference_type' => 'payment_order',
                    'reference_id' => $order->public_id,
                ]);
                $order->forceFill([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'fishka_transaction_id' => $transaction?->id,
                ])->save();

                return $order;
            }

            $order->forceFill([
                'status' => $event->status,
                'failure_reason' => $event->reason === null ? null : Str::limit($event->reason, 250),
            ])->save();

            return $order;
        });
    }
}
