<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $items = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:255', 'unique:items'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('items', 'public');
        }

        $item = Item::create($validated);

        return response()->json([
            'message' => 'Barang berhasil ditambahkan',
            'item' => $item,
        ], 201);
    }

    public function show(Item $item)
    {
        return response()->json([
            'item' => $item,
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:255', 'unique:items,item_code,'.$item->id],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('items', 'public');
        }

        $item->update($validated);

        return response()->json([
            'message' => 'Barang berhasil diperbarui',
            'item' => $item,
        ]);
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return response()->json([
            'message' => 'Barang berhasil dihapus',
        ]);
    }
}