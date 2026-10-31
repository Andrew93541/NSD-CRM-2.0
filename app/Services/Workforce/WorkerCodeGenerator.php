<?php

namespace App\Services\Workforce;

use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkerCodeGenerator
{
    /**
     * Map worker_type to dynamic ID prefix:
     * - Permanent / employee -> EMP
     * - Temporary / subcontractor / direct labour -> SUB
     */
    public static function prefixForType(string $workerType): string
    {
        return match ($workerType) {
            'employee' => 'EMP',
            default => 'SUB',
        };
    }

    /**
     * Atomically generate the next unique worker code in EMP-YYYY-#### or SUB-YYYY-#### format.
     */
    public static function generate(int $companyId, string $workerType, string|\DateTimeInterface|null $joiningDate = null): string
    {
        $prefix = self::prefixForType($workerType);
        $year = $joiningDate
            ? (int) Carbon::parse($joiningDate)->format('Y')
            : (int) Carbon::now()->format('Y');

        return DB::transaction(function () use ($companyId, $prefix, $year) {
            $maxExisting = self::maxExistingSequenceInWorkers($companyId, $prefix, $year);

            if (Schema::hasTable('worker_sequences')) {
                $row = DB::table('worker_sequences')
                    ->where('company_id', $companyId)
                    ->where('prefix', $prefix)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    $nextNumber = max((int) $row->last_number, $maxExisting) + 1;
                    DB::table('worker_sequences')
                        ->where('id', $row->id)
                        ->update([
                            'last_number' => $nextNumber,
                            'updated_at' => now(),
                        ]);
                } else {
                    $nextNumber = $maxExisting + 1;
                    DB::table('worker_sequences')->insert([
                        'company_id' => $companyId,
                        'prefix' => $prefix,
                        'year' => $year,
                        'last_number' => $nextNumber,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                $nextNumber = $maxExisting + 1;
            }

            return sprintf('%s-%04d-%04d', $prefix, $year, $nextNumber);
        });
    }

    /**
     * Non-mutating preview of the next worker code for UI display.
     */
    public static function previewNext(int $companyId, string $workerType, ?int $year = null): string
    {
        $prefix = self::prefixForType($workerType);
        $year = $year ?: (int) Carbon::now()->format('Y');
        $maxExisting = self::maxExistingSequenceInWorkers($companyId, $prefix, $year);

        $seqLast = 0;
        if (Schema::hasTable('worker_sequences')) {
            $seqLast = (int) DB::table('worker_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->value('last_number');
        }

        $nextNumber = max($seqLast, $maxExisting) + 1;

        return sprintf('%s-%04d-%04d', $prefix, $year, $nextNumber);
    }

    protected static function maxExistingSequenceInWorkers(int $companyId, string $prefix, int $year): int
    {
        $pattern = sprintf('%s-%04d-', $prefix, $year);

        $codes = Worker::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', 'like', $pattern . '%')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^' . preg_quote($pattern, '/') . '(\d+)$/', (string) $code, $m)) {
                $val = (int) $m[1];
                if ($val > $max) {
                    $max = $val;
                }
            }
        }

        return $max;
    }
}

