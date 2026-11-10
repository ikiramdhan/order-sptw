<?php

namespace App\Http\Controllers;

use App\Services\ItemImportService;
use App\Services\SheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ItemImportController extends Controller
{
    private const KEY = 'item_import';

    public function form()
    {
        return view('items.import');
    }

    public function template()
    {
        return response(\App\Services\ItemTemplateXlsx::build(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="contoh-impor-barang.xlsx"',
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']], ['file.required' => 'Pilih file Excel (.xlsx) atau .csv.', 'file.max' => 'Ukuran file maksimal 5 MB.']);

        $file = $request->file('file');

        try {
            $parsed = ItemImportService::parse(SheetReader::read($file->getRealPath(), $file->getClientOriginalName()));
        } catch (\DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        session([self::KEY => $parsed['rows']]);

        return view('items.import-preview', $parsed);
    }

    public function apply(Request $request): RedirectResponse|\Illuminate\Contracts\View\View
    {
        $payload = $request->input('payload');

        // Baris yang disunting di pratinjau dikirim sebagai satu paket JSON (menghindari batas jumlah kolom form)
        if (is_string($payload) && $payload !== '') {
            $edited = json_decode($payload, true);

            if (! is_array($edited) || $edited === []) {
                return redirect()->route('items.import')->withErrors(['file' => 'Tidak ada baris untuk disimpan. Unggah file lagi.']);
            }

            try {
                $parsed = ItemImportService::parseEdited($edited);
            } catch (\DomainException $e) {
                return redirect()->route('items.import')->withErrors(['file' => $e->getMessage()]);
            }

            session([self::KEY => $parsed['rows']]);

            if ($request->input('action') !== 'save') {
                return view('items.import-preview', $parsed);
            }
        }

        $rows = session(self::KEY);

        if (! is_array($rows) || $rows === []) {
            return redirect()->route('items.import')->withErrors(['file' => 'Sesi impor sudah habis. Unggah file lagi.']);
        }

        $result = ItemImportService::apply($rows, $request->user());
        session()->forget(self::KEY);

        return redirect()->route('items.index')->with('status', "Impor selesai: {$result['new']} barang baru, {$result['update']} diperbarui".($result['skipped'] ? ", {$result['skipped']} baris dilewati karena error." : '.'));
    }
}
