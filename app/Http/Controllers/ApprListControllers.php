<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ApprListControllers extends Controller
{
    public function index()
    {
        return view('apprlist.index'); // Pastikan file view ini ada di resources/views/apprlist/index.blade.php
    }

    public function getData()
    {
        $query = DB::connection('BTID')
            ->table('mgr.cb_cash_request_appr')
            ->where('status', 'P')
            ->whereNotNull('currency_cd')
            ->whereNotNull('sent_mail_date')
            ->whereRaw("LTRIM(RTRIM(entity_cd)) NOT LIKE '%[^0-9]%'")
            ->where('audit_date', '>=', DB::raw("CONVERT(datetime, '2024-03-28', 120)"));

        return DataTables::of($query)->make(true);
    }

    public function sendData(Request $request)
    {
        $entity_cd = $request->input('entity_cd');
        $doc_no = $request->input('doc_no');
        $user_id = $request->input('user_id');

        // Debugging: Gunakan Log Laravel, jangan var_dump()
        \Log::info("Received Data: ", compact('entity_cd', 'doc_no', 'user_id'));

        // Jangan gunakan var_dump(), langsung return response JSON
        return response()->json(['message' => 'Data berhasil diproses'], 200);
    }
}
