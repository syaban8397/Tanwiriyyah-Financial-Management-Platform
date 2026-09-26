<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use App\Models\Unit;
use App\Services\InquiryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InquiryController extends Controller
{
    public function index(Request $request, InquiryService $inquiry): Response
    {
        $answer = null;

        if ($request->filled('question')) {
            $answer = $inquiry->answer($request->user(), $request->string('question')->toString(), $request->only(['period_id', 'unit_id', 'left_id', 'right_id']));
        }

        return Inertia::render('Inquiry/Index', [
            'questions' => $inquiry->questions(),
            'periods' => FinancialPeriod::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'units' => Unit::query()->when($request->user()->unit_id, fn ($query) => $query->whereKey($request->user()->unit_id))->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => $request->only(['question', 'period_id', 'unit_id', 'left_id', 'right_id']),
            'answer' => $answer,
        ]);
    }
}
