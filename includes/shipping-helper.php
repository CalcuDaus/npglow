<?php
/**
 * NPGLOW Pluggable Shipping Engine
 * Supports tiered dynamic calculations by location/weight & ready for API integration (RajaOngkir/Biteship).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/settings-helper.php';

class NPGLOW_Shipping {

    /**
     * List of Indonesian Provinces and major cities for checkout selection
     */
    public static function get_location_data(): array {
        return [
            'DKI Jakarta' => ['Jakarta Barat', 'Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Timur', 'Jakarta Utara', 'Kepulauan Seribu'],
            'Jawa Barat' => ['Bandung', 'Bandung Barat', 'Bekasi', 'Bogor', 'Ciamis', 'Cianjur', 'Cirebon', 'Garut', 'Indramayu', 'Karawang', 'Kuningan', 'Majalengka', 'Pangandaran', 'Purwakarta', 'Subang', 'Sukabumi', 'Sumedang', 'Tasikmalaya', 'Depok'],
            'Banten' => ['Tangerang', 'Tangerang Selatan', 'Serang', 'Cilegon', 'Lebak', 'Pandeglang'],
            'Jawa Tengah' => ['Semarang', 'Surakarta (Solo)', 'Magelang', 'Pekalongan', 'Salatiga', 'Tegal', 'Banyumas (Purwokerto)', 'Batang', 'Blora', 'Boyolali', 'Brebes', 'Cilacap', 'Demak', 'Grobogan', 'Jepara', 'Karanganyar', 'Kebumen', 'Kendal', 'Klaten', 'Kudus', 'Pati', 'Purbalingga', 'Purworejo', 'Rembang', 'Sragen', 'Sukoharjo', 'Temanggung', 'Wonogiri', 'Wonosobo'],
            'DI Yogyakarta' => ['Yogyakarta', 'Bantul', 'Gunungkidul', 'Kulon Progo', 'Sleman'],
            'Jawa Timur' => ['Surabaya', 'Malang', 'Batu', 'Blitar', 'Kediri', 'Madiun', 'Mojokerto', 'Pasuruan', 'Probolinggo', 'Banyuwangi', 'Bojonegoro', 'Bondowoso', 'Gresik', 'Jember', 'Jombang', 'Lamongan', 'Lumajang', 'Magetan', 'Nganjuk', 'Ngawi', 'Pacitan', 'Pamekasan', 'Ponorogo', 'Sampang', 'Sidoarjo', 'Situbondo', 'Sumenep', 'Tuban', 'Tulungagung'],
            'Sumatera Utara' => ['Medan', 'Binjai', 'Deli Serdang', 'Pematangsiantar', 'Tebing Tinggi', 'Tanjungbalai', 'Asahan', 'Karo', 'Langkat', 'Simalungun', 'Toba', 'Tapanuli Utara', 'Labuhanbatu'],
            'Sumatera Barat' => ['Padang', 'Bukittinggi', 'Payakumbuh', 'Pariaman', 'Solok', 'Agam', 'Pasaman', 'Tanah Datar'],
            'Riau' => ['Pekanbaru', 'Dumai', 'Bengkalis', 'Kampar', 'Siak', 'Indragiri Hilir', 'Rokan Hulu'],
            'Kepulauan Riau' => ['Batam', 'Tanjungpinang', 'Bintan', 'Karimun', 'Natuna'],
            'Sumatera Selatan' => ['Palembang', 'Prabumulih', 'Lubuklinggau', 'Banyuasin', 'Ogan Ilir', 'Muara Enim'],
            'Lampung' => ['Bandar Lampung', 'Metro', 'Lampung Selatan', 'Lampung Tengah', 'Pringsewu'],
            'Bali' => ['Denpasar', 'Badung', 'Gianyar', 'Tabanan', 'Buleleng', 'Klungkung', 'Karangasem'],
            'Kalimantan Barat' => ['Pontianak', 'Singkawang', 'Kuburaya', 'Sambas', 'Sintang'],
            'Kalimantan Timur' => ['Samarinda', 'Balikpapan', 'Bontang', 'Kutai Kartanegara', 'Berau'],
            'Kalimantan Selatan' => ['Banjarmasin', 'Banjarbaru', 'Banjar', 'Barito Kuala', 'Tanah Laut'],
            'Sulawesi Selatan' => ['Makassar', 'Gowa', 'Maros', 'Parepare', 'Palopo', 'Bone'],
            'Sulawesi Utara' => ['Manado', 'Bitung', 'Tomohon', 'Minahasa'],
            'Nusa Tenggara Barat' => ['Mataram', 'Lombok Barat', 'Lombok Timur', 'Sumbawa', 'Bima'],
            'Nusa Tenggara Timur' => ['Kupang', 'Manggarai Barat (Labuan Bajo)', 'Ende', 'Sikka (Maumere)'],
            'Papua' => ['Jayapura', 'Merauke', 'Biak Numfor', 'Mimika (Timika)']
        ];
    }

    /**
     * Supported Couriers and standard services
     */
    public static function get_available_couriers(): array {
        return [
            'jnt' => [
                'code' => 'jnt',
                'name' => 'J&T Express',
                'services' => [
                    ['code' => 'EZ', 'name' => 'J&T EZ (Reguler)', 'etd' => '1 - 3 Hari', 'type' => 'regular'],
                    ['code' => 'SUPER', 'name' => 'J&T Super (Cepat)', 'etd' => '1 - 2 Hari', 'type' => 'express']
                ]
            ],
            'jne' => [
                'code' => 'jne',
                'name' => 'JNE Express',
                'services' => [
                    ['code' => 'REG', 'name' => 'JNE Reguler', 'etd' => '2 - 3 Hari', 'type' => 'regular'],
                    ['code' => 'YES', 'name' => 'JNE YES (Yakin Esok Sampai)', 'etd' => '1 Hari', 'type' => 'express'],
                    ['code' => 'OKE', 'name' => 'JNE OKE (Hemat)', 'etd' => '3 - 5 Hari', 'type' => 'economy']
                ]
            ],
            'sicepat' => [
                'code' => 'sicepat',
                'name' => 'SiCepat Ekspres',
                'services' => [
                    ['code' => 'SIUNT', 'name' => 'SiCepat Reguler', 'etd' => '1 - 3 Hari', 'type' => 'regular'],
                    ['code' => 'GOKIL', 'name' => 'SiCepat Kargo (Hemat)', 'etd' => '3 - 6 Hari', 'type' => 'economy']
                ]
            ]
        ];
    }

    /**
     * Calculate shipping rate dynamically based on destination and subtotal
     */
    public static function calculate_rate(
        mysqli $conn,
        string $province,
        string $city,
        string $courierCode = 'jnt',
        string $serviceCode = 'EZ',
        float $subtotal = 0.0,
        int $weightGrams = 350
    ): array {
        // Base rate lookup by province zone (origin: Jabodetabek)
        $zoneRates = [
            'DKI Jakarta' => 9000,
            'Banten' => 11000,
            'Jawa Barat' => 12000,
            'Jawa Tengah' => 18000,
            'DI Yogyakarta' => 18000,
            'Jawa Timur' => 20000,
            'Lampung' => 22000,
            'Sumatera Selatan' => 25000,
            'Sumatera Barat' => 29000,
            'Sumatera Utara' => 32000,
            'Riau' => 30000,
            'Kepulauan Riau' => 32000,
            'Bali' => 24000,
            'Kalimantan Barat' => 35000,
            'Kalimantan Timur' => 38000,
            'Kalimantan Selatan' => 36000,
            'Sulawesi Selatan' => 38000,
            'Sulawesi Utara' => 45000,
            'Nusa Tenggara Barat' => 32000,
            'Nusa Tenggara Timur' => 45000,
            'Papua' => 65000
        ];

        $baseRate = $zoneRates[$province] ?? 20000;

        // Weight multiplier (rounded up per kg, minimum 1kg)
        $kg = max(1, ceil($weightGrams / 1000));
        
        // Service multiplier
        $serviceMultiplier = 1.0;
        if ($serviceCode === 'YES' || $serviceCode === 'SUPER') {
            $serviceMultiplier = 1.6;
        } elseif ($serviceCode === 'OKE' || $serviceCode === 'GOKIL') {
            $serviceMultiplier = 0.8;
        }

        $calculatedCost = round($baseRate * $kg * $serviceMultiplier);

        // Check Free Shipping settings
        $settings = get_all_settings($conn);
        $minFreeOrder = isset($settings['shipping_free_min_order']) ? (float)$settings['shipping_free_min_order'] : 100000.0;
        
        $isFreeShipping = false;
        $finalCost = $calculatedCost;
        $discountOngkir = 0;

        if ($subtotal >= $minFreeOrder && $serviceCode !== 'YES' && $serviceCode !== 'SUPER') {
            $isFreeShipping = true;
            $discountOngkir = $calculatedCost;
            $finalCost = 0;
        }

        // Get Courier & Service details
        $couriers = self::get_available_couriers();
        $courierName = $couriers[$courierCode]['name'] ?? 'J&T Express';
        $serviceName = 'Reguler';
        $etd = '1 - 3 Hari';

        if (isset($couriers[$courierCode]['services'])) {
            foreach ($couriers[$courierCode]['services'] as $srv) {
                if ($srv['code'] === $serviceCode) {
                    $serviceName = $srv['name'];
                    $etd = $srv['etd'];
                    break;
                }
            }
        }

        return [
            'courier_code' => $courierCode,
            'courier_name' => $courierName,
            'service_code' => $serviceCode,
            'service_name' => $serviceName,
            'etd' => $etd,
            'raw_cost' => $calculatedCost,
            'discount_ongkir' => $discountOngkir,
            'final_cost' => $finalCost,
            'is_free_shipping' => $isFreeShipping,
            'formatted_raw_cost' => 'Rp ' . number_format($calculatedCost, 0, ',', '.'),
            'formatted_final_cost' => $finalCost === 0 ? 'Rp 0 (Gratis Ongkir)' : 'Rp ' . number_format($finalCost, 0, ',', '.')
        ];
    }
}
