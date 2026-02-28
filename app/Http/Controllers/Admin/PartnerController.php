<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PartnerController extends Controller
{
    public function index()
    {
        return response()->json(Partner::orderBy('display_order')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'logo'          => 'nullable|file|mimes:jpg,jpeg,png,svg|max:2048',
            'contribution'  => 'nullable|string',
            'website'       => 'nullable|url',
            'display_order' => 'nullable|integer',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('partners', 'public');
        }

        $partner = Partner::create($data);

        return response()->json(['message' => 'Partenaire ajouté.', 'partner' => $partner], 201);
    }

    public function update(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'logo'          => 'sometimes|file|mimes:jpg,jpeg,png,svg|max:2048',
            'contribution'  => 'sometimes|string',
            'website'       => 'sometimes|url',
            'display_order' => 'sometimes|integer',
        ]);

        if ($request->hasFile('logo')) {
            if ($partner->logo) Storage::disk('public')->delete($partner->logo);
            $data['logo'] = $request->file('logo')->store('partners', 'public');
        }

        $partner->update($data);

        return response()->json(['message' => 'Partenaire mis à jour.', 'partner' => $partner]);
    }

    public function destroy(int $id)
    {
        $partner = Partner::findOrFail($id);
        if ($partner->logo) Storage::disk('public')->delete($partner->logo);
        $partner->delete();

        return response()->json(['message' => 'Partenaire supprimé.']);
    }
}