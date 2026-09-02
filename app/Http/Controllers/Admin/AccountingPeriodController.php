<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingPeriodController extends Controller
{
    public function index(AccountingPeriodService $service): View
    {
        $periods = AccountingPeriod::query()
            ->with('closedBy')
            ->latest('start_date')
            ->get();

        $currentPeriod = $service->forDate(now());
        $closingGate = $currentPeriod?->isOpen()
            ? $service->closingGate($currentPeriod)
            : null;

        return view('admin.accounting.periods.index', [
            'currentUser' => request()->user(),
            'section' => 'Accounting Control',
            'periods' => $periods,
            'currentPeriod' => $currentPeriod,
            'closingGate' => $closingGate,
        ]);
    }

    public function gate(AccountingPeriod $period, AccountingPeriodService $service): View
    {
        return view('admin.accounting.periods.gate', [
            'currentUser' => request()->user(),
            'section' => 'Accounting Control',
            'period' => $period,
            'gate' => $service->closingGate($period),
        ]);
    }

    public function close(Request $request, AccountingPeriod $period, AccountingPeriodService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $service->close($period, $request->user()->id, $data['reason'] ?? null);

        return to_route('admin.accounting.periods.index')
            ->with('success', 'Accounting period closed successfully.');
    }

    public function reopen(Request $request, AccountingPeriod $period, AccountingPeriodService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $service->reopen($period, $request->user()->id, $data['reason']);

        return to_route('admin.accounting.periods.index')
            ->with('success', 'Accounting period reopened successfully.');
    }
}
