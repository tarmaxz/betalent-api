<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = Client::withCount('transactions')->get();
        return response()->json($clients);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load(['transactions.products', 'transactions.gateway']);
        $response = [
            'client' => $client,
            'transactions' => $client->transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'gateway' => $transaction->gateway->name,
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
            }),
        ];
        return response()->json($response);
    }
}
