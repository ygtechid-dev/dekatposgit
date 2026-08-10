<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\Biller;
use App\Models\Customer;
use App\Models\PosSetting;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\ProductUnit;
use App\Models\ProductUnitPriceLevel;
use App\Models\ProductUnitPriceTier;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\Warehouse;

/**
 * POS Simple.
 *
 * Halaman kasir ringkas (satu tabel + keyboard) yang berdiri sendiri dari
 * halaman POS lama (backend/sale/pos.blade.php). POS lama sama sekali tidak
 * diubah. Penyimpanan transaksi tetap memakai SaleController@store lewat route
 * `sales.store` dengan flag pos=1, jadi laporan, stok, kas dan jurnal
 * mengikuti alur yang sudah ada.
 */
class PosSimpleController extends Controller
{
    /** Grup pelanggan yang sedang dipilih kasir (dipakai mode harga "Level Harga"). */
    private $customer_group_id = null;

    /** Level harga yang bisa dipilih kasir di kiri atas. */
    public static function priceLevels()
    {
        return [
            1 => 'Harga 1 (Jual)',
            2 => 'Harga 2 (Grosir)',
            3 => 'Harga 3 (Gudang)',
        ];
    }

    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('sales-add')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $warehouse_list = Warehouse::sellableList();
        $customer_list = Customer::where('is_active', true)->select('id', 'name', 'phone_number')->get();
        $biller_list = Biller::where('is_active', true)->select('id', 'name')->get();
        $pos_setting = PosSetting::latest()->first();

        // gudang default: gudang yang dikunci admin untuk kasir, kalau tidak ada ambil yang pertama
        $default_warehouse_id = Auth::user()->warehouse_id;
        if (!$default_warehouse_id || !Warehouse::isSellable($default_warehouse_id)) {
            $default_warehouse_id = optional($warehouse_list->first())->id;
        }

        $default_customer_id = $pos_setting ? $pos_setting->customer_id : null;
        if (!$default_customer_id || !$customer_list->contains('id', $default_customer_id)) {
            $default_customer_id = optional($customer_list->first())->id;
        }

        // Biller default harus benar-benar ada. Kalau pos_settings menunjuk biller
        // yang sudah dihapus/dinonaktifkan, transaksi tersimpan dengan biller_id
        // yatim dan halaman struk mati ("company_name on null").
        $default_biller_id = $pos_setting ? $pos_setting->biller_id : null;
        if (!$default_biller_id || !$biller_list->contains('id', $default_biller_id)) {
            $default_biller_id = optional($biller_list->first())->id
                ?: optional(Biller::defaultForTransaction())->id;
        }

        // dipakai modal "Detail Kasir" untuk menentukan baris rekap mana yang tampil
        $options = ($pos_setting && $pos_setting->payment_options)
            ? explode(',', $pos_setting->payment_options)
            : [];

        $price_levels = self::priceLevels();

