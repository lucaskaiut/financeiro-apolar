<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardService $service) {}

    public function summary(Request $request): JsonResponse
    {
        $costCenterId = $request->string('cost_center_id')->toString() ?: null;

        return $this->success($this->service->summary($costCenterId));
    }
}
