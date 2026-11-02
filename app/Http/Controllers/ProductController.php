<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Keygen\Keygen;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Tax;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Product_Warehouse;
use App\Models\Product_Supplier;
use App\Models\CustomField;
use Auth;
use DNS1D;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use DB;
use App\Models\Variant;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\ProductPurchase;
use App\Models\Payment;
use App\Models\CustomerGroup;
use App\Models\ProductUnit;
use App\Models\ProductUnitPriceLevel;
use App\Models\ProductUnitPriceTier;
use App\Traits\TenantInfo;
use App\Traits\CacheForget;
use Intervention\Image\Facades\Image;
use File;

class ProductController extends Controller
{
    use CacheForget;
    use TenantInfo;

    private function prepareSaleUnitIds(array &$data)
    {
        if((isset($data['type']) && $data['type'] != 'standard') || empty($data['sale_unit_id'])) {
            $data['product_sale_unit_ids'] = null;
            return;
        }

        if(isset($data['product_sale_unit_ids']) && is_array($data['product_sale_unit_ids'])) {
            if(isset($data['sale_unit_id']) && !in_array($data['sale_unit_id'], $data['product_sale_unit_ids']))
                array_unshift($data['product_sale_unit_ids'], $data['sale_unit_id']);
            $data['product_sale_unit_ids'] = implode(',', array_unique(array_filter($data['product_sale_unit_ids'])));
        }
        elseif(isset($data['sale_unit_id']) && $data['sale_unit_id']) {
            $data['product_sale_unit_ids'] = $data['sale_unit_id'];
        }
        else {
            $data['product_sale_unit_ids'] = null;
        }
    }

    /**
     * Angka uang dari form bisa sampai ke server dalam format tampilan Indonesia
     * ("8.500" atau "8.500,75") kalau JS tidak sempat menormalkannya — misalnya
     * form ter-submit lewat tombol Enter, bukan lewat tombol Simpan. Tanpa
     * normalisasi ini PHP membaca "8.500" sebagai 8.5, sehingga harga pokok
     * 8.500 tersimpan menjadi 8,50.
     */
    public static function normalizeMoney($value)
    {
        if($value === null || $value === '' || is_array($value))
            return $value;
        if(is_int($value) || is_float($value))
            return $value;

        $clean = preg_replace('/[^\d.,\-]/', '', trim((string)$value));
        if($clean === '' || $clean === '-')
            return 0;

        if(strpos($clean, ',') !== false) {
            // Format Indonesia: titik = pemisah ribuan, koma = pemisah desimal.
            $clean = str_replace(',', '.', str_replace('.', '', $clean));
        }
        elseif(strpos($clean, '.') !== false) {
            $parts = explode('.', $clean);
            $last = array_pop($parts);
            // "8.500" / "1.234.567" -> pemisah ribuan. "8.5" / "8.50" -> desimal.
            if(count($parts) > 1 || strlen($last) === 3)
                $clean = implode('', $parts) . $last;
            else
                $clean = implode('', $parts) . '.' . $last;
        }

        return is_numeric($clean) ? (float)$clean : 0;
    }

    private function normalizeMoneyTree(array $node)
    {
        foreach($node as $key => $value) {
            if(is_array($value))
                $node[$key] = $this->normalizeMoneyTree($value);
            elseif(in_array($key, ['cost', 'price'], true))
                $node[$key] = self::normalizeMoney($value);
        }
        return $node;
    }

    /**
     * Normalisasi semua kolom uang pada payload produk sebelum disimpan.
     */
    private function normalizeMoneyFields(array $data)
    {
        foreach(['cost', 'price', 'wholesale_price', 'promotion_price'] as $key) {
            if(isset($data[$key]) && !is_array($data[$key]))
                $data[$key] = self::normalizeMoney($data[$key]);
        }

        if(isset($data['unit_price']) && is_array($data['unit_price']))
            $data['unit_price'] = array_map([self::class, 'normalizeMoney'], $data['unit_price']);

        if(isset($data['additional_cost']) && is_array($data['additional_cost']))
            $data['additional_cost'] = array_map([self::class, 'normalizeMoney'], $data['additional_cost']);

        if(isset($data['pu']) && is_array($data['pu']))
            $data['pu'] = $this->normalizeMoneyTree($data['pu']);

        return $data;
    }

