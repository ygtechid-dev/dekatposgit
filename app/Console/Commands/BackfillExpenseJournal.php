<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Expense;
use App\Models\Journal;
use App\Services\JournalService;

/**
 * Posts the double-entry journal for every Expense that does not have one yet.
 *
 * Needed for expenses that were saved while auto-posting was silently failing
 * (missing chart_of_accounts row, missing migration, etc). Running it twice is
 * safe: expenses that already have a journal are skipped.
 *
 * Usage:
 *   php artisan journal:backfill-expense
 *   php artisan journal:backfill-expense --dry-run
 */
class BackfillExpenseJournal extends Command
{
    protected $signature = 'journal:backfill-expense {--dry-run : Tampilkan saja, jangan simpan}';

    protected $description = 'Buat jurnal otomatis untuk pengeluaran lama yang belum punya jurnal';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $posted = Journal::where('reference_type', 'expense')
            ->whereNotNull('reference_id')
            ->pluck('reference_id')
            ->unique()
            ->all();

        $expenses = Expense::whereNotIn('id', $posted ?: [0])->orderBy('id')->get();

        if ($expenses->isEmpty()) {
            $this->info('Semua pengeluaran sudah punya jurnal. Tidak ada yang perlu diproses.');
            return 0;
        }

        $this->info('Ditemukan ' . $expenses->count() . ' pengeluaran tanpa jurnal.');

        $ok = 0;
        $failed = 0;

        foreach ($expenses as $expense) {
            try {
                $debitId = JournalService::expenseDebitAccountId($expense->expense_category_id);
                $creditId = JournalService::coaIdForCashAccount($expense->account_id);

                if ($dryRun) {
                    $this->line(sprintf(
                        '[dry-run] %s | %s | Dr %s / Cr %s',
                        $expense->reference_no,
                        number_format($expense->amount, 2),
                        $debitId,
                        $creditId
                    ));
                    $ok++;
                    continue;
                }

                $journal = JournalService::postSimple(
                    $expense->created_at ?: date('Y-m-d H:i:s'),
                    'Beban ' . $expense->reference_no,
                    $debitId,
                    $creditId,
                    $expense->amount,
                    'expense',
                    $expense->id
                );

                if ($journal) {
                    $ok++;
                    $this->line('OK   ' . $expense->reference_no . ' -> ' . $journal->journal_no);
                } else {
                    $failed++;
                    $this->warn('SKIP ' . $expense->reference_no . ' (nominal 0 atau kosong)');
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error('GAGAL ' . $expense->reference_no . ': ' . $e->getMessage());
            }
        }

        $this->info('Selesai. Berhasil: ' . $ok . ', gagal/dilewati: ' . $failed . '.');

        return 0;
    }
}
