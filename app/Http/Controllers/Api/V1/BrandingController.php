<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ReportBrand;
use Illuminate\Http\JsonResponse;

/** Identitas sekolah untuk tampilan GrisaKAD (nama, alamat, logo). Publik karena dipakai halaman login; tidak memuat data pribadi. */
class BrandingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $brand = ReportBrand::get();

        return response()
            ->json(['data' => [
                'name' => $brand['name'],
                'address' => $brand['address'],
                'logo' => $brand['logo'],
                'color' => $brand['color'],
            ]])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
