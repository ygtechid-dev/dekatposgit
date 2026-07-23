<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Warehouse;
use App\Models\Rack;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Product_Warehouse;
use Auth;
use DB;
use Spatie\Permission\Models\Role;

/**
 * Handles the "Plan Toko" / "Plan Gudang" screens: assigning products to a
 * physical rack (Rak) within a specific warehouse, and reading back the
 * current assignments. Mirrors the flow from the POS Muawanah reference app:
 * pick a rack, search a product by code/name, add it to that rack, and the
 * table below lists every product that already has a rack assigned for the
 * selected warehouse.
 *
 * Reuses AdjustmentController::getProduct()/limsProductSearch() (routed
 * separately below) for the product search autocomplete, since that logic
 * already does exactly what's needed: list active products stocked in a
 * given warehouse, and resolve a scanned/typed code back to a product.
 */
class RackPlanController extends Controller
{
    /**
     * Plan Toko/Plan Gudang reuses the 'adjustment' permission, but racks are
     * actually managed from the Warehouse page ('warehouse' permission,
     * see RackController which has no permission gate of its own). A role
     * that can manage racks there but lacks 'adjustment' would otherwise be
     * able to create racks yet never see/reach the page that assigns
     * products into them - so either permission is accepted here.
     */
    private function checkAccess()
    {
        $role = Role::find(Auth::user()->role_id);
        return $role && ($role->hasPermissionTo('adjustment') || $role->hasPermissionTo('warehouse'));
    }

    public function planToko()
    {
        if (!$this->checkAccess())
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $lims_warehouse_list = Warehouse::where(['is_active' => true, 'type' => 'toko'])->get();
        $page_title = 'Plan Toko';
        $warehouse_type = 'toko';
        return view('backend.rack.plan', compact('lims_warehouse_list', 'page_title', 'warehouse_type'));
    }

    public function planGudang()
    {
        if (!$this->checkAccess())
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $lims_warehouse_list = Warehouse::where(['is_active' => true, 'type' => 'gudang'])->get();
        $page_title = 'Plan Gudang';
        $warehouse_type = 'gudang';
        return view('backend.rack.plan', compact('lims_warehouse_list', 'page_title', 'warehouse_type'));
    }

    /**
     * Products that already have a rack assigned for the given warehouse.
     */
    public function list($warehouse_id)
    {
        $rows = DB::table('product_warehouse')
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->leftJoin('racks', 'product_warehouse.rack_id', '=', 'racks.id')
            ->leftJoin('product_variants', function ($join) {
                $join->on('product_variants.product_id', '=', 'product_warehouse.product_id')
                     ->on('product_variants.variant_id', '=', 'product_warehouse.variant_id');
            })
            ->where([
                ['product_warehouse.warehouse_id', $warehouse_id],
                ['products.is_active', true],
            ])
            ->whereNotNull('product_warehouse.rack_id')
            ->select(
                'product_warehouse.id as id',
                'products.id as product_id',
                'product_warehouse.variant_id',
                DB::raw('COALESCE(product_variants.item_code, products.code) as barcode'),
                'products.name as nama',
                'products.price as harga',
                'product_warehouse.qty as stok',
                'product_warehouse.rack_id',
                'racks.name as rack_name'
            )
            ->orderBy('product_warehouse.id', 'desc')
            ->get();

        return response()->json($rows);
    }

    public function rackOptions($warehouse_id)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Sorry! You are not allowed to access this module'], 403);

