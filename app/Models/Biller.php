<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Biller extends Model
{
    protected $fillable =[
        "name", "image", "company_name", "vat_number",
        "email", "phone_number", "address", "city",
        "state", "postal_code", "country", "is_active"
    ];

    public function sale()
    {
    	return $this->hasMany('App\Models\Sale');
    }

    /**
     * Biller yang dipakai kalau transaksi tidak menyertakan biller yang sah.
     *
     * Tabel billers yang kosong (atau biller yang sudah dihapus) bikin
     * sales.biller_id yatim, dan itu mematikan halaman struk
     * ("Attempt to read property company_name on null") serta retur penjualan.
     * Daripada memblokir kasir berjualan, biller default dibuatkan sekali dari
     * data Pengaturan Umum. Admin tinggal merapikannya di menu Biller.
     */
    public static function defaultForTransaction()
    {
        $biller = self::where('is_active', true)->orderBy('id')->first()
            ?: self::orderBy('id')->first();
        if ($biller) {
            return $biller;
        }

        $setting = GeneralSetting::latest()->first();
        $companyName = ($setting && $setting->company_name)
            ? $setting->company_name
            : (($setting && $setting->site_title) ? $setting->site_title : 'Toko');

        return self::create([
            'name'         => $companyName,
            'company_name' => $companyName,
            'email'        => ($setting && $setting->email) ? $setting->email : 'no-reply@localhost',
            'phone_number' => ($setting && $setting->phone) ? $setting->phone : '-',
            'address'      => '-',
            'city'         => '-',
            'is_active'    => true,
        ]);
    }
}
