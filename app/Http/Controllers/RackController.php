<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rack;
use App\Models\Warehouse;
use Auth;
use Spatie\Permission\Models\Role;

class RackController extends Controller
{
    /**
     * Rak dikelola lewat halaman Gudang, jadi izinnya ikut 'warehouse'.
     * 'adjustment' juga diterima supaya orang yang mengatur Plan Toko/Plan
     * Gudang bisa membuat rak sendiri tanpa harus dibukakan akses Gudang -
     * sama seperti RackPlanController::checkAccess().
     */
    private function checkAccess()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role) {
            return false;
        }
        // hasPermissionTo() melempar PermissionDoesNotExist kalau nama izinnya
        // belum pernah di-seed. Ditangkap di sini supaya endpoint JSON tidak
        // balas 500 dan modal rak tidak diam tanpa pesan.
        try {
            return $role->hasPermissionTo('warehouse') || $role->hasPermissionTo('adjustment');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Halaman "Rak" tersendiri di menu Produk.
     *
     * Sebelumnya satu-satunya cara membuat rak adalah lewat tombol "Kelola
     * Rak" yang tersembunyi di dropdown Aksi pada halaman Gudang - praktis
     * tidak ketemu, dan sama sekali tidak ada kalau role-nya tidak punya
     * izin 'warehouse'. Halaman ini memberi tempat yang jelas untuk membuat,
     * mengubah nama, dan menghapus rak.
     */
    public function page()
    {
        if (!$this->checkAccess())
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $lims_warehouse_list = Warehouse::where('is_active', true)->orderBy('name')->get();

        return view('backend.rack.index', compact('lims_warehouse_list'));
    }

    public function index($warehouse_id)
    {
        $lims_rack_list = Rack::where('warehouse_id', $warehouse_id)
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->get();

        // Jumlah barang yang sudah ditempatkan di tiap rak, supaya di halaman
        // Rak kelihatan mana yang masih kosong dan mana yang tidak aman dihapus.
        $counts = \DB::table('product_warehouse')
            ->select('rack_id', \DB::raw('COUNT(*) as total'))
            ->whereIn('rack_id', $lims_rack_list->pluck('id'))
            ->groupBy('rack_id')
            ->pluck('total', 'rack_id');

        $lims_rack_list->each(function ($rack) use ($counts) {
            $rack->product_count = (int) ($counts[$rack->id] ?? 0);
        });

        return response()->json($lims_rack_list);
    }

    public function store(Request $request)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Anda tidak punya izin untuk membuat rak.'], 403);

        $this->validate($request, [
            'name' => 'required|max:255',
            'warehouse_id' => 'required',
        ]);

        // Nama rak harus unik per gudang; tanpa ini gampang dibuat dua rak
        // "A1" di gudang yang sama dan isinya jadi terpecah dua.
        $duplicate = Rack::where('warehouse_id', $request->input('warehouse_id'))
            ->where('name', $request->input('name'))
            ->where(function ($query) {
                $query->where('is_active', true)->orWhereNull('is_active');
            })
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'Rak "' . $request->input('name') . '" sudah ada di gudang/toko ini.'], 422);
        }

        $rack = Rack::create([
            'warehouse_id' => $request->input('warehouse_id'),
            'name' => $request->input('name'),
            'is_active' => true,
        ]);

        return response()->json($rack);
    }

    public function edit($id)
    {
        $lims_rack_data = Rack::findOrFail($id);
        return response()->json($lims_rack_data);
    }

    public function update(Request $request, $id)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Anda tidak punya izin untuk mengubah rak.'], 403);

        $this->validate($request, [
            'name' => 'required|max:255',
        ]);
        $lims_rack_data = Rack::findOrFail($id);

        $duplicate = Rack::where('warehouse_id', $lims_rack_data->warehouse_id)
            ->where('name', $request->input('name'))
            ->where('id', '!=', $lims_rack_data->id)
            ->where(function ($query) {
                $query->where('is_active', true)->orWhereNull('is_active');
            })
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'Rak "' . $request->input('name') . '" sudah ada di gudang/toko ini.'], 422);
        }

        $lims_rack_data->name = $request->input('name');
        $lims_rack_data->save();
        return response()->json($lims_rack_data);
    }

    public function destroy($id)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Anda tidak punya izin untuk menghapus rak.'], 403);

        $lims_rack_data = Rack::findOrFail($id);

        // Rak yang masih berisi barang tidak boleh dinonaktifkan begitu saja:
        // stoknya akan "hilang" dari Laporan Stok Rak tanpa jejak.
        $assigned = \DB::table('product_warehouse')->where('rack_id', $lims_rack_data->id)->count();
        if ($assigned > 0) {
            return response()->json([
                'message' => 'Rak ini masih dipakai ' . $assigned . ' barang. Pindahkan dulu barangnya lewat Plan Toko/Plan Gudang.'
            ], 422);
        }

        $lims_rack_data->is_active = false;
        $lims_rack_data->save();
        return response()->json(['message' => 'Rack deleted successfully']);
    }
}
