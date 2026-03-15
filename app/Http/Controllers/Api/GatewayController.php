<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GatewayController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Gateway::ordered()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:gateways,name',
            'is_active' => 'boolean',
            'priority' => 'required|integer|min:0',
            'config' => 'nullable|array',
        ]);
        $gateway = Gateway::create($validated);
        return response()->json($gateway, 201);
    }

    public function update(Request $request, Gateway $gateway): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:gateways,name,' . $gateway->id,
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:0',
            'config' => 'nullable|array',
        ]);
        $gateway->update($validated);
        return response()->json($gateway);
    }

    public function destroy(Gateway $gateway): JsonResponse
    {
        $gateway->delete();
        return response()->json(null, 204);
    }
}
