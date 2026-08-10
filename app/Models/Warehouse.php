<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Auth;

class Warehouse extends Model
{
    protected $fillable =[

        "name", "phone", "email", "address", "is_active", "type"
    ];

    /**
     * Penjualan hanya boleh dilakukan dari gudang bertipe "toko".
     * Untuk kasir (role_id > 2) daftarnya dipersempit lagi ke gudang yang
     * sudah ditetapkan admin di profil user, supaya kasir tidak bisa memilih sendiri.
     */
    public static function sellableList()
    {
        $query = self::where([
            ['is_active', true],
            ['type', 'toko'],
        ]);

        if (Auth::check() && Auth::user()->role_id > 2)
            $query->where('id', Auth::user()->warehouse_id);

        return $query->orderBy('name')->get();
    }

    /**
     * Validasi sisi server: gudang boleh dipakai untuk transaksi penjualan?
     */
    public static function isSellable($warehouse_id)
    {
        if (!$warehouse_id)
            return false;

        if (Auth::check() && Auth::user()->role_id > 2 && (int)$warehouse_id !== (int)Auth::user()->warehouse_id)
            return false;

        return self::where([
            ['id', $warehouse_id],
            ['is_active', true],
            ['type', 'toko'],
        ])->exists();
    }

    public function product()
    {
    	return $this->hasMany('App\Models\Product');

    }

    public function racks()
    {
        return $this->hasMany('App\Models\Rack')->where('is_active', true);
    }
}
