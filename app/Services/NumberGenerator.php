<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGenerator
{
    /** PO-2611-0001, SJ-2611-0001, INV-2611-0001, RT-..., NR-..., PB-... (urut per bulan). */
    public static function next(string $prefix): string
    {
        $period = now()->format('ym');

        $n = DB::transaction(function () use ($prefix, $period) {
            $row = DB::table('number_sequences')
                ->where('prefix', $prefix)->where('period', $period)
                ->lockForUpdate()->first();

            if (! $row) {
                DB::table('number_sequences')->insert(['prefix' => $prefix, 'period' => $period, 'last' => 1]);

                return 1;
            }

            DB::table('number_sequences')->where('id', $row->id)->update(['last' => $row->last + 1]);

            return $row->last + 1;
        });

        return sprintf('%s-%s-%04d', $prefix, $period, $n);
    }
}
