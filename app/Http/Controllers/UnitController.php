<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::orderBy('name')->get()->map(function (Unit $u) {
            $u->usage = \DB::table('items')->where('base_unit_id', $u->id)->count()
                + \DB::table('item_units')->where('unit_id', $u->id)->count();

            return $u;
        });

        return view('units.index', ['units' => $units]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', 'unique:units,name'],
        ], ['name.required' => 'Nama satuan wajib diisi.', 'name.unique' => 'Satuan ini sudah ada.']);

        $unit = Unit::create($data);
        ActivityLog::record('unit_created', $unit->name, $unit);

        return back()->with('status', "Satuan {$unit->name} ditambahkan.");
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('units', 'name')->ignore($unit->id)],
        ], ['name.required' => 'Nama satuan wajib diisi.', 'name.unique' => 'Satuan ini sudah ada.']);

        $unit->update($data);
        ActivityLog::record('unit_updated', $unit->name, $unit);

        return back()->with('status', 'Satuan diperbarui.');
    }
}
