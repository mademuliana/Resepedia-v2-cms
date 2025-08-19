<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomersAndAddressesSeeder extends Seeder
{
    public function run(): void
    {
        $companies = DB::table('companies')->get();
        $clamp = fn($n,$min,$max)=>max($min,min($max,$n));

        foreach ($companies as $company) {
            // Up to 5 candidates per company; we’ll slice with ±2
            $candidates = match ($company->name) {
                'Resepedia Jakarta' => [
                    ['name'=>'Andi Setiawan','email'=>'andi@customer.test','phone'=>'628111111111',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Melati No. 12','city'=>'Jakarta','state'=>'DKI Jakarta','postal'=>'10110','lat'=>-6.2,'lng'=>106.8167,'default'=>true]]],
                    ['name'=>'Budi Santoso','email'=>'budi@customer.test','phone'=>'628122222222',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Kenanga No. 3','city'=>'Jakarta','state'=>'DKI Jakarta','postal'=>'10150','lat'=>-6.19,'lng'=>106.83,'default'=>true]]],
                    ['name'=>'Siti Aisyah','email'=>'siti@customer.test','phone'=>'628133333333',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Pahlawan No. 7','city'=>'Jakarta','state'=>'DKI Jakarta','postal'=>'10160','lat'=>-6.21,'lng'=>106.81,'default'=>true]]],
                    ['name'=>'Rahmat Hidayat','email'=>'rahmat@customer.test','phone'=>'628177777777',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Merpati No. 9','city'=>'Jakarta','state'=>'DKI Jakarta','postal'=>'10170','lat'=>-6.205,'lng'=>106.82,'default'=>true]]],
                    ['name'=>'Clara Wijaya','email'=>'clara@customer.test','phone'=>'628188888888',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Anggrek No. 4','city'=>'Jakarta','state'=>'DKI Jakarta','postal'=>'10180','lat'=>-6.207,'lng'=>106.84,'default'=>true]]],
                ],
                default => [
                    ['name'=>'Rudi Hartono','email'=>'rudi@customer.test','phone'=>'628144444444',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Asia Afrika No. 1','city'=>'Bandung','state'=>'Jawa Barat','postal'=>'40111','lat'=>-6.9147,'lng'=>107.6098,'default'=>true]]],
                    ['name'=>'Dewi Lestari','email'=>'dewi@customer.test','phone'=>'628155555555',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Braga No. 8','city'=>'Bandung','state'=>'Jawa Barat','postal'=>'40121','lat'=>-6.916,'lng'=>107.61,'default'=>true]]],
                    ['name'=>'Fajar Pratama','email'=>'fajar@customer.test','phone'=>'628166666666',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Dago No. 22','city'=>'Bandung','state'=>'Jawa Barat','postal'=>'40135','lat'=>-6.89,'lng'=>107.61,'default'=>true]]],
                    ['name'=>'Maya Sari','email'=>'maya@customer.test','phone'=>'628199999999',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Ciumbuleuit No. 5','city'=>'Bandung','state'=>'Jawa Barat','postal'=>'40142','lat'=>-6.87,'lng'=>107.60,'default'=>true]]],
                    ['name'=>'Yoga Prasetyo','email'=>'yoga@customer.test','phone'=>'628133000000',
                        'addresses'=>[['label'=>'Home','line1'=>'Jl. Setiabudi No. 11','city'=>'Bandung','state'=>'Jawa Barat','postal'=>'40141','lat'=>-6.86,'lng'=>107.60,'default'=>true]]],
                ],
            };

            $offset = (crc32((string) $company->id) % 5) - 2; // -2..+2
            $target = $clamp(3 + $offset, 2, count($candidates));

            foreach (array_slice($candidates, 0, $target) as $c) {
                $cust = Customer::updateOrCreate(
                    ['name'=>$c['name'],'company_id'=>$company->id],
                    ['email'=>$c['email'],'phone'=>$c['phone'],'notes'=>null]
                );
                foreach ($c['addresses'] as $a) {
                    Address::updateOrCreate(
                        ['customer_id'=>$cust->id,'company_id'=>$company->id,'label'=>$a['label'],'line1'=>$a['line1'],'city'=>$a['city']],
                        ['line2'=>$a['line2']??null,'state'=>$a['state']??null,'postal_code'=>$a['postal']??null,'country'=>'ID','latitude'=>$a['lat']??null,'longitude'=>$a['lng']??null,'is_default'=>(bool)($a['default']??false)]
                    );
                }
            }
        }
    }
}
