<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CouriersSeeder extends Seeder
{
    public function run(): void
    {
        $companies = DB::table('companies')->pluck('id','name');
        $clamp = fn($n,$min,$max)=>max($min,min($max,$n));

        $candidates = [
            ['name'=>'GoSend','type'=>'third_party','phone'=>'628111000000','notes'=>'Same-day','active'=>true],
            ['name'=>'JNE','type'=>'third_party','phone'=>'628222000000','notes'=>'Next-day','active'=>true],
            ['name'=>'GrabExpress','type'=>'third_party','phone'=>'628133322222','notes'=>'Express','active'=>true],
            ['name'=>'Internal Fleet','type'=>'internal','phone'=>'','notes'=>'In-house courier','active'=>true],
        ];

        foreach ($companies as $companyName => $cid) {
            $offset = (crc32((string) $cid) % 5) - 2; // -2..+2
            $target = $clamp(2 + $offset, 1, count($candidates));

            foreach (array_slice($candidates, 0, $target) as $c) {
                Courier::updateOrCreate(
                    ['name'=>$c['name'],'company_id'=>$cid],
                    ['type'=>$c['type'],'phone'=>$c['phone'],'notes'=>$c['notes'],'active'=>$c['active']]
                );
            }
        }
    }
}
