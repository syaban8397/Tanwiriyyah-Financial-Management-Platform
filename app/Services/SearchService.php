<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;

class SearchService
{
    public function __construct(private ReportBuilder $reports) {}

    public function search(User $user, string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';
        $groups = [];

        if ($user->hasPermission('FINANCE_VIEW')) {
            $groups[] = [
                'category' => 'Transaksi',
                'items' => Transaction::query()
                    ->with('unit:id,code')
                    ->visibleTo($user)
                    ->where(function ($query) use ($like) {
                        $query->where('number', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhere('reference', 'like', $like);
                    })
                    ->latest('id')
                    ->limit(6)
                    ->get()
                    ->map(fn (Transaction $transaction) => [
                        'label' => $transaction->number,
                        'meta' => $transaction->unit?->code.' · '.$transaction->description,
                        'href' => '/transactions/'.$transaction->id,
                    ])->all(),
            ];

            $groups[] = [
                'category' => 'Akun',
                'items' => Account::query()
                    ->where('is_active', true)
                    ->where(function ($query) use ($like) {
                        $query->where('code', 'like', $like)->orWhere('name', 'like', $like);
                    })
                    ->orderBy('code')
                    ->limit(5)
                    ->get()
                    ->map(fn (Account $account) => [
                        'label' => $account->code.' '.$account->name,
                        'meta' => $account->type->label(),
                        'href' => '/ledger?account_id='.$account->id,
                    ])->all(),
            ];

            $groups[] = [
                'category' => 'Dokumen',
                'items' => Document::query()
                    ->with('transaction:id,number,unit_id')
                    ->whereHas('transaction', fn ($query) => $query->visibleTo($user))
                    ->where('original_name', 'like', $like)
                    ->latest('id')
                    ->limit(5)
                    ->get()
                    ->map(fn (Document $document) => [
                        'label' => $document->original_name,
                        'meta' => $document->transaction?->number,
                        'href' => '/documents/'.$document->id,
                    ])->all(),
            ];
        }

        if ($user->unit_id === null) {
            $groups[] = [
                'category' => 'Unit',
                'items' => Unit::query()
                    ->where('is_active', true)
                    ->where(function ($query) use ($like) {
                        $query->where('code', 'like', $like)->orWhere('name', 'like', $like);
                    })
                    ->orderBy('code')
                    ->limit(6)
                    ->get()
                    ->map(fn (Unit $unit) => [
                        'label' => $unit->code,
                        'meta' => $unit->name,
                        'href' => '/transactions?unit_id='.$unit->id,
                    ])->all(),
            ];
        }

        if ($user->hasPermission('USER_MANAGE')) {
            $groups[] = [
                'category' => 'Pengguna',
                'items' => User::query()
                    ->where(function ($query) use ($like) {
                        $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    })
                    ->orderBy('name')
                    ->limit(5)
                    ->get()
                    ->map(fn (User $found) => [
                        'label' => $found->name,
                        'meta' => $found->email,
                        'href' => '/admin/users',
                    ])->all(),
            ];
        }

        if ($user->hasPermission('REPORT_VIEW')) {
            $reports = collect($this->reports->catalog())
                ->filter(fn ($report) => str_contains(mb_strtolower($report['title'].$report['description']), mb_strtolower($term)))
                ->take(4)
                ->map(fn ($report) => [
                    'label' => $report['title'],
                    'meta' => $report['group'],
                    'href' => '/reports?report='.$report['key'],
                ])->values()->all();

            $groups[] = ['category' => 'Laporan', 'items' => $reports];
        }

        return array_values(array_filter($groups, fn ($group) => $group['items'] !== []));
    }
}
