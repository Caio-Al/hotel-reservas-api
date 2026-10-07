<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableRoomsRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RoomController extends Controller
{
    public function __construct(private RoomService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return RoomResource::collection(
            $this->service->list($request->user(), $request->integer('hotel_id') ?: null)
        );
    }

    public function available(AvailableRoomsRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        return RoomResource::collection(
            $this->service->available((int) $data['hotel_id'], $data['check_in'], $data['check_out'])
        );
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = $this->service->create($request->validated());

        return (new RoomResource($room))->response()->setStatusCode(201);
    }

    public function show(Room $room): RoomResource
    {
        Gate::authorize('view', $room);

        return new RoomResource($room);
    }

    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        return new RoomResource($this->service->update($room, $request->validated()));
    }

    public function destroy(Room $room): Response
    {
        Gate::authorize('delete', $room);
        $this->service->delete($room);

        return response()->noContent();
    }
}
