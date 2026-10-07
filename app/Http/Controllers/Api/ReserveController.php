<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReserveRequest;
use App\Http\Resources\ReserveResource;
use App\Models\Reserve;
use App\Services\ReserveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ReserveController extends Controller
{
    public function __construct(private ReserveService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ReserveResource::collection(
            $this->service->list($request->user(), $request->integer('hotel_id') ?: null)
        );
    }

    public function store(StoreReserveRequest $request): JsonResponse
    {
        $reserve = $this->service->create($request->validated());

        return (new ReserveResource($reserve))->response()->setStatusCode(201);
    }

    public function show(Reserve $reserve): ReserveResource
    {
        Gate::authorize('view', $reserve);

        return new ReserveResource($reserve->load(['guests', 'dailies', 'payments', 'coupon']));
    }
}
