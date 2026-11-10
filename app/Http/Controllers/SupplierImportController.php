<?php

namespace App\Http\Controllers;

use App\Services\SheetReader;
use App\Services\SupplierImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierImportController extends Controller
{
    private const KEY = 'supplier_import';

    public function form()
    {
        return view('suppliers.import');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:5120'],
            'paste' => ['nullable', 'string', 'max:300000'],
        ], ['file.max' => 'Ukuran file maksimal 5 MB.']);

        $paste = trim((string) $request->input('paste'));

        if (! $request->hasFile('file') && $paste === '') {
            return back()->withErrors(['paste' => 'Tempel tabel dari Google Sheets atau pilih file.'])->withInput();
        }

        try {
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $raw = SheetReader::read($file->getRealPath(), $file->getClientOriginalName());
            } else {
                $tmp = tempnam(sys_get_temp_dir(), 'sup');
                file_put_contents($tmp, $paste);
                try {
                    $raw = SheetReader::read($tmp, 'tempel.csv');
                } finally {
                    @unlink($tmp);
                }
            }
            $parsed = SupplierImportService::parse($raw);
        } catch (\DomainException $e) {
            return back()->withErrors([$request->hasFile('file') ? 'file' : 'paste' => $e->getMessage()])->withInput();
        }

        session([self::KEY => $parsed['rows']]);

        return view('suppliers.import-preview', $parsed);
    }

    public function apply(Request $request): RedirectResponse
    {
        $rows = session(self::KEY);

        if (! is_array($rows) || $rows === []) {
            return redirect()->route('suppliers.import')->withErrors(['paste' => 'Sesi impor sudah habis. Ulangi dari awal.']);
        }

        $result = SupplierImportService::apply($rows, $request->user());
        session()->forget(self::KEY);

        return redirect()->route('suppliers.index')->with('status', "Impor selesai: {$result['new']} supplier baru, {$result['update']} diperbarui".($result['skipped'] ? ", {$result['skipped']} baris dilewati karena error." : '.'));
    }
}