        $warehouse = Warehouse::find($warehouse_id);
        $racks = Rack::where('warehouse_id', $warehouse_id)
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'warehouse_id']);

        if ($racks->isEmpty() && $warehouse) {
            $sameNameWarehouseIds = Warehouse::where('name', $warehouse->name)
                ->pluck('id')
                ->toArray();

            $racks = Rack::whereIn('warehouse_id', $sameNameWarehouseIds)
                ->where(function ($query) {
                    $query->where('is_active', true)
                        ->orWhereNull('is_active');
                })
                ->orderBy('name')
                ->get(['id', 'name', 'warehouse_id']);
        }

        return response()->json($racks);
    }

    public function getProductPool($warehouse_id)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Sorry! You are not allowed to access this module'], 403);

        $simpleProducts = Product::where([
                ['is_active', true],
                ['type', 'standard']
            ])
            ->whereNull('is_variant')
            ->select('id', 'name', 'code', 'cost')
            ->orderBy('name')
            ->get();

        $variantProducts = Product::join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->where([
                ['products.is_active', true],
                ['products.type', 'standard']
            ])
            ->whereNotNull('products.is_variant')
            ->select('products.id', 'products.name', 'product_variants.item_code as code', DB::raw('(products.cost + product_variants.additional_cost) as cost'))
            ->orderBy('products.name')
            ->get();

        $products = $simpleProducts->concat($variantProducts);

        return response()->json([
            $products->pluck('code')->values(),
            $products->pluck('name')->values(),
            [],
            $products->pluck('cost')->values(),
        ]);
    }

    /**
     * Assign (or move) a product into a rack for a warehouse. Creates the
     * product_warehouse row (qty 0) if the product isn't stocked there yet.
     */
    public function assign(Request $request)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Sorry! You are not allowed to access this module'], 403);

        $this->validate($request, [
            'product_id' => 'required',
            'warehouse_id' => 'required',
            'rack_id' => 'required',
        ]);

        $product_id = $request->input('product_id');
        // product_code = products.code for a simple product, or the
        // ProductVariant.item_code for a variant product. Same convention
        // AdjustmentController::store() uses to resolve the real variant_id
        // (Product_Warehouse.variant_id is a FK to `variants`, NOT to
        // product_variants.id, so it must be looked up via item_code here
        // rather than trusting an id coming straight from the client).
        $product_code = $request->input('product_code');
        $warehouse_id = $request->input('warehouse_id');
        $rack_id = $request->input('rack_id');

        $lims_product = Product::find($product_id);
        if (!$lims_product)
            return response()->json(['message' => 'Produk tidak ditemukan'], 404);

        $variant_id = null;
        $barcode = $lims_product->code;
        if ($lims_product->is_variant) {
            $lims_variant = ProductVariant::select('id', 'variant_id', 'item_code')
                ->FindExactProductWithCode($product_id, $product_code)
                ->first();
            if ($lims_variant) {
                $variant_id = $lims_variant->variant_id;
                $barcode = $lims_variant->item_code;
            }
        }

        if ($variant_id) {
            $lims_pw = Product_Warehouse::FindProductWithVariant($product_id, $variant_id, $warehouse_id)->first();
        }
        else {
            $lims_pw = Product_Warehouse::FindProductWithoutVariant($product_id, $warehouse_id)->first();
        }

        if (!$lims_pw) {
            $lims_pw = new Product_Warehouse();
            $lims_pw->product_id = $product_id;
            $lims_pw->variant_id = $variant_id;
            $lims_pw->warehouse_id = $warehouse_id;
            $lims_pw->qty = 0;
        }
        $lims_pw->rack_id = $rack_id;
        $lims_pw->save();

        $rack = Rack::find($rack_id);

        return response()->json([
            'id' => $lims_pw->id,
            'barcode' => $barcode,
            'nama' => $lims_product ? $lims_product->name : '',
            'harga' => $lims_product ? $lims_product->price : 0,
            'stok' => $lims_pw->qty,
            'rack_id' => (int) $rack_id,
            'rack_name' => $rack ? $rack->name : '',
        ]);
    }

    /**
     * Change the rack of an existing product_warehouse row (used by the
     * per-row rak dropdown + "Save" bulk update in the plan table).
     */
    public function updateRack(Request $request, $id)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Sorry! You are not allowed to access this module'], 403);

        $lims_pw = Product_Warehouse::find($id);
        if (!$lims_pw)
            return response()->json(['message' => 'Data not found'], 404);

        $lims_pw->rack_id = $request->input('rack_id') ?: null;
        $lims_pw->save();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Remove a product from its rack (keeps the stock row/qty intact, only
     * clears the rack placement).
     */
    public function remove($id)
    {
        if (!$this->checkAccess())
            return response()->json(['message' => 'Sorry! You are not allowed to access this module'], 403);

        $lims_pw = Product_Warehouse::find($id);
        if (!$lims_pw)
            return response()->json(['message' => 'Data not found'], 404);

        $lims_pw->rack_id = null;
        $lims_pw->save();

        return response()->json(['message' => 'ok']);
    }
}
