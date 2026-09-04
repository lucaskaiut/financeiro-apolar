<?php

namespace App\Modules\CreditCard\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\CreditCard\Http\Requests\CloseCreditCardInvoiceRequest;
use App\Modules\CreditCard\Http\Requests\StoreCreditCardPurchaseRequest;
use App\Modules\CreditCard\Http\Requests\StoreCreditCardRequest;
use App\Modules\CreditCard\Http\Requests\UpdateCreditCardRequest;
use App\Modules\CreditCard\Http\Resources\CreditCardInvoiceResource;
use App\Modules\CreditCard\Http\Resources\CreditCardResource;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\CreditCard\Models\CreditCardInvoice;
use App\Modules\CreditCard\Services\CreditCardService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditCardController extends ApiController
{
    public function __construct(
        private readonly CreditCardService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CreditCard::class);

        $cards = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(CreditCardResource::collection($cards));
    }

    public function show(CreditCard $creditCard): JsonResponse
    {
        $this->authorize('view', $creditCard);

        return $this->success(CreditCardResource::make($creditCard->load('bankAccount')));
    }

    public function store(StoreCreditCardRequest $request): JsonResponse
    {
        $this->authorize('create', CreditCard::class);

        $card = $this->service->create($request->validated());

        return $this->created(CreditCardResource::make($card), 'Cartão criado com sucesso.');
    }

    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $card = $this->service->update($creditCard, $request->validated());

        return $this->success(CreditCardResource::make($card), 'Cartão atualizado com sucesso.');
    }

    public function destroy(Request $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('delete', $creditCard);

        $this->service->delete($creditCard);

        return $this->success(null, 'Cartão removido com sucesso.');
    }

    public function storePurchase(StoreCreditCardPurchaseRequest $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $purchase = $this->service->createPurchase($creditCard, $request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialCreate,
            'credit_card_purchase',
            $purchase->uuid,
            ['description' => $purchase->description, 'value' => (float) $purchase->value],
        );

        return $this->created(['id' => $purchase->uuid], 'Compra registrada com sucesso.');
    }

    public function closeInvoice(CloseCreditCardInvoiceRequest $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $invoice = $this->service->closeInvoice($creditCard, $request->validated('reference_month'));

        return $this->success(CreditCardInvoiceResource::make($invoice), 'Fatura fechada com sucesso.');
    }

    public function invoices(CreditCard $creditCard): JsonResponse
    {
        $this->authorize('view', $creditCard);

        $invoices = CreditCardInvoice::query()
            ->where('credit_card_id', $creditCard->uuid)
            ->withCount('purchases')
            ->orderByDesc('reference_month')
            ->get();

        return $this->success(CreditCardInvoiceResource::collection($invoices));
    }
}
