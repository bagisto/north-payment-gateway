<?php

namespace Webkul\North\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Webkul\Checkout\Facades\Cart;
use Webkul\North\Payment\North;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;

class NorthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderTransactionRepository $orderTransactionRepository,
        protected InvoiceRepository $invoiceRepository,
        protected North $north,
    ) {}

    /**
     * Create a North Embedded Checkout session for the current cart.
     *
     * @return JsonResponse
     */
    public function createSession()
    {
        if (! $this->north->hasValidCredentials()) {
            return response()->json(['message' => trans('north::app.response.provide-credentials')], 400);
        }

        $cart = Cart::getCart();

        if (! $cart) {
            return response()->json(['message' => trans('north::app.response.cart-not-found')], 400);
        }

        try {
            $session = $this->north->createSession(
                round((float) $cart->grand_total, 2),
                $this->buildProducts($cart),
            );

            return response()->json(['token' => $session['token'] ?? null]);
        } catch (RequestException $e) {
            Log::error('North session creation failed.', [
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return response()->json(['message' => trans('north::app.response.session-not-found')], 400);
        } catch (\Exception $e) {
            Log::error('North session creation failed.', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => trans('north::app.response.session-not-found')], 400);
        }
    }

    /**
     * Verify the session with North and place the order on approval.
     *
     * @return JsonResponse
     */
    public function verify()
    {
        $sessionToken = request()->input('session_token');

        if (! $sessionToken) {
            return response()->json(['message' => trans('north::app.response.invalid-session')], 400);
        }

        try {
            $status = $this->north->verifySession($sessionToken);
        } catch (\Exception $e) {
            return response()->json(['message' => trans('north::app.response.verification-failed')], 400);
        }

        if (($status['status'] ?? null) !== 'Approved') {
            return response()->json(['message' => trans('north::app.response.payment-failed')], 400);
        }

        return $this->placeOrder($status);
    }

    /**
     * Receive North's asynchronous transaction webhook as a secondary confirmation.
     *
     * @return JsonResponse
     */
    public function webhook()
    {
        $rawBody = request()->getContent();

        $signature = request()->header('X-YourApp-Signature-256');

        if (! $this->north->verifyWebhookSignature($rawBody, $signature)) {
            return response()->json(['message' => trans('north::app.response.invalid-session')], 400);
        }

        $transactionId = request()->input('transaction.authCode');

        if (
            $transactionId
            && $this->orderTransactionRepository->findOneWhere(['transaction_id' => $transactionId])
        ) {
            return response()->json(['message' => 'ok']);
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Validate the cart and persist the order, invoice and transaction for an approved payment.
     *
     * @return JsonResponse
     */
    protected function placeOrder(array $status)
    {
        if (Cart::hasError()) {
            return response()->json(['message' => trans('north::app.response.cart-processed')], 400);
        }

        Cart::collectTotals();

        $cart = Cart::getCart();

        if (! $cart) {
            return response()->json(['message' => trans('north::app.response.cart-not-found')], 400);
        }

        if (! $this->validateOrder($cart)) {
            return response()->json(['message' => trans('north::app.response.payment-failed')], 400);
        }

        $data = (new OrderResource($cart))->jsonSerialize();

        $order = $this->orderRepository->create($data);

        $this->orderRepository->update(['status' => 'processing'], $order->id);

        if ($order->canInvoice()) {
            $invoiceData = ['order_id' => $order->id];

            foreach ($order->items as $item) {
                $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
            }

            $invoice = $this->invoiceRepository->create($invoiceData);

            $this->orderTransactionRepository->create([
                'transaction_id' => $status['authCode'] ?? $status['sessionToken'] ?? uniqid('north_'),
                'status' => $status['status'] ?? 'Approved',
                'type' => $order->payment->method,
                'payment_method' => $order->payment->method,
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'amount' => $order->base_grand_total,
                'data' => json_encode($status),
            ]);
        }

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

        return response()->json(['success' => true]);
    }

    /**
     * Build the line items North's hosted form lists under its Payment Summary.
     *
     * North validates the session amount against the sum of these products alone — its "tax" and
     * "serviceFee" fields are not folded into that check and are not charged — so shipping, tax
     * and any cart-level discount are each added here as their own, quantity-less line instead.
     *
     * @return array<int, array{name: string, price: float, quantity: int, logoUrl: string}>
     */
    protected function buildProducts($cart): array
    {
        $products = $cart->items
            ->map(fn ($item) => [
                'name' => $item->name,
                'price' => round((float) $item->price, 2),
                'quantity' => (int) $item->quantity,
                'logoUrl' => '',
            ])
            ->values()
            ->all();

        if ($cart->shipping_amount > 0) {
            $products[] = [
                'name' => trans('north::app.checkout.shipping-line'),
                'price' => round((float) $cart->shipping_amount, 2),
                'quantity' => 1,
                'logoUrl' => '',
            ];
        }

        if ($cart->tax_total > 0) {
            $products[] = [
                'name' => trans('north::app.checkout.tax-line'),
                'price' => round((float) $cart->tax_total, 2),
                'quantity' => 1,
                'logoUrl' => '',
            ];
        }

        if ($cart->discount_amount > 0) {
            $products[] = [
                'name' => trans('north::app.checkout.discount-line'),
                'price' => -round((float) $cart->discount_amount, 2),
                'quantity' => 1,
                'logoUrl' => '',
            ];
        }

        return $products;
    }

    /**
     * Check the cart has everything required before an order is placed.
     *
     * @return bool
     */
    protected function validateOrder($cart)
    {
        return (bool) $cart->payment
            && (bool) $cart->billing_address
            && (
                ! $cart->haveStockableItems()
                || (
                    $cart->shipping_address
                    && $cart->selected_shipping_rate
                )
            );
    }
}
