<?php

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Report\Services\ReportService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    public function __construct(private readonly ReportService $service) {}

    public function daily(Request $request): JsonResponse
    {
        return $this->success($this->service->daily(
            $request->string('date')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }

    public function weekly(Request $request): JsonResponse
    {
        return $this->success($this->service->weekly(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }

    public function provision(Request $request): JsonResponse
    {
        return $this->success($this->service->provision(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            (int) $request->integer('days', 30),
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }

    public function dailyExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->dailyExport(
            $request->string('date')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        );
    }

    public function weeklyExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->weeklyExport(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        );
    }

    public function provisionExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->provisionExport(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            (int) $request->integer('days', 30),
            $request->string('cost_center_id')->toString() ?: null,
        );
    }

    public function byCategory(Request $request): JsonResponse
    {
        return $this->success($this->service->byCategory(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }

    public function byCostCenter(Request $request): JsonResponse
    {
        return $this->success($this->service->byCostCenter(
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }

    public function byCategoryExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->byCategoryExport(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        );
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        return $this->success($this->service->monthlySummary(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }

    public function monthlySummaryExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->monthlySummaryExport(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        );
    }

    public function byCostCenterExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->byCostCenterExport(
            $request->string('cost_center_id')->toString() ?: null,
        );
    }

    public function cashFlowExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->service->cashFlowExport(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            (int) $request->integer('days', 30),
            $request->string('cost_center_id')->toString() ?: null,
        );
    }

    public function payablesExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $selectedIds = array_values(array_filter(explode(',', $request->string('selected_ids')->toString())));

        return $this->service->payablesExport(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
            $selectedIds,
        );
    }

    public function cashFlow(Request $request): JsonResponse
    {
        return $this->success($this->service->cashFlow(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            (int) $request->integer('days', 30),
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }

    public function payables(Request $request): JsonResponse
    {
        return $this->success($this->service->payables(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('cost_center_id')->toString() ?: null,
        ));
    }
}
