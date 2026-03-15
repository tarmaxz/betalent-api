<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(private PaymentService $paymentService) 
    {

    }

    public function index(): JsonResponse
    {
        $transactions = Transaction::with(['client', 'gateway', 'products'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client.name' => 'required|string|max:255',
            'client.email' => 'required|email|max:255',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment.card_number' => 'required|string',
            'payment.card_holder' => 'required|string',
            'payment.card_expiry' => 'required|string',
            'payment.card_cvv' => 'required|string|size:3',
        ]);
        $result = $this->paymentService->processTransaction($validated);
        return response()->json($result, $result['success'] ? 201 : 422);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load(['client', 'gateway', 'products']);
        $response = [
            'id' => $transaction->id,
            'client' => [
                'id' => $transaction->client->id,
                'name' => $transaction->client->name,
                'email' => $transaction->client->email,
            ],
            'gateway' => [
                'id' => $transaction->gateway->id,
                'name' => $transaction->gateway->name,
            ],
            'external_id' => $transaction->external_id,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'card_last_numbers' => $transaction->card_last_numbers,
            'created_at' => $transaction->created_at,
            'products' => $transaction->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'unit_price' => $product->pivot->unit_price,
                    'subtotal' => $product->pivot->subtotal,
                ];
            }),
        ];
        return response()->json($response);
    }

    public function refund(Transaction $transaction): JsonResponse
    {
        $result = $this->paymentService->refundTransaction($transaction->id);
        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
