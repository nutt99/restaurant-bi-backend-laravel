<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;

class MenuController extends Controller
{
    // GET /api/menus
    public function index()
    {
        $menus = Menu::all();
        return response()->json(['success' => true, 'data' => $menus]);
    }

    // GET /api/menus/{id}
    public function show($id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json(['success' => false, 'message' => 'Menu not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $menu]);
    }

    // POST /api/menus
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $menu = Menu::create([
            'name' => $validated['name'],
            'price' => $validated['price']
        ]);

        return response()->json(['success' => true, 'data' => $menu], 201);
    }

    // PUT /api/menus/{id}
    public function update(Request $request, $id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json(['success' => false, 'message' => 'Menu not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
        ]);

        $menu->update($validated);

        return response()->json(['success' => true, 'data' => $menu]);
    }

    // DELETE /api/menus/{id}
    public function destroy($id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json(['success' => false, 'message' => 'Menu not found'], 404);
        }

        $menu->delete();

        return response()->json(['success' => true, 'message' => 'Menu deleted']);
    }
}