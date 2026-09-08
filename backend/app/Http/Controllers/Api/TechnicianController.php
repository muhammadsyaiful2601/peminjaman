<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Technician;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    public function index()
    {
        return response()->json(Technician::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:100', 'unique:technicians,nip'],
        ]);

        return response()->json([
            'message' => 'Teknisi berhasil ditambahkan',
            'technician' => Technician::create($validated),
        ], 201);
    }

    public function update(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:100', 'unique:technicians,nip,'.$technician->id],
        ]);

        $technician->update($validated);

        return response()->json([
            'message' => 'Teknisi berhasil diperbarui',
            'technician' => $technician,
        ]);
    }

    public function destroy(Technician $technician)
    {
        $technician->delete();

        return response()->json(['message' => 'Teknisi berhasil dihapus']);
    }
}