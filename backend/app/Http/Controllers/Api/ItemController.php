<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:5012'],
        ]);

        $validated['item_code'] = $this->generateItemCode($validated['category']);

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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($item->category !== $validated['category']) {
            $validated['item_code'] = $this->generateItemCode($validated['category'], $item->id);
        }

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

    private function generateItemCode(string $category, ?int $ignoreItemId = null): string
    {
        $categoryPrefix = Str::upper(Str::substr(
            preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($category)),
            0,
            3
        ));
        $categoryPrefix = str_pad($categoryPrefix ?: 'BRG', 3, 'X');

        $query = Item::where('item_code', 'like', $categoryPrefix . '-%');
        if ($ignoreItemId !== null) {
            $query->where('id', '!=', $ignoreItemId);
        }

        $nextNumber = (int) $query->pluck('item_code')
            ->map(fn (string $itemCode) => (int) Str::afterLast($itemCode, '-'))
            ->max() + 1;

        do {
            $itemCode = sprintf('%s-%03d', $categoryPrefix, $nextNumber++);
        } while (Item::where('item_code', $itemCode)->exists());

        return $itemCode;
    }
}