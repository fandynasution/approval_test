<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetEncryptController extends Controller
{
    public function index(Request $request) 
    {

        $data2Encrypt = [
            "entity_cd"     => "03",
            "project_no"    => "0301",
            "doc_no"        => "PO25110025",
            "trx_type"      => "O1",
            "level_no"      => "1",
            "usergroup"     => "09B",
            "user_id"       => "YOGASWARA",
            "supervisor"    => "M",
            "type"          => "A",
            "type_module"        => "PO",
            "text"          => "Purchase Order"
        ];
        $encryptedData = Crypt::encrypt($data2Encrypt);

        dd($encryptedData);
        
    }
}