        return view('backend.sale.pos_simple', compact(
            'warehouse_list',
            'customer_list',
            'biller_list',
            'default_warehouse_id',
            'default_customer_id',
            'default_biller_id',
            'price_levels',
            'options'
        ));
    }

    /**
     * Pencarian produk untuk POS Simple (AJAX).
     *
     * Query param: q, warehouse_id, level (1|2|3), exact (0|1).
     * exact=1 dipakai barcode scanner: hanya cocok persis pada kode/barcode.
     */
    public function search(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('sales-add')) {
            return response()->json([], 403);
        }

        $q = trim((string) $request->input('q'));
        $warehouse_id = (int) $request->input('warehouse_id');
        $level = (int) $request->input('level', 1);
        $exact = (int) $request->input('exact', 0);
        $this->customer_group_id = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::select('customer_group_id')->find($request->input('customer_id'));
            $this->customer_group_id = $customer ? $customer->customer_group_id : null;
        }

        if ($q === '') {
            return response()->json([]);
        }

        $rows = [];

        // --- produk tanpa varian -------------------------------------------------
        $products = Product::where('is_active', true)
            ->whereNull('is_variant')
            ->where(function ($query) use ($q, $exact) {
                if ($exact) {
                    $query->where('code', $q);
                } else {
                    $query->where('code', 'like', $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                }
            })
            ->limit(30)
            ->get();

        foreach ($products as $product) {
            $rows[] = $this->rowFromProduct($product, $product->code, $product->name, 0, $warehouse_id, $level, null);
        }

        // --- produk bervarian: dicari lewat item_code / nama varian --------------
        if (count($rows) < 30) {
            $variants = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')
                ->join('variants', 'variants.id', '=', 'product_variants.variant_id')
                ->where('products.is_active', true)
                ->where(function ($query) use ($q, $exact) {
                    if ($exact) {
                        $query->where('product_variants.item_code', $q);
                    } else {
                        $query->where('product_variants.item_code', 'like', $q . '%')
                            ->orWhere('products.name', 'like', '%' . $q . '%');
                    }
                })
                ->select(
                    'products.*',
                    'product_variants.item_code as v_item_code',
                    'product_variants.additional_price as v_additional_price',
                    'product_variants.variant_id as v_variant_id',
                    'variants.name as v_name'
                )
                ->limit(30 - count($rows))
                ->get();

            foreach ($variants as $variant) {
                $product = Product::find($variant->id);
                if (!$product) {
                    continue;
                }
                $rows[] = $this->rowFromProduct(
                    $product,
                    $variant->v_item_code,
                    $product->name . ' [' . $variant->v_name . ']',
                    (float) $variant->v_additional_price,
                    $warehouse_id,
                    $level,
                    $variant->v_variant_id
                );
            }
        }

        return response()->json($rows);
    }

    /**
     * Susun satu baris hasil pencarian.
     */
    private function rowFromProduct($product, $code, $name, $additional_price, $warehouse_id, $level, $variant_id)
    {
        $warehouse_row = null;
        if ($warehouse_id) {
            $warehouse_row = Product_Warehouse::where('product_id', $product->id)
                ->where('warehouse_id', $warehouse_id)
                ->when($variant_id, function ($query) use ($variant_id) {
                    return $query->where('variant_id', $variant_id);
                })
                ->selectRaw('SUM(qty) as qty, MAX(price) as price')
                ->first();
        }

        $stock = $warehouse_row ? (float) $warehouse_row->qty : 0;
        $warehouse_price = ($warehouse_row && $warehouse_row->price > 0) ? (float) $warehouse_row->price : 0;

        $base = (float) $product->price + (float) $additional_price;
        if ($level == 2) {
            $price = ((float) $product->wholesale_price > 0) ? (float) $product->wholesale_price + $additional_price : $base;
        } elseif ($level == 3) {
            $price = $warehouse_price > 0 ? $warehouse_price + $additional_price : $base;
        } else {
            $price = $base;
        }

        $units = $this->saleUnits($product, $price);

        return [
            'id'         => $product->id,
            'code'       => $code,
            'name'       => $name,
            'unit'       => count($units) ? $units[0]['name'] : 'n/a',
            'price'      => count($units) ? $units[0]['price'] : round($price, 2),
            'base_price' => round($price, 2),
            'units'      => $units,
            'stock'      => $stock,
            'is_variant' => $product->is_variant ? 1 : 0,
        ];
    }

    /**
     * Daftar satuan jual produk beserta harganya.
     *
     * Urutan mengikuti POS lama: satuan jual utama (sale_unit_id) selalu di depan.
     * `stock_factor` dipakai POS Simple untuk menampilkan sisa stok dalam satuan
     * yang dipilih (stok disimpan dalam satuan dasar).
     */
    private function saleUnits($product, $base_price)
    {
        if ($product->type != 'standard') {
            return [[
                'id'           => 0,
                'name'         => 'n/a',
                'price'        => round($base_price, 2),
                'stock_factor' => 1,
                'tiers'        => [],
            ]];
        }

        $query = Unit::where(function ($q) use ($product) {
            $q->where('base_unit', $product->unit_id)
                ->orWhere('id', $product->unit_id);
        });
        if ($product->product_sale_unit_ids) {
            $sale_unit_ids = array_filter(explode(',', $product->product_sale_unit_ids));
            if (count($sale_unit_ids)) {
                $query->whereIn('id', $sale_unit_ids);
            }
        }
        $unit_rows = $query->get();

        if ($unit_rows->isEmpty()) {
            $fallback = $product->unit_id ? Unit::find($product->unit_id) : null;
            if (!$fallback) {
                return [[
                    'id'           => 0,
                    'name'         => 'n/a',
                    'price'        => round($base_price, 2),
                    'stock_factor' => 1,
                    'tiers'        => [],
                ]];
            }
            $unit_rows = collect([$fallback]);
        }

        $units = [];
        foreach ($unit_rows as $unit) {
            $factor = $this->unitFactor($unit);
            $row = [
                'id'           => $unit->id,
                'name'         => $unit->unit_name,
                'price'        => round($this->unitPrice($product, $unit, $base_price, $factor), 2),
                'stock_factor' => $factor,
                'tiers'        => $this->unitTiers($product, $unit),
            ];
            // satuan jual utama ditaruh paling depan
            if ($product->sale_unit_id == $unit->id) {
                array_unshift($units, $row);
            } else {
                $units[] = $row;
            }
        }

        return $units;
    }

    /**
     * Berapa satuan dasar yang terkandung dalam 1 satuan ini.
     * Mengikuti aturan konversi SaleController@store: operator '*' mengalikan,
     * '/' membagi.
     */
    private function unitFactor($unit)
    {
        $value = (float) $unit->operation_value;
        if ($value <= 0) {
            return 1;
        }
        if ($unit->operator == '*') {
            return $value;
        }
        if ($unit->operator == '/') {
            return 1 / $value;
        }

        return 1;
    }

    /**
     * Harga satu satuan.
     *
     * Mode harga "Satuan"/"Level Harga"/"Jumlah" memakai harga yang diisi di
     * modul produk (product_units / product_unit_price_levels). Kalau tidak ada,
     * harga dihitung dari harga dasar dikali faktor konversi satuan.
     */
    private function unitPrice($product, $unit, $base_price, $factor)
    {
        if (in_array($product->price_mode, ['unit', 'level', 'qty'])) {
            $product_unit = ProductUnit::where('product_id', $product->id)
                ->where('unit_id', $unit->id)
                ->first();

            if ($product_unit) {
                if ($product->price_mode == 'level' && $this->customer_group_id) {
                    $level = ProductUnitPriceLevel::where('product_unit_id', $product_unit->id)
                        ->where('customer_group_id', $this->customer_group_id)
                        ->first();
                    if ($level && $level->price > 0) {
                        return (float) $level->price;
                    }
                }
                if ($product_unit->price > 0) {
                    return (float) $product_unit->price;
                }
            }
        }

        return $base_price * $factor;
    }

    /**
     * Harga bertingkat berdasarkan jumlah beli (mode harga "Jumlah").
     * Dikirim ke POS Simple supaya harga bisa menyesuaikan saat qty diketik.
     */
    private function unitTiers($product, $unit)
    {
        if ($product->price_mode != 'qty') {
            return [];
        }
        $product_unit = ProductUnit::where('product_id', $product->id)
            ->where('unit_id', $unit->id)
            ->first();
        if (!$product_unit) {
            return [];
        }

        return ProductUnitPriceTier::where('product_unit_id', $product_unit->id)
            ->where('price', '>', 0)
            ->orderBy('min_qty')
            ->get()
            ->map(function ($tier) {
                return [
                    'min_qty' => (float) $tier->min_qty,
                    'price'   => round((float) $tier->price, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Barang promo yang aktif hari ini, untuk panel Promosi di POS Simple.
     */
    public function promos(Request $request)
    {
        $today = date('Y-m-d');
        $warehouse_id = (int) $request->input('warehouse_id');

        $products = Product::where('is_active', true)
            ->where('promotion', 1)
            ->where('promotion_price', '>', 0)
            ->where(function ($query) use ($today) {
                $query->whereNull('starting_date')->orWhere('starting_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('last_date')->orWhere('last_date', '>=', $today);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        $rows = [];
        foreach ($products as $product) {
            $stock = 0;
            if ($warehouse_id) {
                $stock = (float) Product_Warehouse::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse_id)
                    ->sum('qty');
            }
            $rows[] = [
                'code'      => $product->code,
                'name'      => $product->name,
                'price'     => round((float) $product->price, 2),
                'promo'     => round((float) $product->promotion_price, 2),
                'last_date' => $product->last_date,
                'stock'     => $stock,
            ];
        }

        return response()->json($rows);
    }
}
