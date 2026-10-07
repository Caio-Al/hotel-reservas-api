<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Reserve;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $service)
    {
    }

    public function index(Reserve $reserve): AnonymousResourceCollection
    {
        Gate::authorize('view', $reserve);

        return PaymentResource::collection($this->service->list($reserve));
    }

    public function store(StorePaymentRequest $request, Reserve $reserve): JsonResponse
    {
        $payment = $this->service->register($reserve, $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }
}
