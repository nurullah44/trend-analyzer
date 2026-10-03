<?php

namespace App\Http\Controllers;

use App\Read\Ledger;
use App\Read\OwnerWrites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** The three inspection pages, rendered from the Ledger only, and the owner's Verdict form. */
class InspectionController extends Controller
{
    public function __construct(private readonly Ledger $ledger) {}

    public function alarms(Request $request): View
    {
        return view('alarms', ['report' => $this->ledger->report($request->query('week'))]);
    }

    public function subject(string $slug): View
    {
        return view('subject', ['subject' => $this->ledger->subject($slug) ?? abort(404)]);
    }

    public function sources(): View
    {
        return view('sources', ['sources' => $this->ledger->sources()]);
    }

    public function verdict(Request $request, OwnerWrites $writes, int $alarm): RedirectResponse
    {
        try {
            $writes->verdict($alarm, (string) $request->input('verdict'), $request->input('note') ?: null, $request->input('magnitude') ?: null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['verdict' => $e->getMessage()]);
        }

        return back()->with('status', 'Verdict recorded.');
    }
}