    /**
     * Persist the per-unit pricing table (Mode Harga: Satuan / Level Harga /
     * Jumlah) submitted as a nested `pu[<unit_id>][...]` array. No-ops when
     * price_mode is "single" (the flat products.cost/price/markup_percent/
     * point/commission columns are used instead) or when the array is empty.
     * Rows for units removed from the form are deleted; kept rows are
     * updated in place so ids stay stable across edits.
     */
    private function syncProductUnits($productId, array $data)
    {
        $priceMode = $data['price_mode'] ?? 'single';
        $puInput = $data['pu'] ?? [];

        if($priceMode == 'single' || !is_array($puInput) || empty($puInput)) {
            ProductUnit::where('product_id', $productId)->delete();
            return;
        }

        $keptUnitIds = [];
        $sort = 0;
        foreach ($puInput as $unitId => $row) {
            if(!is_numeric($unitId))
                continue;
            $keptUnitIds[] = $unitId;

            $productUnit = ProductUnit::updateOrCreate(
                ['product_id' => $productId, 'unit_id' => $unitId],
                [
                    'cost' => $row['cost'] ?? 0,
                    'markup_percent' => $row['markup_percent'] ?? 0,
                    'price' => $row['price'] ?? 0,
                    'point' => $row['point'] ?? 0,
                    'commission' => $row['commission'] ?? 0,
                    'sort_order' => $sort++,
                ]
            );

            if($priceMode == 'level' && !empty($row['level']) && is_array($row['level'])) {
                $keptGroupIds = [];
                foreach ($row['level'] as $groupId => $level) {
                    if(!is_numeric($groupId))
                        continue;
                    $keptGroupIds[] = $groupId;
                    ProductUnitPriceLevel::updateOrCreate(
                        ['product_unit_id' => $productUnit->id, 'customer_group_id' => $groupId],
                        [
                            'markup_percent' => $level['markup_percent'] ?? 0,
                            'price' => $level['price'] ?? 0,
                        ]
                    );
                }
                ProductUnitPriceLevel::where('product_unit_id', $productUnit->id)
                    ->whereNotIn('customer_group_id', $keptGroupIds)
                    ->delete();
            }
            else {
                ProductUnitPriceLevel::where('product_unit_id', $productUnit->id)->delete();
            }

            if($priceMode == 'qty' && !empty($row['tier']) && is_array($row['tier'])) {
                $keptTiers = [];
                foreach ($row['tier'] as $tierNo => $tier) {
                    if(empty($tier['min_qty']) && empty($tier['price']))
                        continue;
                    $keptTiers[] = $tierNo;
                    ProductUnitPriceTier::updateOrCreate(
                        ['product_unit_id' => $productUnit->id, 'tier_no' => $tierNo],
                        [
                            'min_qty' => $tier['min_qty'] ?? 0,
                            'markup_percent' => $tier['markup_percent'] ?? 0,
                            'price' => $tier['price'] ?? 0,
                        ]
                    );
                }
                ProductUnitPriceTier::where('product_unit_id', $productUnit->id)
                    ->whereNotIn('tier_no', $keptTiers)
                    ->delete();
            }
            else {
                ProductUnitPriceTier::where('product_unit_id', $productUnit->id)->delete();
            }
        }

        // Remove pricing rows for units no longer present in the submitted table
        // (also cascades their level/tier children).
        $stale = ProductUnit::where('product_id', $productId)->whereNotIn('unit_id', $keptUnitIds)->get();
        foreach ($stale as $staleUnit) {
            ProductUnitPriceLevel::where('product_unit_id', $staleUnit->id)->delete();
            ProductUnitPriceTier::where('product_unit_id', $staleUnit->id)->delete();
            $staleUnit->delete();
        }

        // Keep the flat products.cost/price columns in sync with the base
        // unit's row so screens/reports that don't know about the per-unit
        // pricing table (POS, product reports, etc.) still show a sane
        // number instead of 0. Base unit = the product's own unit_id.
        $product = Product::find($productId);
        if($product) {
            $baseRow = ProductUnit::where('product_id', $productId)->where('unit_id', $product->unit_id)->first();
            $fallbackRow = $baseRow ?: ProductUnit::where('product_id', $productId)->orderBy('sort_order')->first();
            if($fallbackRow) {
                $product->cost = $fallbackRow->cost;
                $product->price = $fallbackRow->price;
                $product->markup_percent = $fallbackRow->markup_percent;
                $product->point = $fallbackRow->point;
                $product->commission = $fallbackRow->commission;
                $product->save();
            }
        }
    }

    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('products-index')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';
            $role_id = $role->id;
            $numberOfProduct = DB::table('products')->where('is_active', true)->count();
            $custom_fields = CustomField::where([
                                ['belongs_to', 'product'],
                                ['is_table', true]
                            ])->pluck('name');
            $field_name = [];
            foreach($custom_fields as $fieldName) {
                $field_name[] = str_replace(" ", "_", strtolower($fieldName));
            }
            return view('backend.product.index', compact('all_permission', 'role_id', 'numberOfProduct', 'custom_fields', 'field_name'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    /**
     * Entry point untuk DataTables daftar produk.
     *
     * Dibungkus try/catch supaya error apa pun tercatat di storage/logs/laravel.log
     * dan tetap membalas JSON yang valid, bukan halaman error HTML. Tanpa ini
     * DataTables cuma bisa bilang "Ajax error" tanpa petunjuk penyebabnya.
     */
    public function productData(Request $request)
    {
        try {
            return $this->buildProductData($request);
        }
        catch (\Throwable $e) {
            \Log::error('productData gagal: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Gagal memuat daftar produk: '.$e->getMessage()
                                     .' ('.basename($e->getFile()).':'.$e->getLine().')',
            ]);
        }
    }

    private function buildProductData(Request $request)
    {
        $columns = array(
            2 => 'name',
            3 => 'code',
            4 => 'brand_id',
            5 => 'category_id',
            6 => 'qty',
            7 => 'unit_id',
            8 => 'price',
            9 => 'cost',
            10 => 'stock_worth'
        );

        // Di PHP 8 in_array() dengan argumen null melempar TypeError, jadi payload
        // yang tidak mengirim all_permission akan membuat seluruh tabel gagal.
        $all_permission = $request->input('all_permission');
        $all_permission = is_array($all_permission) ? $all_permission : [];

        // "status" toggles between the Aktif (default) and Non-aktif product lists.
        $isActive = $request->input('status', 'active') != 'inactive';
        $stockFilter = $request->input('stock_filter', 'all');
        $applyStockFilter = function($query) use ($stockFilter) {
            if($stockFilter == 'all')
                return $query;

            if(Auth::user()->role_id > 2) {
                $warehouseId = Auth::user()->warehouse_id;
                return $query->whereExists(function($subQuery) use ($stockFilter, $warehouseId) {
                    $subQuery->select(DB::raw(1))
                        ->from('product_warehouse')
                        ->whereColumn('product_warehouse.product_id', 'products.id')
                        ->where('product_warehouse.warehouse_id', $warehouseId);
                    if($stockFilter == 'in_stock')
                        $subQuery->where('product_warehouse.qty', '>', 0);
                    elseif($stockFilter == 'minus')
                        $subQuery->where('product_warehouse.qty', '<', 0);
                    elseif($stockFilter == 'empty')
                        $subQuery->where('product_warehouse.qty', '=', 0);
                });
            }

            if($stockFilter == 'in_stock')
                return $query->where('products.qty', '>', 0);
            if($stockFilter == 'minus')
                return $query->where('products.qty', '<', 0);
            if($stockFilter == 'empty')
                return $query->where('products.qty', '=', 0);

            return $query;
        };

        $totalQuery = Product::where('is_active', $isActive);
        $applyStockFilter($totalQuery);
        $totalData = $totalQuery->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        // Index kolom bisa menunjuk kolom yang tidak bisa diurutkan (checkbox, gambar,
        // custom field, tombol aksi). Tanpa fallback ini $order jadi "products." saja
        // dan query SQL-nya error.
        $orderIndex = $request->input('order.0.column');
        $order = 'products.'.($columns[$orderIndex] ?? 'name');
        $dir = strtolower($request->input('order.0.dir')) === 'desc' ? 'desc' : 'asc';
        //fetching custom fields data
        $custom_fields = CustomField::where([
                        ['belongs_to', 'product'],
                        ['is_table', true]
                    ])->pluck('name');
        $field_names = [];
        foreach($custom_fields as $fieldName) {
            $field_names[] = str_replace(" ", "_", strtolower($fieldName));
        }
        if(empty($request->input('search.value'))){
            $q = Product::with('category', 'brand', 'unit')
                ->where('is_active', $isActive);
            $applyStockFilter($q);
            $totalFiltered = (clone $q)->count();
            $products = $q->offset($start)
                ->limit($limit)
                ->orderBy($order,$dir)
                ->get();
        }
        else {
            $search = $request->input('search.value');
            $q = Product::select('products.*')
                ->with('category', 'brand', 'unit')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                ->where('products.is_active', $isActive)
                ->where(function($query) use ($search, $field_names) {
                    $query->where('products.name', 'LIKE', "%{$search}%")
                        ->orWhere('products.code', 'LIKE', "%{$search}%")
                        ->orWhere(function($categoryQuery) use ($search) {
                            $categoryQuery->where('categories.name', 'LIKE', "%{$search}%")
                                ->where('categories.is_active', true);
                        })
                        ->orWhere(function($brandQuery) use ($search) {
                            $brandQuery->where('brands.title', 'LIKE', "%{$search}%")
                                ->where('brands.is_active', true);
                        });

                    foreach ($field_names as $field_name) {
                        $query->orwhere('products.' . $field_name, 'LIKE', "%{$search}%");
                    }
                });
            $applyStockFilter($q);
            $totalFiltered = (clone $q)->count();

            $q = $q->offset($start)
                ->limit($limit)
                ->orderBy($order,$dir);

            $products = $q->get();
            /*$totalFiltered = Product::
                            join('categories', 'products.category_id', '=', 'categories.id')
                            ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                            ->where([
                                ['products.name','LIKE',"%{$search}%"],
                                ['products.is_active', true]
                            ])
                            ->orWhere([
                                ['products.code', 'LIKE', "%{$search}%"],
                                ['products.is_active', true]
                            ])
                            ->orWhere([
                                ['categories.name', 'LIKE', "%{$search}%"],
                                ['categories.is_active', true],
                                ['products.is_active', true]
                            ])
                            ->orWhere([
                                ['brands.title', 'LIKE', "%{$search}%"],
                                ['brands.is_active', true],
                                ['products.is_active', true]
                            ])
                            ->count();*/
        }
        $data = array();
        if(!empty($products))
        {
            foreach ($products as $key=>$product)
            {
                $nestedData['id'] = $product->id;
                $nestedData['key'] = $key;
                $product_image = explode(",", $product->image);
                $product_image = htmlspecialchars($product_image[0]);
                if($product_image && $product_image != 'zummXD2dvAtI.png') {
                    if(file_exists("public/images/product/small/". $product_image))
                        $nestedData['image'] = '<img src="'.url('images/product/small', $product_image).'" height="80" width="80">';
                    else
                        $nestedData['image'] = '<img src="'.url('images/product', $product_image).'" height="80" width="80">';
                }
                else
                    $nestedData['image'] = '<img src="images/zummXD2dvAtI.png" height="80" width="80">';
                $nestedData['name'] = $product->name;
                $nestedData['code'] = $product->code;
                if($product->brand)
                    $nestedData['brand'] = $product->brand->title;
                else
                    $nestedData['brand'] = "N/A";
                // Kategori/brand/unit bisa sudah dihapus sementara produknya masih ada.
                $nestedData['category'] = $product->category ? $product->category->name : 'N/A';
                if(Auth::user()->role_id > 2 && $product->type == 'standard') {
                    $nestedData['qty'] = Product_Warehouse::where([
                                                ['product_id', $product->id],
                                                ['warehouse_id', Auth::user()->warehouse_id]
                                            ])->sum('qty');
                }
                else
                    $nestedData['qty'] = $product->qty;

                if($product->unit_id && $product->unit)
                    $nestedData['unit'] = $product->unit->unit_name;
                else
                    $nestedData['unit'] = 'N/A';

                $nestedData['price'] = number_format((float)$product->price, config('decimal'), ',', '.');
                $nestedData['cost'] = number_format((float)$product->cost, config('decimal'), ',', '.');

                $stock_worth_price = number_format($nestedData['qty'] * $product->price, config('decimal'), ',', '.');
                $stock_worth_cost = number_format($nestedData['qty'] * $product->cost, config('decimal'), ',', '.');
                if(config('currency_position') == 'prefix')
                    $nestedData['stock_worth'] = config('currency').' '.$stock_worth_price.' / '.config('currency').' '.$stock_worth_cost;
                else
                    $nestedData['stock_worth'] = $stock_worth_price.' '.config('currency').' / '.$stock_worth_cost.' '.config('currency');

                //fetching custom fields data
                foreach($field_names as $field_name) {
                    $nestedData[$field_name] = $product->$field_name;
                }

                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.trans("file.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                            <li>
                                <button="type" class="btn btn-link view"><i class="fa fa-eye"></i> '.trans('file.View').'</button>
                            </li>';

                if(in_array("products-edit", $all_permission))
                    $nestedData['options'] .= '<li>
                            <a href="'.route('products.edit', $product->id).'" class="btn btn-link"><i class="fa fa-edit"></i> '.trans('file.edit').'</a>
                        </li>';
                // Route::has() dipakai supaya route cache yang basi atau routes/web.php
                // yang belum ter-deploy cuma menyembunyikan satu menu, bukan mematikan
                // seluruh daftar produk dengan RouteNotFoundException.
                if(in_array("products-add", $all_permission) && \Route::has('products.duplicate'))
                    $nestedData['options'] .= '<li>
                            <a href="'.route('products.duplicate', $product->id).'" class="btn btn-link"><i class="fa fa-copy"></i> Duplikat Produk</a>
                        </li>';
                if(in_array("products-index", $all_permission) || in_array("product_history", $all_permission))
                    $nestedData['options'] .= '<li>
                            <a href="'.url('products/history?product_id='.$product->id).'" class="btn btn-link"><i class="dripicons-checklist"></i> '.trans("file.Product History").'</a>
                        </li>';
                if(in_array("print_barcode", $all_permission)) {
                    $product_info = $product->code.' ('.$product->name.')';
                    $nestedData['options'] .= \Form::open(["route" => "product.printBarcode", "method" => "GET"] ).'
                        <li>
                            <input type="hidden" name="data" value="'.$product_info.'" />
                            <button type="submit" class="btn btn-link"><i class="dripicons-print"></i> '.trans("file.print_barcode").'</button>
                        </li>'.\Form::close();
                }
                if(in_array("products-delete", $all_permission)) {
                    if($isActive)
                        $nestedData['options'] .= \Form::open(["route" => ["products.destroy", $product->id], "method" => "DELETE"] ).'
                                <li>
                                  <button type="submit" class="btn btn-link" onclick="return confirm(\'Non-aktifkan produk ini? Produk tidak akan muncul lagi saat jual/beli, tapi data & riwayatnya tetap tersimpan.\')"><i class="fa fa-ban"></i> Non-aktifkan</button>
                                </li>'.\Form::close();
                    else
                        $nestedData['options'] .= '<li>
                                <a href="'.url('products/activate/'.$product->id).'" class="btn btn-link" onclick="return confirm(\'Aktifkan kembali produk ini?\')"><i class="fa fa-check-circle"></i> Aktifkan Kembali</a>
                            </li>';
                }
                $nestedData['options'] .= '</ul></div>';
                // data for product details by one click
                if($product->tax_id) {
                    // Pajak bisa sudah dihapus; jangan sampai null->name bikin 500.
                    $tax_data = Tax::find($product->tax_id);
                    $tax = $tax_data ? $tax_data->name : 'N/A';
                }
                else
                    $tax = "N/A";

                if($product->tax_method == 1)
                    $tax_method = trans('file.Exclusive');
                else
                    $tax_method = trans('file.Inclusive');

                $nestedData['product'] = array( '[ "'.$product->type.'"', ' "'.$product->name.'"', ' "'.$product->code.'"', ' "'.$nestedData['brand'].'"', ' "'.$nestedData['category'].'"', ' "'.$nestedData['unit'].'"', ' "'.$product->cost.'"', ' "'.$product->price.'"', ' "'.$tax.'"', ' "'.$tax_method.'"', ' "'.$product->alert_quantity.'"', ' "'.preg_replace('/\s+/S', " ", $product->product_details).'"', ' "'.$product->id.'"', ' "'.$product->product_list.'"', ' "'.$product->variant_list.'"', ' "'.$product->qty_list.'"', ' "'.$product->price_list.'"', ' "'.$nestedData['qty'].'"', ' "'.$product->image.'"', ' "'.$product->is_variant.'"]'
                );
                //$nestedData['imagedata'] = DNS1D::getBarcodePNG($product->code, $product->barcode_symbology);
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );

        // JSON_INVALID_UTF8_SUBSTITUTE: kalau ada satu produk saja yang punya karakter
        // rusak (biasanya hasil copy-paste dari Excel), json_encode biasa mengembalikan
        // false -> body kosong -> DataTables melapor "Ajax error". Diganti '?' saja.
        return response()->json($json_data, 200, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    public function create()
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('products-add')){
            $lims_product_list_without_variant = $this->productWithoutVariant();
            $lims_product_list_with_variant = $this->productWithVariant();
            $lims_brand_list = Brand::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $lims_unit_list = Unit::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $numberOfProduct = Product::where('is_active', true)->count();
            $custom_fields = CustomField::where('belongs_to', 'product')->get();
            $lims_customer_group_list = CustomerGroup::where('is_active', true)->get();
            return view('backend.product.create',compact('lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_warehouse_list', 'numberOfProduct', 'custom_fields', 'lims_customer_group_list'));
        } 
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => [
                'max:255',
                    Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
            'name' => [
                'max:255',
                    Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ]
        ]);
        $data = $request->except('image', 'file');
        $data = $this->normalizeMoneyFields($data);

        if(isset($data['is_variant'])) {
            $data['variant_option'] = json_encode($data['variant_option']);
            $data['variant_value'] = json_encode($data['variant_value']);
        }
        else {
            $data['variant_option'] = $data['variant_value'] = null;
        }

        $data['name'] = preg_replace('/[\n\r]/', "<br>", htmlspecialchars(trim($data['name']), ENT_QUOTES));

        if(in_array('ecommerce', explode(',',config('addons')))) {
            $data['slug'] = Str::slug($data['name'], '-');
            $data['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['slug']);
            $data['slug'] = str_replace( '\/', '/', $data['slug'] );
        }
            
        if($data['type'] == 'combo') {
            $data['product_list'] = implode(",", $data['product_id']);
            $data['variant_list'] = implode(",", $data['variant_id']);
            $data['qty_list'] = implode(",", $data['product_qty']);
            $data['price_list'] = implode(",", $data['unit_price']);
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
        }
        elseif($data['type'] == 'digital' || $data['type'] == 'service')
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;

        // Satuan Pembelian tidak lagi diisi manual di form: default ke Satuan Produk (unit_id)
        if(empty($data['purchase_unit_id']))
            $data['purchase_unit_id'] = $data['unit_id'];

        $this->prepareSaleUnitIds($data);

        $data['product_details'] = str_replace('"', '@', $data['product_details']);

        if($data['starting_date'])
            $data['starting_date'] = date('Y-m-d', strtotime($data['starting_date']));
        if($data['last_date'])
            $data['last_date'] = date('Y-m-d', strtotime($data['last_date']));
        $data['is_active'] = true;
        $images = $request->image;
        $image_names = [];
        if($images) {
            if ( !file_exists("public/images/product/large") && !is_dir("public/images/product/large") ) {
                mkdir("public/images/product/large");       
            }
            if ( !file_exists("public/images/product/medium") && !is_dir("public/images/product/medium") ) {
                mkdir("public/images/product/medium");       
            }
            if ( !file_exists("public/images/product/small") && !is_dir("public/images/product/small") ) {
                mkdir("public/images/product/small");       
            }
            foreach ($images as $key => $image) {
                $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
                $imageName = date("Ymdhis") . ($key+1);
                if(!config('database.connections.saleprosaas_landlord')) {
                    $imageName = $imageName . '.' . $ext;
                    $image->move('public/images/product', $imageName);

                    $img_lg = Image::make('public/images/product/'. $imageName)->fit(500, 500)->save('public/images/product/large/'. $imageName, 90);
                    $img_md = Image::make('public/images/product/'. $imageName)->fit(250, 250)->save('public/images/product/medium/'. $imageName, 100);
                    $img_sm = Image::make('public/images/product/'. $imageName)->fit(100, 100)->save('public/images/product/small/'. $imageName, 100);

                }
                else {
                    $imageName = $this->getTenantId() . '_' . $imageName . '.' . $ext;
                    $image->move('public/images/product', $imageName);

                    $img_lg = Image::make('public/images/product/'. $imageName)->fit(500, 500)->save('public/images/product/large/'. $imageName, 90);
                    $img_md = Image::make('public/images/product/'. $imageName)->fit(250, 250)->save('public/images/product/medium/'. $imageName, 100);
                    $img_sm = Image::make('public/images/product/'. $imageName)->fit(100, 100)->save('public/images/product/small/'. $imageName, 100);

                }
                $image_names[] = $imageName;
            }
            $data['image'] = implode(",", $image_names);
        }
        else {
            $data['image'] = 'zummXD2dvAtI.png';
        }
        $file = $request->file;
        if ($file) {
            $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $fileName = strtotime(date('Y-m-d H:i:s'));
            $fileName = $fileName . '.' . $ext;
            $file->move('public/product/files', $fileName);
            $data['file'] = $fileName;
        }
        if(!isset($data['is_sync_disable']) && \Schema::hasColumn('products', 'is_sync_disable'))
                $data['is_sync_disable'] = null;
        //return $data;
        $lims_product_data = Product::create($data);
        $this->syncProductUnits($lims_product_data->id, $data);
        //inserting custom field data
        $custom_field_data = [];
        $custom_fields = CustomField::where('belongs_to', 'product')->select('name', 'type')->get();
        foreach ($custom_fields as $type => $custom_field) {
            $field_name = str_replace(' ', '_', strtolower($custom_field->name));
            if(isset($data[$field_name])) {
                if($custom_field->type == 'checkbox' || $custom_field->type == 'multi_select')
                    $custom_field_data[$field_name] = implode(",", $data[$field_name]);
                else
                    $custom_field_data[$field_name] = $data[$field_name];
            }
        }
        if(count($custom_field_data))
            DB::table('products')->where('id', $lims_product_data->id)->update($custom_field_data);
        //dealing with initial stock and auto purchase
        $initial_stock = 0;
        if(isset($data['is_initial_stock']) && !isset($data['is_variant']) && !isset($data['is_batch'])) {
            foreach ($data['stock_warehouse_id'] as $key => $warehouse_id) {
                $stock = $data['stock'][$key];
                if($stock > 0) {
                    $this->autoPurchase($lims_product_data, $warehouse_id, $stock);
                    $initial_stock += $stock;
                }
            }
        }
        if($initial_stock > 0) {
            $lims_product_data->qty += $initial_stock;
            $lims_product_data->save();
        }
        //dealing with product variant
        if(!isset($data['is_batch']))
            $data['is_batch'] = null;
        $variant_ids = [];
        if(isset($data['is_variant'])) {
            foreach ($data['variant_name'] as $key => $variant_name) {
                $lims_variant_data = Variant::firstOrCreate(['name' => $data['variant_name'][$key]]);
                $lims_variant_data->name = $data['variant_name'][$key];
                $lims_variant_data->save();
                $variant_ids[] = $lims_variant_data->id;
                $lims_product_variant_data = new ProductVariant;
                $lims_product_variant_data->product_id = $lims_product_data->id;
                $lims_product_variant_data->variant_id = $lims_variant_data->id;
                $lims_product_variant_data->position = $key + 1;
                $lims_product_variant_data->item_code = $data['item_code'][$key];
                $lims_product_variant_data->additional_cost = $data['additional_cost'][$key];
                $lims_product_variant_data->additional_price = $data['additional_price'][$key];
                $lims_product_variant_data->qty = 0;
                $lims_product_variant_data->save();
            }
        }
        if(isset($data['is_diffPrice'])) {
            foreach ($data['diff_price'] as $key => $diff_price) {
                if($diff_price) {
                    Product_Warehouse::create([
                        "product_id" => $lims_product_data->id,
                        "warehouse_id" => $data["warehouse_id"][$key],
                        "qty" => 0,
                        "price" => $diff_price
                    ]);
                }
            }
        }
        elseif(!isset($data['is_initial_stock']) && !isset($data['is_batch']) && config('without_stock') == 'yes') {
            $warehouse_ids = Warehouse::where('is_active', true)->pluck('id');
            foreach ($warehouse_ids as $warehouse_id) {
                if(count($variant_ids)) {
                    foreach ($variant_ids as $variant_id) {
                        Product_Warehouse::create([
                            "product_id" => $lims_product_data->id,
                            "variant_id" => $variant_id,
                            "warehouse_id" => $warehouse_id,
                            "qty" => 0,
                        ]);
                    }
                }
                else {
                    Product_Warehouse::create([
                        "product_id" => $lims_product_data->id,
                        "warehouse_id" => $warehouse_id,
                        "qty" => 0,
                    ]);
                }
            }
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        \Session::flash('create_message', 'Product created successfully');
    }

    public function autoPurchase($product_data, $warehouse_id, $stock)
    {
        $data['reference_no'] = 'pr-' . date("Ymd") . '-'. date("his");
        $data['user_id'] = Auth::id();
        $data['warehouse_id'] = $warehouse_id;
        $data['item'] = 1;
        $data['total_qty'] = $stock;
        $data['total_discount'] = 0;
        $data['status'] = 1;
        $data['payment_status'] = 2;
        if($product_data->tax_id) {
            $tax_data = DB::table('taxes')->select('rate')->find($product_data->tax_id);
            if($product_data->tax_method == 1) {
                $net_unit_cost = number_format($product_data->cost, 2, '.', '');
                $tax = number_format($product_data->cost * $stock * ($tax_data->rate / 100), 2, '.', '');
                $cost = number_format(($product_data->cost * $stock) + $tax, 2, '.', '');
            }
            else {
                $net_unit_cost = number_format((100 / (100 + $tax_data->rate)) * $product_data->cost, 2, '.', '');
                $tax = number_format(($product_data->cost - $net_unit_cost) * $stock, 2, '.', '');
                $cost = number_format($product_data->cost * $stock, 2, '.', '');
            }
            $tax_rate = $tax_data->rate;
            $data['total_tax'] = $tax;
            $data['total_cost'] = $cost;
        }
        else {
            $data['total_tax'] = 0.00;
            $data['total_cost'] = number_format($product_data->cost * $stock, 2, '.', '');
            $net_unit_cost = number_format($product_data->cost, 2, '.', '');
            $tax_rate = 0.00;
            $tax = 0.00;
            $cost = number_format($product_data->cost * $stock, 2, '.', '');
        }

        $product_warehouse_data = Product_Warehouse::select('id', 'qty')
                                ->where([
                                    ['product_id', $product_data->id],
                                    ['warehouse_id', $warehouse_id]
                                ])->first();
        if($product_warehouse_data) {
            $product_warehouse_data->qty += $stock;
            $product_warehouse_data->save();
        }
        else {
            $lims_product_warehouse_data = new Product_Warehouse();
            $lims_product_warehouse_data->product_id = $product_data->id;
            $lims_product_warehouse_data->warehouse_id = $warehouse_id;
            $lims_product_warehouse_data->qty = $stock;
            $lims_product_warehouse_data->save();
        }
        $data['order_tax'] = 0;
        $data['grand_total'] = $data['total_cost'];
        $data['paid_amount'] = $data['grand_total'];
        //insetting data to purchase table
        $purchase_data = Purchase::create($data);
        //inserting data to product_purchases table
        ProductPurchase::create([
            'purchase_id' => $purchase_data->id,
            'product_id' => $product_data->id,
            'qty' => $stock,
            'recieved' => $stock,
            'purchase_unit_id' => $product_data->unit_id,
            'net_unit_cost' => $net_unit_cost,
            'discount' => 0,
            'tax_rate' => $tax_rate,
            'tax' => $tax,
            'total' => $cost
        ]);
        //inserting data to payments table
        Payment::create([
            'payment_reference' => 'ppr-' . date("Ymd") . '-'. date("his"),
            'user_id' => Auth::id(),
            'purchase_id' => $purchase_data->id,
            'account_id' => 0,
            'amount' => $data['grand_total'],
            'change' => 0,
            'paying_method' => 'Cash'
        ]);
    }

    public function duplicate($id)
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if(!$role->hasPermissionTo('products-add'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $source = Product::with('productUnits.priceLevels', 'productUnits.priceTiers')->findOrFail($id);
        $suffix = date('YmdHis');
        $newProduct = null;

        DB::transaction(function() use ($source, $suffix, &$newProduct) {
            $newProduct = $source->replicate();
            $newProduct->name = substr($source->name . ' Copy ' . $suffix, 0, 255);
            $newProduct->code = substr($source->code . '-COPY-' . $suffix, 0, 255);
            // Kolom 'slug' hanya ada bila addon ecommerce terpasang. Jangan diisi
            // kalau kolomnya tidak ada, kalau tidak: SQLSTATE[42S22] Unknown column 'slug'.
            if (\Schema::hasColumn('products', 'slug')) {
                $newProduct->slug = $source->slug ? substr($source->slug . '-copy-' . $suffix, 0, 255) : null;
            }
            else {
                unset($newProduct->slug);
            }
            $newProduct->qty = 0;
            $newProduct->is_active = true;
            if (\Schema::hasColumn('products', 'woocommerce_product_id')) {
                $newProduct->woocommerce_product_id = null;
                $newProduct->woocommerce_media_id = null;
            }
            $newProduct->save();

            foreach ($source->productUnits as $sourceUnit) {
                $newUnit = ProductUnit::create([
                    'product_id' => $newProduct->id,
                    'unit_id' => $sourceUnit->unit_id,
                    'cost' => $sourceUnit->cost,
                    'markup_percent' => $sourceUnit->markup_percent,
                    'price' => $sourceUnit->price,
                    'point' => $sourceUnit->point,
                    'commission' => $sourceUnit->commission,
                    'sort_order' => $sourceUnit->sort_order,
                ]);

                foreach ($sourceUnit->priceLevels as $level) {
                    ProductUnitPriceLevel::create([
                        'product_unit_id' => $newUnit->id,
                        'customer_group_id' => $level->customer_group_id,
                        'markup_percent' => $level->markup_percent,
                        'price' => $level->price,
                    ]);
                }

                foreach ($sourceUnit->priceTiers as $tier) {
                    ProductUnitPriceTier::create([
                        'product_unit_id' => $newUnit->id,
                        'tier_no' => $tier->tier_no,
                        'min_qty' => $tier->min_qty,
                        'markup_percent' => $tier->markup_percent,
                        'price' => $tier->price,
                    ]);
                }
            }

            ProductVariant::where('product_id', $source->id)->orderBy('position')->get()->each(function($variant) use ($newProduct, $suffix) {
                ProductVariant::create([
                    'product_id' => $newProduct->id,
                    'variant_id' => $variant->variant_id,
                    'position' => $variant->position,
                    'item_code' => substr($variant->item_code . '-COPY-' . $suffix, 0, 255),
                    'additional_cost' => $variant->additional_cost,
                    'additional_price' => $variant->additional_price,
                    'qty' => 0,
                ]);
            });

            Product_Warehouse::where('product_id', $source->id)->get()->each(function($warehouseRow) use ($newProduct) {
                Product_Warehouse::create([
                    'product_id' => $newProduct->id,
                    'product_batch_id' => null,
                    'variant_id' => $warehouseRow->variant_id,
                    'imei_number' => null,
                    'warehouse_id' => $warehouseRow->warehouse_id,
                    'rack_id' => null,
                    'qty' => 0,
                    'price' => $warehouseRow->price,
                ]);
            });

        });

        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');

        return redirect()->route('products.edit', $newProduct->id)->with('edit_message', 'Produk berhasil diduplikat. Silakan ubah nama dan barcode.');
    }

    public function history(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('product_history') || $role->hasPermissionTo('products-index')) {
            if($request->input('warehouse_id'))
                $warehouse_id = $request->input('warehouse_id');
            else
                $warehouse_id = 0;

            $warehouse_type = $request->input('warehouse_type', '');

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d') )))));
                $ending_date = date("Y-m-d");
            }
            $product_id = $request->input('product_id');
            $product_data = Product::select('name', 'code')->find($product_id);
            if(!$product_data)
                return redirect('products')->with('not_permitted', 'Produk tidak ditemukan');
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            return view('backend.product.history',compact('starting_date', 'ending_date', 'warehouse_id', 'warehouse_type', 'product_id', 'product_data', 'lims_warehouse_list'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function saleHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('sales')
            ->join('product_sales', 'sales.id', '=', 'product_sales.sale_id')
            ->where('product_sales.product_id', $product_id)
            ->whereDate('sales.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('sales.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('sales.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'sales.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->join('customers', 'sales.customer_id', '=', 'customers.id')
                ->join('warehouses', 'sales.warehouse_id', '=', 'warehouses.id')
                ->select('sales.id', 'sales.reference_no', 'sales.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_sales.qty', 'product_sales.sale_unit_id', 'product_sales.total')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $sales = $q->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('sales.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales =  $q->orwhere([
                                ['sales.reference_no', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->get();
                $totalFiltered = $q->orwhere([
                                    ['sales.reference_no', 'LIKE', "%{$search}%"],
                                    ['sales.user_id', Auth::id()]
                                ])
                                ->count();
            }
            else {
                $sales =  $q->orwhere('sales.reference_no', 'LIKE', "%{$search}%")->get();
                $totalFiltered = $q->orwhere('sales.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($sales))
        {
            foreach ($sales as $key => $sale)
            {
                $nestedData['id'] = $sale->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($sale->created_at));
                $nestedData['reference_no'] = $sale->reference_no;
                $nestedData['warehouse'] = $sale->warehouse_name;
                $nestedData['customer'] = $sale->customer_name.' ['.($sale->customer_number).']';
                $nestedData['qty'] = number_format($sale->qty, config('decimal'), ',', '.');
                if($sale->sale_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($sale->sale_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_price'] = number_format(($sale->total / $sale->qty), config('decimal'), ',', '.');
                $nestedData['sub_total'] = number_format($sale->total, config('decimal'), ',', '.');
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }

    public function purchaseHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('purchases')
            ->join('product_purchases', 'purchases.id', '=', 'product_purchases.purchase_id')
            ->where('product_purchases.product_id', $product_id)
            ->whereDate('purchases.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('purchases.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('purchases.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'purchases.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->join('warehouses', 'purchases.warehouse_id', '=', 'warehouses.id')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $purchases = $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('purchases.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $purchases =  $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                            ->orwhere([
                                ['purchases.reference_no', 'LIKE', "%{$search}%"],
                                ['purchases.user_id', Auth::id()]
                            ])->get();
                $totalFiltered = $q->orwhere([
                                    ['purchases.reference_no', 'LIKE', "%{$search}%"],
                                    ['purchases.user_id', Auth::id()]
                                ])->count();
            }
            else {
                $purchases =  $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                            ->orwhere('purchases.reference_no', 'LIKE', "%{$search}%")
                            ->get();
                $totalFiltered = $q->orwhere('purchases.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($purchases))
        {
            foreach ($purchases as $key => $purchase)
            {
                $nestedData['id'] = $purchase->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($purchase->created_at));
                $nestedData['reference_no'] = $purchase->reference_no;
                $nestedData['warehouse'] = $purchase->warehouse_name;
                if($purchase->supplier_id)
                    $nestedData['supplier'] = $purchase->supplier_name.' ['.($purchase->supplier_number).']';
                else
                    $nestedData['supplier'] = 'N/A';
                $nestedData['qty'] = number_format($purchase->qty, config('decimal'), ',', '.');
                if($purchase->purchase_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($purchase->purchase_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_cost'] = number_format(($purchase->total / $purchase->qty), config('decimal'), ',', '.');
                $nestedData['sub_total'] = number_format($purchase->total, config('decimal'), ',', '.');
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }

    public function saleReturnHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('returns')
            ->join('product_returns', 'returns.id', '=', 'product_returns.return_id')
            ->where('product_returns.product_id', $product_id)
            ->whereDate('returns.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('returns.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('returns.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'returns.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->join('customers', 'returns.customer_id', '=', 'customers.id')
                ->join('warehouses', 'returns.warehouse_id', '=', 'warehouses.id')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $returnss = $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('returns.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $returnss =  $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')
                            ->orwhere([
                                ['returns.reference_no', 'LIKE', "%{$search}%"],
                                ['returns.user_id', Auth::id()]
                            ])
                            ->get();
                $totalFiltered = $q->orwhere([
                                    ['returns.reference_no', 'LIKE', "%{$search}%"],
                                    ['returns.user_id', Auth::id()]
                                ])
                                ->count();
            }
            else {
                $returnss =  $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')
                            ->orwhere('returns.reference_no', 'LIKE', "%{$search}%")
                            ->get();
                $totalFiltered = $q->orwhere('returns.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($returnss))
        {
            foreach ($returnss as $key => $returns)
            {
                $nestedData['id'] = $returns->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($returns->created_at));
                $nestedData['reference_no'] = $returns->reference_no;
                $nestedData['warehouse'] = $returns->warehouse_name;
                $nestedData['customer'] = $returns->customer_name.' ['.($returns->customer_number).']';
                $nestedData['qty'] = number_format($returns->qty, config('decimal'), ',', '.');
                if($returns->sale_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($returns->sale_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_price'] = number_format(($returns->total / $returns->qty), config('decimal'), ',', '.');
                $nestedData['sub_total'] = number_format($returns->total, config('decimal'), ',', '.');
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }

    public function purchaseReturnHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('return_purchases')
            ->join('purchase_product_return', 'return_purchases.id', '=', 'purchase_product_return.return_id')
            ->where('purchase_product_return.product_id', $product_id)
            ->whereDate('return_purchases.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('return_purchases.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('return_purchases.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'return_purchases.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->leftJoin('suppliers', 'return_purchases.supplier_id', '=', 'suppliers.id')
                ->join('warehouses', 'return_purchases.warehouse_id', '=', 'warehouses.id')
                ->select('return_purchases.id', 'return_purchases.reference_no', 'return_purchases.created_at', 'return_purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'purchase_product_return.qty', 'purchase_product_return.purchase_unit_id', 'purchase_product_return.total')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $return_purchases = $q->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('return_purchases.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $return_purchases =  $q->orwhere([
                                        ['return_purchases.reference_no', 'LIKE', "%{$search}%"],
                                        ['return_purchases.user_id', Auth::id()]
                                    ])
                                    ->get();
                $totalFiltered = $q->orwhere([
                                    ['return_purchases.reference_no', 'LIKE', "%{$search}%"],
                                    ['return_purchases.user_id', Auth::id()]
                                ])
                                ->count();
            }
            else {
                $return_purchases =  $q->orwhere('return_purchases.reference_no', 'LIKE', "%{$search}%")->get();
                $totalFiltered = $q->orwhere('return_purchases.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($return_purchases))
        {
            foreach ($return_purchases as $key => $return_purchase)
            {
                $nestedData['id'] = $return_purchase->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($return_purchase->created_at));
                $nestedData['reference_no'] = $return_purchase->reference_no;
                $nestedData['warehouse'] = $return_purchase->warehouse_name;
                if($return_purchase->supplier_id)
                    $nestedData['supplier'] = $return_purchase->supplier_name.' ['.($return_purchase->supplier_number).']';
                else
                    $nestedData['supplier'] = 'N/A';
                $nestedData['qty'] = number_format($return_purchase->qty, config('decimal'), ',', '.');
                if($return_purchase->purchase_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($return_purchase->purchase_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_cost'] = number_format(($return_purchase->total / $return_purchase->qty), config('decimal'), ',', '.');
                $nestedData['sub_total'] = number_format($return_purchase->total, config('decimal'), ',', '.');
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }

    public function adjustmentHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');
        $warehouse_type = $request->input('warehouse_type');

        $q = DB::table('adjustments')
            ->join('product_adjustments', 'adjustments.id', '=', 'product_adjustments.adjustment_id')
            ->join('warehouses', 'adjustments.warehouse_id', '=', 'warehouses.id')
            ->where('product_adjustments.product_id', $product_id)
            ->whereDate('adjustments.created_at', '>=', $request->input('starting_date'))
            ->whereDate('adjustments.created_at', '<=', $request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('adjustments.warehouse_id', $warehouse_id);
        if($warehouse_type)
            $q = $q->where('warehouses.type', $warehouse_type);

        $totalData = (clone $q)->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'adjustments.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->select('adjustments.id', 'adjustments.reference_no', 'adjustments.created_at', 'adjustments.note', 'warehouses.name as warehouse_name', 'product_adjustments.qty', 'product_adjustments.action', 'product_adjustments.unit_cost')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $adjustments = $q->get();
        }
        else {
            $search = $request->input('search.value');
            $adjustments = $q->where('adjustments.reference_no', 'LIKE', "%{$search}%")->get();
            $totalFiltered = count($adjustments);
        }
        $data = array();
        if(!empty($adjustments))
        {
            foreach ($adjustments as $key => $adjustment)
            {
                $nestedData['id'] = $adjustment->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($adjustment->created_at));
                $nestedData['reference_no'] = $adjustment->reference_no;
                $nestedData['warehouse'] = $adjustment->warehouse_name;
                $nestedData['action'] = $adjustment->action == '-' ? 'Berkurang (Selisih Minus)' : 'Bertambah (Selisih Plus)';
                $nestedData['qty'] = number_format($adjustment->qty, config('decimal'), ',', '.');
                $nestedData['sub_total'] = number_format($adjustment->qty * $adjustment->unit_cost, config('decimal'), ',', '.');
                $nestedData['note'] = $adjustment->note;
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }

    public function transferHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('transfers')
            ->join('product_transfer', 'transfers.id', '=', 'product_transfer.transfer_id')
            ->where('product_transfer.product_id', $product_id)
            ->whereDate('transfers.created_at', '>=', $request->input('starting_date'))
            ->whereDate('transfers.created_at', '<=', $request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where(function($query) use ($warehouse_id) {
                $query->where('transfers.from_warehouse_id', $warehouse_id)
                    ->orWhere('transfers.to_warehouse_id', $warehouse_id);
            });

        $totalData = (clone $q)->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'transfers.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->join('warehouses as from_warehouses', 'transfers.from_warehouse_id', '=', 'from_warehouses.id')
                ->join('warehouses as to_warehouses', 'transfers.to_warehouse_id', '=', 'to_warehouses.id')
                ->select('transfers.id', 'transfers.reference_no', 'transfers.created_at', 'from_warehouses.name as from_warehouse_name', 'to_warehouses.name as to_warehouse_name', 'product_transfer.qty', 'product_transfer.net_unit_cost', 'product_transfer.total')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $transfers = $q->get();
        }
        else {
            $search = $request->input('search.value');
            $transfers = $q->where('transfers.reference_no', 'LIKE', "%{$search}%")->get();
            $totalFiltered = count($transfers);
        }
        $data = array();
        if(!empty($transfers))
        {
            foreach ($transfers as $key => $transfer)
            {
                $nestedData['id'] = $transfer->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($transfer->created_at));
                $nestedData['reference_no'] = $transfer->reference_no;
                $nestedData['warehouse'] = $transfer->from_warehouse_name.' &rarr; '.$transfer->to_warehouse_name;
                $nestedData['qty'] = number_format($transfer->qty, config('decimal'), ',', '.');
                $nestedData['unit_cost'] = number_format($transfer->net_unit_cost, config('decimal'), ',', '.');
                $nestedData['sub_total'] = number_format($transfer->total, config('decimal'), ',', '.');
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }

    // Menggabungkan semua jenis transaksi (jual, beli, retur jual, retur beli,
    // penyesuaian, transfer) untuk satu produk ke dalam satu daftar tunggal,
    // diurutkan berdasarkan tanggal - untuk tab "Semua Transaksi" di Riwayat
    // Produk. Karena datanya berasal dari 6 tabel yang berbeda strukturnya,
    // paginasi/pencarian dilakukan di PHP (bukan didorong ke SQL) setelah semua
    // baris pada rentang tanggal dan produk terkait dikumpulkan.
    public function allHistoryData(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        abort_unless($role && ($role->hasPermissionTo('product_history') || $role->hasPermissionTo('products-index')), 403);
        $productId = $request->input('product_id');
        $warehouseId = $request->input('warehouse_id');
        if (Auth::user()->role_id > 2) {
            $warehouseId = Auth::user()->warehouse_id;
            abort_unless($warehouseId, 403);
        }
        $warehouses = DB::table('warehouses')
            ->when($warehouseId, fn($q) => $q->where('id', $warehouseId))
            ->when($request->input('warehouse_type'), fn($q, $type) => $q->where('type', $type))
            ->pluck('name', 'id');
        $units = DB::table('units')->get()->keyBy('id');
        $baseQty = function ($qty, $unitId) use ($units) {
            $unit = $units->get($unitId);
            if (!$unit) return (float) $qty;
            return $unit->operator === '*'
                ? (float) $qty * (float) $unit->operation_value
                : (float) $qty / max((float) $unit->operation_value, 0.000001);
        };
        $rows = collect();
        $sources = [
            ['sales', 'product_sales', 'sale_id', 'sale_unit_id', 'qty', 'Penjualan', -1],
            ['purchases', 'product_purchases', 'purchase_id', 'purchase_unit_id', 'recieved', 'Pembelian', 1],
            ['returns', 'product_returns', 'return_id', 'sale_unit_id', 'qty', 'Retur Penjualan', 1],
            ['return_purchases', 'purchase_product_return', 'return_id', 'purchase_unit_id', 'qty', 'Retur Pembelian', -1],
            ['adjustments', 'product_adjustments', 'adjustment_id', null, 'qty', 'Penyesuaian', 1],
        ];
        foreach ($sources as [$header, $detail, $foreignKey, $unitField, $qtyField, $label, $direction]) {
            $query = DB::table($header)->join($detail, "$header.id", '=', "$detail.$foreignKey")
                ->where("$detail.product_id", $productId)
                ->whereIn("$header.warehouse_id", $warehouses->keys())
                ->select("$detail.*", "$header.created_at", "$header.reference_no", "$header.warehouse_id")
                ->orderBy("$detail.id");
            if ($header === 'sales') $query->where('sales.sale_status', 1);
            foreach ($query->get() as $row) {
                $qty = $baseQty($row->$qtyField, $unitField ? $row->$unitField : null);
                $sign = $header === 'adjustments' && $row->action === '-' ? -1 : $direction;
                $rows->push([
                    'type' => $label,
                    'reference_no' => $row->reference_no,
                    'created_at' => $row->created_at,
                    'warehouse' => $warehouses[$row->warehouse_id],
                    'stock_in' => $sign > 0 ? $qty : 0,
                    'stock_out' => $sign < 0 ? $qty : 0,
                    'sub_total' => $header === 'adjustments' ? $qty * $row->unit_cost : $row->total,
                ]);
            }
        }
        $transfers = DB::table('transfers')
            ->join('product_transfer', 'transfers.id', '=', 'product_transfer.transfer_id')
            ->where('product_transfer.product_id', $productId)
            ->whereIn('transfers.status', [1, 3])
            ->select('transfers.created_at', 'transfers.reference_no', 'transfers.status',
                'transfers.from_warehouse_id', 'transfers.to_warehouse_id',
                'product_transfer.qty', 'product_transfer.purchase_unit_id', 'product_transfer.total')
            ->get();
        foreach ($transfers as $transfer) {
            $qty = $baseQty($transfer->qty, $transfer->purchase_unit_id);
            foreach (['from_warehouse_id' => -1, 'to_warehouse_id' => 1] as $field => $direction) {
                if (!$warehouses->has($transfer->$field) || ($direction === 1 && (int) $transfer->status !== 1)) continue;
                $rows->push([
                    'type' => $direction === 1 ? 'Transfer Masuk' : 'Transfer Keluar',
                    'reference_no' => $transfer->reference_no,
                    'created_at' => $transfer->created_at,
                    'warehouse' => $warehouses[$transfer->$field],
                    'stock_in' => $direction === 1 ? $qty : 0,
                    'stock_out' => $direction === -1 ? $qty : 0,
                    'sub_total' => $transfer->total,
                ]);
            }
        }

        // Reconstruct balances from current stock, including movements outside the date filter.
        $balance = (float) DB::table('product_warehouse')->where('product_id', $productId)
            ->whereIn('warehouse_id', $warehouses->keys())->sum('qty');
        $rows = $rows->sortBy('created_at', SORT_REGULAR, true)->values()->map(function ($row) use (&$balance) {
            $row['balance'] = $balance;
            $balance -= $row['stock_in'] - $row['stock_out'];
            return $row;
        });
        $rows = $rows->filter(function ($row) use ($request) {
            $date = substr($row['created_at'], 0, 10);
            return $date >= $request->input('starting_date') && $date <= $request->input('ending_date');
        })->values();
        $totalData = $rows->count();
        $search = $request->input('search.value');
        if ($search) {
            $rows = $rows->filter(fn($row) => stripos($row['reference_no'], $search) !== false
                || stripos($row['type'], $search) !== false || stripos($row['warehouse'], $search) !== false)->values();
        }
        $totalFiltered = $rows->count();
        $length = (int) $request->input('length', 10);
        $data = $rows->slice(max(0, (int) $request->input('start')), $length === -1 ? $totalFiltered : max(1, $length))
            ->values()->map(function ($row, $key) {
                $row['key'] = $key;
                $row['date'] = date(config('date_format'), strtotime($row['created_at']));
                foreach (['stock_in', 'stock_out', 'balance', 'sub_total'] as $field) {
                    $row[$field] = number_format($row[$field], config('decimal'), ',', '.');
                }
                return $row;
            });
        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    public function variantData($id)
    {
        if(Auth::user()->role_id > 2) {
            return ProductVariant::join('variants', 'product_variants.variant_id', '=', 'variants.id')
                ->join('product_warehouse', function($join) {
                    $join->on('product_variants.product_id', '=', 'product_warehouse.product_id');
                    $join->on('product_variants.variant_id', '=', 'product_warehouse.variant_id');
                })
                ->select('variants.name', 'product_variants.item_code', 'product_variants.additional_cost', 'product_variants.additional_price', 'product_warehouse.qty')
                ->where([
                    ['product_warehouse.product_id', $id],
                    ['product_warehouse.warehouse_id', Auth::user()->warehouse_id]
                ])
                ->orderBy('product_variants.position')
                ->get();
        }
        else {
            return ProductVariant::join('variants', 'product_variants.variant_id', '=', 'variants.id')
                ->select('variants.name', 'product_variants.item_code', 'product_variants.additional_cost', 'product_variants.additional_price', 'product_variants.qty')
                ->orderBy('product_variants.position')
                ->where('product_id', $id)
                ->get();
        }
    }

    public function edit($id)
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('products-edit')) {
            $lims_product_list_without_variant = $this->productWithoutVariant();
            $lims_product_list_with_variant = $this->productWithVariant();
            $lims_brand_list = Brand::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $lims_unit_list = Unit::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_product_data = Product::where('id', $id)->first();
            if($lims_product_data->variant_option) {
                $lims_product_data->variant_option = json_decode($lims_product_data->variant_option);
                $lims_product_data->variant_value = json_decode($lims_product_data->variant_value);
            }
            $lims_product_variant_data = $lims_product_data->variant()->orderBy('position')->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $noOfVariantValue = 0;
            $custom_fields = CustomField::where('belongs_to', 'product')->get();
            $lims_customer_group_list = CustomerGroup::where('is_active', true)->get();
            $lims_product_unit_list = ProductUnit::with('priceLevels', 'priceTiers')->where('product_id', $id)->orderBy('sort_order')->get();

            if(in_array('ecommerce', explode(',',config('addons')))) {
                $product_arr = explode(',',$lims_product_data->related_products);
                $related_products = DB::table('products')->whereIn('id',$product_arr)->get();
                return view('backend.product.edit',compact('related_products','lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_product_data', 'lims_product_variant_data', 'lims_warehouse_list', 'noOfVariantValue', 'custom_fields', 'lims_customer_group_list', 'lims_product_unit_list'));
            }
            return view('backend.product.edit',compact('lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_product_data', 'lims_product_variant_data', 'lims_warehouse_list', 'noOfVariantValue', 'custom_fields', 'lims_customer_group_list', 'lims_product_unit_list'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function updateProduct(Request $request)
    {
        if(!env('USER_VERIFIED')) {
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
        }
        else {
            $this->validate($request, [
                'name' => [
                    'max:255',
                    Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                        return $query->where('is_active', 1);
                    }),
                ],

                'code' => [
                    'max:255',
                    Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                        return $query->where('is_active', 1);
                    }),
                ]
            ]);

            $lims_product_data = Product::findOrFail($request->input('id'));
            $data = $request->except('image', 'file', 'prev_img');
            $data = $this->normalizeMoneyFields($data);
            $data['name'] = htmlspecialchars(trim($data['name']), ENT_QUOTES);


            if(in_array('ecommerce', explode(',',config('addons')))) {
                $data['slug'] = Str::slug($data['name'], '-');
                $data['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['slug']);
                $data['slug'] = str_replace( '\/', '/', $data['slug'] );
                $data['related_products'] = rtrim($request->products, ",");

                if(isset($request->in_stock))
                    $data['in_stock'] = $request->input('in_stock');
                else
                    $data['in_stock'] = 0;

                if(isset($request->is_online))
                    $data['is_online'] = $request->input('is_online');
                else
                    $data['is_online'] = 0;
            }

            if($data['type'] == 'combo') {
                $data['product_list'] = implode(",", $data['product_id']);
                $data['variant_list'] = implode(",", $data['variant_id']);
                $data['qty_list'] = implode(",", $data['product_qty']);
                $data['price_list'] = implode(",", $data['unit_price']);
                $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
            }
            elseif($data['type'] == 'digital' || $data['type'] == 'service')
                $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;

            // Satuan Pembelian tidak lagi diisi manual di form: default ke Satuan Produk (unit_id)
            if(empty($data['purchase_unit_id']))
                $data['purchase_unit_id'] = $data['unit_id'];

            $this->prepareSaleUnitIds($data);

            if(!isset($data['featured']))
                $data['featured'] = 0;

            if(!isset($data['is_embeded']))
                $data['is_embeded'] = 0;

            if(!isset($data['promotion']))
                $data['promotion'] = null;

            if(!isset($data['is_batch']))
                $data['is_batch'] = null;

            if(!isset($data['is_imei']))
                $data['is_imei'] = null;

            if(!isset($data['is_sync_disable']) && \Schema::hasColumn('products', 'is_sync_disable'))
                $data['is_sync_disable'] = null;

            $data['product_details'] = str_replace('"', '@', $data['product_details']);
            // $data['product_details'] = $data['product_details'];
            if($data['starting_date'])
                $data['starting_date'] = date('Y-m-d', strtotime($data['starting_date']));
            if($data['last_date'])
                $data['last_date'] = date('Y-m-d', strtotime($data['last_date']));

            $previous_images = [];
            //dealing with previous images
            if($request->prev_img) {
                foreach ($request->prev_img as $key => $prev_img) {
                    if(!in_array($prev_img, $previous_images))
                        $previous_images[] = $prev_img;
                }
                $lims_product_data->image = implode(",", $previous_images);
                $lims_product_data->save();
            }
            else {
                $lims_product_data->image = null;
                $lims_product_data->save();
            }

            //dealing with new images
            if($request->image) {
                if ( !file_exists("public/images/product/large") && !is_dir("public/images/product/large") ) {
                    mkdir("public/images/product/large");       
                }
                if ( !file_exists("public/images/product/medium") && !is_dir("public/images/product/medium") ) {
                    mkdir("public/images/product/medium");       
                }
                if ( !file_exists("public/images/product/small") && !is_dir("public/images/product/small") ) {
                    mkdir("public/images/product/small");       
                }
                $images = $request->image;
                $image_names = [];
                $length = count(explode(",", $lims_product_data->image));
                foreach ($images as $key => $image) {
                    $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
                    if(!config('database.connections.saleprosaas_landlord')) {
                        $imageName = date("Ymdhis") . ($length + $key+1) . '.' . $ext;
                        $image->move('public/images/product', $imageName);
                        $img_lg = Image::make('public/images/product/'. $imageName)->fit(500, 500)->save('public/images/product/large/'. $imageName, 90);
                        $img_md = Image::make('public/images/product/'. $imageName)->fit(250, 250)->save('public/images/product/medium/'. $imageName, 100);
                        $img_sm = Image::make('public/images/product/'. $imageName)->fit(100, 100)->save('public/images/product/small/'. $imageName, 100);
                    }else{
                        $imageName = $this->getTenantId() . '_' . date("Ymdhis") . ($length + $key+1) . '.' . $ext;
                        $image->move('public/images/product', $imageName);
                        $img_lg = Image::make('public/images/product/'. $imageName)->fit(500, 500)->save('public/images/product/large/'. $imageName, 90);
                        $img_md = Image::make('public/images/product/'. $imageName)->fit(250, 250)->save('public/images/product/medium/'. $imageName, 100);
                        $img_sm = Image::make('public/images/product/'. $imageName)->fit(100, 100)->save('public/images/product/small/'. $imageName, 100);
                    }
                    $image_names[] = $imageName;
                }
                if($lims_product_data->image)
                    $data['image'] = $lims_product_data->image. ',' . implode(",", $image_names);
                else
                    $data['image'] = implode(",", $image_names);
            }
            else
                $data['image'] = $lims_product_data->image;

            $file = $request->file;
            if ($file) {
                $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
                $fileName = strtotime(date('Y-m-d H:i:s'));
                $fileName = $fileName . '.' . $ext;
                $file->move('public/product/files', $fileName);
                $data['file'] = $fileName;
            }

            $old_product_variant_ids = ProductVariant::where('product_id', $request->input('id'))->pluck('id')->toArray();
            $new_product_variant_ids = [];
            //dealing with product variant
            if(isset($data['is_variant'])) {
                if(isset($data['variant_option']) && isset($data['variant_value'])) {
                    $data['variant_option'] = json_encode($data['variant_option']);
                    $data['variant_value'] = json_encode($data['variant_value']);
                }
                foreach ($data['variant_name'] as $key => $variant_name) {
                    $lims_variant_data = Variant::firstOrCreate(['name' => $data['variant_name'][$key]]);
                    $lims_product_variant_data = ProductVariant::where([
                                                    ['product_id', $lims_product_data->id],
                                                    ['variant_id', $lims_variant_data->id]
                                                ])->first();
                    if($lims_product_variant_data) {
                        $lims_product_variant_data->update([
                            'position' => $key+1,
                            'item_code' => $data['item_code'][$key],
                            'additional_cost' => $data['additional_cost'][$key],
                            'additional_price' => $data['additional_price'][$key]
                        ]);
                    }
                    else {
                        $lims_product_variant_data = new ProductVariant();
                        $lims_product_variant_data->product_id = $lims_product_data->id;
                        $lims_product_variant_data->variant_id = $lims_variant_data->id;
                        $lims_product_variant_data->position = $key + 1;
                        $lims_product_variant_data->item_code = $data['item_code'][$key];
                        $lims_product_variant_data->additional_cost = $data['additional_cost'][$key];
                        $lims_product_variant_data->additional_price = $data['additional_price'][$key];
                        $lims_product_variant_data->qty = 0;
                        $lims_product_variant_data->save();
                    }
                    $new_product_variant_ids[] = $lims_product_variant_data->id;
                }
            }
            else {
                $data['is_variant'] = null;
                $data['variant_option'] = null;
                $data['variant_value'] = null;
            }
            //deleting old product variant if not exist
            foreach ($old_product_variant_ids as $key => $product_variant_id) {
                if (!in_array($product_variant_id, $new_product_variant_ids))
                    ProductVariant::find($product_variant_id)->delete();
            }
            if(isset($data['is_diffPrice'])) {
                foreach ($data['diff_price'] as $key => $diff_price) {
                    if($diff_price) {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $data['warehouse_id'][$key])->first();
                        if($lims_product_warehouse_data) {
                            $lims_product_warehouse_data->price = $diff_price;
                            $lims_product_warehouse_data->save();
                        }
                        else {
                            Product_Warehouse::create([
                                "product_id" => $lims_product_data->id,
                                "warehouse_id" => $data["warehouse_id"][$key],
                                "qty" => 0,
                                "price" => $diff_price
                            ]);
                        }
                    }
                }
            }
            else {
                $data['is_diffPrice'] = false;
                if(isset($data['warehouse_id'])){
                    foreach ($data['warehouse_id'] as $key => $warehouse_id) {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $warehouse_id)->first();
                        if($lims_product_warehouse_data) {
                            $lims_product_warehouse_data->price = null;
                            $lims_product_warehouse_data->save();
                        }
                    }
                }
            }
            $lims_product_data->update($data);
            $this->syncProductUnits($lims_product_data->id, $data);
            //inserting data for custom fields
            $custom_field_data = [];
            $custom_fields = CustomField::where('belongs_to', 'product')->select('name', 'type')->get();
            foreach ($custom_fields as $type => $custom_field) {
                $field_name = str_replace(' ', '_', strtolower($custom_field->name));
                if(isset($data[$field_name])) {
                    if($custom_field->type == 'checkbox' || $custom_field->type == 'multi_select')
                        $custom_field_data[$field_name] = implode(",", $data[$field_name]);
                    else
                        $custom_field_data[$field_name] = $data[$field_name];
                }
            }
            if(count($custom_field_data))
                DB::table('products')->where('id', $lims_product_data->id)->update($custom_field_data);
            $this->cacheForget('product_list');
            $this->cacheForget('product_list_with_variant');
            \Session::flash('edit_message', 'Product updated successfully');
        }
    }

    public function generateCode()
    {
        $id = Keygen::numeric(8)->generate();
        return $id;
    }

    public function search(Request $request)
    {
        $product_code = explode(" (", $request['data']);
        $lims_product_data = Product::where('code', $product_code[0])->first();

        $product[] = $lims_product_data->name;
        $product[] = $lims_product_data->code;
        $product[] = $lims_product_data->qty;
        $product[] = $lims_product_data->price;
        $product[] = $lims_product_data->id;
        return $product;
    }

    public function saleUnit($id)
    {
        $unit = Unit::where("base_unit", $id)->orWhere('id', $id)->pluck('unit_name','id');
        return json_encode($unit);
    }

    /**
     * Same unit list as saleUnit() but with conversion metadata attached —
     * used to populate the "Jml Konversi" column in the per-unit pricing
     * table (Mode Harga: Satuan / Level Harga / Jumlah).
     */
    public function saleUnitDetailed($id)
    {
        $units = Unit::where("base_unit", $id)->orWhere('id', $id)->get(['id', 'unit_name', 'operator', 'operation_value']);
        $result = [];
        foreach ($units as $unit) {
            $conversion = 1;
            if($unit->operator == '*' && $unit->operation_value)
                $conversion = (float) $unit->operation_value;
            elseif($unit->operator == '/' && $unit->operation_value)
                $conversion = 1 / (float) $unit->operation_value;
            $result[] = [
                'id' => $unit->id,
                'name' => $unit->unit_name,
                'conversion' => $conversion,
                'is_base' => $unit->id == $id,
            ];
        }
        return response()->json($result);
    }

    public function getData($id, $variant_id)
    {
        if($variant_id) {
            $data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.name', 'product_variants.item_code')
                ->where([
                    ['products.id', $id],
                    ['product_variants.variant_id', $variant_id]
                ])->first();
            $data->code = $data->item_code;
        }
        else
            $data = Product::select('name', 'code')->find($id);
        return $data;
    }

    public function productWarehouseData($id)
    {
        $warehouse = [];
        $qty = [];
        $batch = [];
        $expired_date = [];
        $imei_number = [];
        $warehouse_name = [];
        $variant_name = [];
        $variant_qty = [];
        $product_warehouse = [];
        $product_variant_warehouse = [];
        $lims_product_data = Product::select('id', 'is_variant')->find($id);
        if($lims_product_data->is_variant) {
            $lims_product_variant_warehouse_data = Product_Warehouse::where('product_id', $lims_product_data->id)->orderBy('warehouse_id')->get();
            $lims_product_warehouse_data = Product_Warehouse::select('warehouse_id', DB::raw('sum(qty) as qty'))->where('product_id', $id)->groupBy('warehouse_id')->get();
            foreach ($lims_product_variant_warehouse_data as $key => $product_variant_warehouse_data) {
                $lims_warehouse_data = Warehouse::find($product_variant_warehouse_data->warehouse_id);
                $lims_variant_data = Variant::find($product_variant_warehouse_data->variant_id);
                $warehouse_name[] = $lims_warehouse_data->name;
                $variant_name[] = $lims_variant_data->name;
                $variant_qty[] = $product_variant_warehouse_data->qty;
            }
        }
        else {
            $lims_product_warehouse_data = Product_Warehouse::where('product_id', $id)->orderBy('warehouse_id', 'asc')->get();
        }
        foreach ($lims_product_warehouse_data as $key => $product_warehouse_data) {
            $lims_warehouse_data = Warehouse::find($product_warehouse_data->warehouse_id);
            if($product_warehouse_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no', 'expired_date')->find($product_warehouse_data->product_batch_id);
                $batch_no = $product_batch_data->batch_no;
                $expiredDate = date(config('date_format'), strtotime($product_batch_data->expired_date));
            }
            else {
                $batch_no = 'N/A';
                $expiredDate = 'N/A';
            }
            $warehouse[] = $lims_warehouse_data->name;
            $batch[] = $batch_no;
            $expired_date[] = $expiredDate;
            $qty[] = $product_warehouse_data->qty;
            if($product_warehouse_data->imei_number)
                $imei_number[] = $product_warehouse_data->imei_number;
            else
                $imei_number[] = 'N/A';
        }

        $product_warehouse = [$warehouse, $qty, $batch, $expired_date, $imei_number];
        $product_variant_warehouse = [$warehouse_name, $variant_name, $variant_qty];
        return ['product_warehouse' => $product_warehouse, 'product_variant_warehouse' => $product_variant_warehouse];
    }

    public function printBarcode(Request $request)
    {
        if($request->input('data'))
            $preLoadedproduct = $this->limsProductSearch($request);
        else
            $preLoadedproduct = null;
        $lims_product_list_without_variant = $this->productWithoutVariant();
        $lims_product_list_with_variant = $this->productWithVariant();

        return view('backend.product.print_barcode', compact('lims_product_list_without_variant', 'lims_product_list_with_variant', 'preLoadedproduct'));
    }

    public function productWithoutVariant()
    {
        return Product::ActiveStandard()->select('id', 'name', 'code')
                ->whereNull('is_variant')->get();
    }

    public function productWithVariant()
    {
        return Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->ActiveStandard()
                ->whereNotNull('is_variant')
                ->select('products.id', 'products.name', 'product_variants.item_code')
                ->orderBy('position')->get();
    }

    public function limsProductSearch(Request $request)
    {
        $product_code = explode("(", $request['data']);
        $product_code[0] = rtrim($product_code[0], " ");
        $lims_product_data = Product::where([
            ['code', $product_code[0] ],
            ['is_active', true]
        ])->first();
        if(!$lims_product_data) {
            $lims_product_data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.*', 'product_variants.item_code', 'product_variants.variant_id', 'product_variants.additional_price')
                ->where('product_variants.item_code', $product_code[0])
                ->first();

            $variant_id = $lims_product_data->variant_id;
            $additional_price = $lims_product_data->additional_price;
        }
        else {
            $variant_id = '';
            $additional_price = 0;
        }
        $product[] = $lims_product_data->name;
        if($lims_product_data->is_variant)
            $product[] = $lims_product_data->item_code;
        else
            $product[] = $lims_product_data->code;

        $product[] = $lims_product_data->price + $additional_price;
        $product[] = DNS1D::getBarcodePNG($product[1], $lims_product_data->barcode_symbology);
        $product[] = $lims_product_data->promotion_price;
        $product[] = config('currency');
        $product[] = config('currency_position');
        $product[] = $lims_product_data->qty;
        $product[] = $lims_product_data->id;
        $product[] = $variant_id;
        return $product;
    }

    /*public function getBarcode()
    {
        return DNS1D::getBarcodePNG('72782608', 'C128');
    }*/

    public function checkBatchAvailability($product_id, $batch_no, $warehouse_id)
    {
        $product_batch_data = ProductBatch::where([
            ['product_id', $product_id],
            ['batch_no', $batch_no]
        ])->first();
        if($product_batch_data) {
            $product_warehouse_data = Product_Warehouse::select('qty')
            ->where([
                ['product_batch_id', $product_batch_data->id],
                ['warehouse_id', $warehouse_id]
            ])->first();
            if($product_warehouse_data) {
                $data['qty'] = $product_warehouse_data->qty;
                $data['product_batch_id'] = $product_batch_data->id;
                $data['expired_date'] = date(config('date_format'), strtotime($product_batch_data->expired_date));
                $data['message'] = 'ok';
            }
            else {
                $data['qty'] = 0;
                $data['message'] = 'This Batch does not exist in the selected warehouse!';
            }
        }
        else {
            $data['message'] = 'Wrong Batch Number!';
        }
        return $data;
    }

    public function importProduct(Request $request)
    {
        //get file
        $upload=$request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if($ext != 'csv')
            return redirect()->back()->with('message', 'Please upload a CSV file');

        $filePath=$upload->getRealPath();
        //open and read
        $file=fopen($filePath, 'r');
        $firstLine = fgets($file);
        $delimiter = ',';
        foreach ([',' , ';', "\t"] as $candidate) {
            if (substr_count($firstLine, $candidate) > substr_count($firstLine, $delimiter)) {
                $delimiter = $candidate;
            }
        }
        rewind($file);
        $header= fgetcsv($file, 0, $delimiter);
        $escapedHeader=[];
        //validate
        foreach ($header as $key => $value) {
            $lheader=strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value)));
            $escapedItem=preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        $required_headers = ['name', 'code', 'type', 'category', 'unitcode', 'cost', 'price'];
        foreach ($required_headers as $required_header) {
            if (!in_array($required_header, $escapedHeader)) {
                return redirect()->back()->with('not_permitted', 'Format CSV tidak sesuai. Kolom wajib yang belum ada: ' . $required_header . '. Pakai contoh file import terbaru.');
            }
        }
        //looping through other columns
        while($columns=fgetcsv($file, 0, $delimiter))
        {
            if (!array_filter($columns, function($value) { return trim((string)$value) !== ''; })) {
                continue;
            }
            if (count($columns) < count($escapedHeader)) {
                $columns = array_pad($columns, count($escapedHeader), '');
            } elseif (count($columns) > count($escapedHeader)) {
                $columns = array_slice($columns, 0, count($escapedHeader));
            }
            $data= array_combine($escapedHeader, $columns);
            $data = array_merge([
                'image' => '',
                'brand' => '',
                'productdetails' => '',
                'variantvalue' => '',
                'variantname' => '',
                'itemcode' => '',
                'additionalcost' => '',
                'additionalprice' => '',
            ], $data ?: []);

           if($data['brand'] != 'N/A' && $data['brand'] != ''){
                $lims_brand_data = Brand::firstOrCreate(['title' => $data['brand'], 'is_active' => true]);
                $brand_id = $lims_brand_data->id;
           }
            else
                $brand_id = null;

           $lims_category_data = Category::firstOrCreate(['name' => $data['category'], 'is_active' => true]);

           $lims_unit_data = Unit::where('unit_code', $data['unitcode'])->first();
           if(!$lims_unit_data)
                return redirect()->back()->with('not_permitted', 'Unit code does not exist in the database.');

           $product = Product::firstOrNew([ 'name'=>$data['name'], 'is_active'=>true ]);
            if($data['image'])
                $product->image = $data['image'];
            else
                $product->image = 'zummXD2dvAtI.png';

           $product->name = htmlspecialchars(trim($data['name']));
            if(in_array('ecommerce', explode(',',config('addons')))) {
                $data['slug'] = Str::slug($data['name'], '-');
                $data['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['slug']);
                $data['slug'] = str_replace( '\/', '/', $data['slug'] );
                $product->slug = $data['slug'];
                $product->in_stock = true;
            }
           $product->code = $data['code'];
           $product->type = strtolower($data['type']);
           $product->barcode_symbology = 'C128';
           $product->brand_id = $brand_id;
           $product->category_id = $lims_category_data->id;
           $product->unit_id = $lims_unit_data->id;
           $product->purchase_unit_id = $lims_unit_data->id;
           $product->sale_unit_id = $lims_unit_data->id;
           $product->cost = str_replace(",","",$data['cost']);
           $product->price = str_replace(",","",$data['price']);
           $product->tax_method = 1;
           $product->qty = 0;
           $product->product_details = $data['productdetails'];
           $product->is_active = true;
           $product->save();
           //fetching warehouse ids
           $warehouse_ids = Warehouse::where('is_active', true)->pluck('id');
           //dealing with variants
           if($data['variantvalue'] && $data['variantname']) {
                $variant_option = [];
                $variant_value = [];
                $variantInfo = explode(",", $data['variantvalue']);
                foreach ($variantInfo as $key => $info) {
                    $variant_option[] = strtok($info, "[");
                    $variant_value[] = str_replace("/", ",", substr($info, strpos($info, "[")+1, (strpos($info, "]") - strpos($info, "[") - 1)));
                }
                $product->variant_option = json_encode($variant_option);
                $product->variant_value = json_encode($variant_value);
                $product->is_variant = true;
                $product->save();

                $variant_names = explode(",", $data['variantname']);
                $item_codes = explode(",", $data['itemcode']);
                $additional_costs = explode(",", $data['additionalcost']);
                $additional_prices = explode(",", $data['additionalprice']);
                foreach ($variant_names as $key => $variant_name) {
                    $variant = Variant::firstOrCreate(['name' => $variant_name]);
                    if($data['itemcode'])
                        $item_code = $item_codes[$key];
                    else
                        $item_code = $variant_name . '-' . $data['code'];

                    if($data['additionalcost'])
                        $additional_cost = $additional_costs[$key];
                    else
                        $additional_cost = 0;

                    if($data['additionalprice'])
                        $additional_price = $additional_prices[$key];
                    else
                        $additional_price = 0;

                    ProductVariant::create([
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'position' => $key + 1,
                        'item_code' => $item_code,
                        'additional_cost' => $additional_cost,
                        'additional_price' => $additional_price,
                        'qty' => 0
                    ]);
                    //inserting data to product warehouse
                    if(config('without_stock') == 'yes') {
                        foreach ($warehouse_ids as $key => $warehouse_id) {
                            Product_Warehouse::create([
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'warehouse_id' => $warehouse_id,
                                'qty' => 0
                            ]);
                        }
                    }
                }
           }
           elseif(config('without_stock') == 'yes') {
                foreach ($warehouse_ids as $key => $warehouse_id) {
                    Product_Warehouse::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse_id,
                        'qty' => 0
                    ]);
                }
            }
         }
         $this->cacheForget('product_list');
         $this->cacheForget('product_list_with_variant');
         return redirect('products')->with('import_message', 'Product imported successfully');
    }

    public function allProductInStock()
    {
        if(!in_array('ecommerce',explode(',', config('addons'))))
            return redirect()->back()->with('not_permitted', 'Please install the ecommerce addon!');
        Product::where('is_active', true)->update(['in_stock' => true]);
        return redirect()->back()->with('create_message', 'All Products set to in stock successfully!');
    }

    public function showAllProductOnline()
    {
        if(!in_array('ecommerce',explode(',', config('addons'))))
            return redirect()->back()->with('not_permitted', 'Please install the ecommerce addon!');
        Product::where('is_active', true)->update(['is_online' => true]);
        return redirect()->back()->with('create_message', 'All Products will be showed to online!');
    }

    public function deleteBySelection(Request $request)
    {
        // "Delete" is a soft deactivation (is_active=false): the product stops
        // showing up in sale/purchase pickers but its history/images stay
        // intact so it can be reactivated later from the Non-aktif filter.
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data = Product::findOrFail($id);
            $lims_product_data->is_active = false;
            $lims_product_data->save();
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return 'Product non-aktifkan successfully!';
    }

    public function destroy($id)
    {
        if(!env('USER_VERIFIED')) {
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
        }
        else {
            // "Delete" is a soft deactivation (is_active=false): images are no
            // longer removed from disk so a deactivated product can be
            // reactivated later without losing its photos.
            $lims_product_data = Product::findOrFail($id);
            $lims_product_data->is_active = false;
            $lims_product_data->save();
            $this->cacheForget('product_list');
            $this->cacheForget('product_list_with_variant');
            return redirect('products')->with('message', 'Product non-aktifkan successfully');
        }
    }

    public function activate($id)
    {
        if(!env('USER_VERIFIED')) {
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
        }
        $lims_product_data = Product::findOrFail($id);
        $lims_product_data->is_active = true;
        $lims_product_data->save();
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return redirect('products')->with('message', 'Product aktifkan successfully');
    }

    public function activateBySelection(Request $request)
    {
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data = Product::findOrFail($id);
            $lims_product_data->is_active = true;
            $lims_product_data->save();
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return 'Product aktifkan successfully!';
    }
}
