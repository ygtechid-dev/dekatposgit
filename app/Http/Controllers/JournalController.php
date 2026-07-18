<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\ChartOfAccount;
use App\Services\JournalService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;
use DB;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role->hasPermissionTo('journal-index'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        if ($request->starting_date) {
            $starting_date = $request->starting_date;
            $ending_date = $request->ending_date;
        } else {
            $starting_date = date('Y-m-01');
            $ending_date = date('Y-m-d');
        }

        $lims_journal_all = Journal::with('details.account')
            ->whereDate('journal_date', '>=', $starting_date)
            ->whereDate('journal_date', '<=', $ending_date)
            ->orderBy('journal_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('backend.journal.index', compact('lims_journal_all', 'starting_date', 'ending_date'));
    }

    public function create()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role->hasPermissionTo('journal-add'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $lims_coa_all = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        return view('backend.journal.create', compact('lims_coa_all'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'journal_date' => 'required|date',
            'chart_of_account_id' => 'required|array|min:2',
            'debit' => 'required|array',
            'credit' => 'required|array',
        ]);

        $data = $request->all();
        $lines = [];
        foreach ($data['chart_of_account_id'] as $key => $coaId) {
            if (!$coaId) continue;
            $lines[] = [
                'chart_of_account_id' => $coaId,
                'debit' => $data['debit'][$key] ?? 0,
                'credit' => $data['credit'][$key] ?? 0,
                'note' => $data['note'][$key] ?? null,
            ];
        }

        try {
            JournalService::post($data['journal_date'], $data['description'] ?? null, $lines, 'manual', null, true);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('not_permitted', $e->getMessage());
        }

        return redirect('journals')->with('message', 'Jurnal berhasil disimpan');
    }

    public function show($id)
    {
        $lims_journal_data = Journal::with('details.account')->find($id);
        return response()->json($lims_journal_data);
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role->hasPermissionTo('journal-edit'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $lims_journal_data = Journal::with('details')->find($id);
        if (!$lims_journal_data || !$lims_journal_data->is_manual)
            return redirect('journals')->with('not_permitted', 'Hanya jurnal manual yang dapat diedit');

        $lims_coa_all = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        return view('backend.journal.edit', compact('lims_journal_data', 'lims_coa_all'));
    }

    public function update(Request $request, $id)
    {
        $lims_journal_data = Journal::find($id);
        if (!$lims_journal_data || !$lims_journal_data->is_manual)
            return redirect('journals')->with('not_permitted', 'Hanya jurnal manual yang dapat diedit');

        $this->validate($request, [
            'journal_date' => 'required|date',
            'chart_of_account_id' => 'required|array|min:2',
            'debit' => 'required|array',
            'credit' => 'required|array',
        ]);

        $data = $request->all();
        $lines = [];
        foreach ($data['chart_of_account_id'] as $key => $coaId) {
            if (!$coaId) continue;
            $lines[] = [
                'chart_of_account_id' => $coaId,
                'debit' => $data['debit'][$key] ?? 0,
                'credit' => $data['credit'][$key] ?? 0,
                'note' => $data['note'][$key] ?? null,
            ];
        }

        $filtered = array_values(array_filter($lines, function ($l) {
            return (float)($l['debit'] ?? 0) > 0 || (float)($l['credit'] ?? 0) > 0;
        }));
        $totalDebit = array_sum(array_column($filtered, 'debit'));
        $totalCredit = array_sum(array_column($filtered, 'credit'));
        if (count($filtered) < 2 || round($totalDebit, 2) !== round($totalCredit, 2)) {
            return redirect()->back()->withInput()->with('not_permitted', 'Jurnal tidak seimbang: total debit harus sama dengan total kredit.');
        }

        DB::transaction(function () use ($lims_journal_data, $data, $filtered) {
            JournalDetail::where('journal_id', $lims_journal_data->id)->delete();
            $lims_journal_data->journal_date = $data['journal_date'];
            $lims_journal_data->description = $data['description'] ?? null;
            $lims_journal_data->save();
            foreach ($filtered as $line) {
                JournalDetail::create([
                    'journal_id' => $lims_journal_data->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'debit' => (float)($line['debit'] ?? 0),
                    'credit' => (float)($line['credit'] ?? 0),
                    'note' => $line['note'] ?? null,
                ]);
            }
        });

        return redirect('journals')->with('message', 'Jurnal berhasil diperbarui');
    }

    public function deleteBySelection(Request $request)
    {
        $ids = $request['journalIdArray'];
        foreach ($ids as $id) {
            $journal = Journal::find($id);
            if ($journal && $journal->is_manual) {
                JournalDetail::where('journal_id', $journal->id)->delete();
                $journal->delete();
            }
        }
        return 'Jurnal berhasil dihapus!';
    }

    public function destroy($id)
    {
        $journal = Journal::find($id);
        if (!$journal || !$journal->is_manual)
            return redirect('journals')->with('not_permitted', 'Hanya jurnal manual yang dapat dihapus. Jurnal otomatis mengikuti transaksi aslinya.');

        JournalDetail::where('journal_id', $journal->id)->delete();
        $journal->delete();
        return redirect('journals')->with('message', 'Jurnal berhasil dihapus');
    }
}
