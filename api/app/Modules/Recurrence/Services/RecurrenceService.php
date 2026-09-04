<?php

namespace App\Modules\Recurrence\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Recurrence\Enums\RecurrenceFrequency;
use App\Modules\Recurrence\Models\Recurrence;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecurrenceService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Recurrence::query()
            ->with(['costCenter:id,uuid,name', 'category:id,uuid,name', 'subcategory:id,uuid,name'])
            ->withCount('accounts')
            ->when(filled($search), fn ($query) => $query->where('description', 'like', "%{$search}%"))
            ->orderBy('description')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{type: string, description: string, counterparty?: ?string, cost_center_id: string, category_id: string, subcategory_id?: ?string, value: numeric, frequency: string, start_date: string, end_date?: ?string, max_occurrences?: ?int, day_of_month?: ?int, status?: string}  $data
     */
    public function create(array $data): Recurrence
    {
        $recurrence = Recurrence::query()->create($data);

        $this->generateOccurrences($recurrence, Carbon::parse($data['start_date']));

        return $recurrence->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Recurrence $recurrence, array $data, string $scope = 'all'): Recurrence
    {
        $recurrence->fill($data);
        $recurrence->save();

        if (in_array($scope, ['all', 'future'], true)) {
            $from = $scope === 'future'
                ? now()->startOfDay()->max(Carbon::parse($recurrence->start_date))
                : Carbon::parse($recurrence->start_date);

            $this->regenerate($recurrence, $from);
        }

        return $recurrence->refresh();
    }

    public function delete(Recurrence $recurrence): void
    {
        $recurrence->delete();
    }

    /**
     * Regenera ocorrências abertas (não baixadas nem conciliadas) a partir de uma data.
     */
    public function regenerate(Recurrence $recurrence, Carbon $from): void
    {
        $recurrence->accounts()
            ->where('status', AccountStatus::Open->value)
            ->whereNull('reconciled_at')
            ->whereDoesntHave('settlements')
            ->whereDate('due_date', '>=', $from->toDateString())
            ->delete();

        $this->generateOccurrences($recurrence, $from);
    }

    /**
     * Gera ocorrências futuras da recorrência dentro do horizonte definido.
     */
    public function generateOccurrences(Recurrence $recurrence, Carbon $from): void
    {
        $horizon = $recurrence->end_date
            ? Carbon::parse($recurrence->end_date)
            : $from->copy()->addYear();

        foreach ($this->occurrenceDates($recurrence, $from, $horizon) as $date) {
            FinancialAccount::query()->create([
                'type' => $recurrence->type,
                'description' => $recurrence->description,
                'counterparty' => $recurrence->counterparty,
                'cost_center_id' => $recurrence->cost_center_id,
                'category_id' => $recurrence->category_id,
                'subcategory_id' => $recurrence->subcategory_id,
                'value' => $recurrence->value,
                'due_date' => $date->toDateString(),
                'recurrence_id' => $recurrence->getKey(),
                'status' => AccountStatus::Open,
            ]);
        }
    }

    /**
     * @return list<Carbon>
     */
    private function occurrenceDates(Recurrence $recurrence, Carbon $from, Carbon $horizon): array
    {
        $dates = [];
        $step = 0;

        while (true) {
            $date = $this->dateForStep($recurrence, $from, $step);

            if ($date->gt($horizon)) {
                break;
            }

            if ($recurrence->max_occurrences !== null && count($dates) >= $recurrence->max_occurrences) {
                break;
            }

            if (count($dates) >= 366) {
                break;
            }

            $dates[] = $date;
            $step++;
        }

        return $dates;
    }

    private function dateForStep(Recurrence $recurrence, Carbon $from, int $step): Carbon
    {
        return match ($recurrence->frequency) {
            RecurrenceFrequency::Daily => $from->copy()->addDays($step),
            RecurrenceFrequency::Weekly => $from->copy()->addWeeks($step),
            RecurrenceFrequency::Biweekly => $from->copy()->addWeeks($step * 2),
            RecurrenceFrequency::Bimonthly => $this->monthlyDate($from, $step * 2, $recurrence->day_of_month),
            RecurrenceFrequency::Quarterly => $this->monthlyDate($from, $step * 3, $recurrence->day_of_month),
            RecurrenceFrequency::Semiannual => $this->monthlyDate($from, $step * 6, $recurrence->day_of_month),
            RecurrenceFrequency::Annual => $this->monthlyDate($from, $step * 12, $recurrence->day_of_month),
            default => $this->monthlyDate($from, $step, $recurrence->day_of_month),
        };
    }

    private function monthlyDate(Carbon $from, int $months, ?int $dayOfMonth): Carbon
    {
        $date = $from->copy()->addMonthsNoOverflow($months);

        if ($dayOfMonth !== null && $dayOfMonth >= 1 && $dayOfMonth <= 31) {
            $date->setDay(min($dayOfMonth, $date->daysInMonth));
        }

        return $date;
    }
}
