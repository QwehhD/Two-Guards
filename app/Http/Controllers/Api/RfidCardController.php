<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RfidCards\StoreRfidCardRequest;
use App\Http\Requests\RfidCards\UpdateRfidCardRequest;
use App\Models\RfidCard;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class RfidCardController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all RFID cards.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', RfidCard::class);

        return response()->json(RfidCard::query()->orderBy('owner_name')->get());
    }

    /**
     * Register a new RFID card.
     */
    public function store(StoreRfidCardRequest $request): JsonResponse
    {
        $card = RfidCard::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($card, 201);
    }

    /**
     * Update an RFID card.
     */
    public function update(UpdateRfidCardRequest $request, RfidCard $rfidCard): JsonResponse
    {
        $rfidCard->update($request->validated());

        return response()->json($rfidCard);
    }

    /**
     * Delete an RFID card.
     */
    public function destroy(RfidCard $rfidCard): JsonResponse
    {
        $this->authorize('delete', $rfidCard);

        $rfidCard->delete();

        return response()->json(null, 204);
    }
}
