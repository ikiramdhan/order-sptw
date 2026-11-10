<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return view('suppliers.index', ['suppliers' => Supplier::orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request) + ['is_active' => true]);
        ActivityLog::record('supplier_created', $supplier->name, $supplier);

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} ditambahkan.");
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));
        ActivityLog::record('supplier_updated', $supplier->name, $supplier);

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} disimpan.");
    }

    public function toggle(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return back()->with('status', $supplier->is_active ? "{$supplier->name} diaktifkan." : "{$supplier->name} dinonaktifkan.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['name.required' => 'Nama supplier wajib diisi.']);
    }
}
