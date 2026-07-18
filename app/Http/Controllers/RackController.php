<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rack;
use App\Models\Warehouse;

class RackController extends Controller
{
    public function index($warehouse_id)
    {
        $lims_rack_list = Rack::where([
            ['warehouse_id', $warehouse_id],
            ['is_active', true]
        ])->get();
        return response()->json($lims_rack_list);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'warehouse_id' => 'required',
        ]);
        $input = $request->all();
        $input['is_active'] = true;
        $rack = Rack::create($input);
        return response()->json($rack);
    }

    public function edit($id)
    {
        $lims_rack_data = Rack::findOrFail($id);
        return response()->json($lims_rack_data);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
        ]);
        $lims_rack_data = Rack::findOrFail($id);
        $lims_rack_data->name = $request->input('name');
        $lims_rack_data->save();
        return response()->json($lims_rack_data);
    }

    public function destroy($id)
    {
        $lims_rack_data = Rack::findOrFail($id);
        $lims_rack_data->is_active = false;
        $lims_rack_data->save();
        return response()->json(['message' => 'Rack deleted successfully']);
    }
}
