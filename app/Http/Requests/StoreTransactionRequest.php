<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['FINANCE_CREATE', 'FINANCE_EDIT']) ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'type' => ['required', Rule::in(['income', 'expense', 'transfer', 'adjustment'])],
            'transacted_on' => ['required', 'date'],
            'posting_date' => ['required', 'date'],
            'category_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'payment_method' => ['required', Rule::in(['cash', 'bank'])],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'destination_cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'destination_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'effect' => ['nullable', Rule::in(['increase', 'decrease'])],
            'original_transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'document_kinds' => ['nullable', 'array'],
            'document_kinds.*' => [Rule::enum(DocumentKind::class)],
        ];
    }
}
