<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

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

        $query = DB::connection('BTID')
            ->table('mgr.cb_cash_request_appr')
            ->where('entity_cd', $entity_cd)
            ->where('doc_no', $doc_no)
            ->where('user_id', $user_id)
            ->get(); // Gunakan get() untuk mengambil hasil query

        $query_project = DB::connection('BTID')
            ->table('mgr.pl_project')
            ->where('entity_cd', $entity_cd)
            ->get(); // Gunakan get() untuk mengambil hasil query

        $queryGroup = DB::connection('BTID')
            ->table('mgr.security_groupings')
            ->where('user_name', $user_id)
            ->get(); // Gunakan get() untuk mengambil hasil query

        $queryUser = DB::connection('BTID')
            ->table('mgr.security_users')
            ->where('name', $user_id)
            ->get(); // Gunakan get() untuk mengambil hasil query

        $project_no = optional($query_project->first())->project_no;
        $trx_type = optional($query->first())->trx_type;
        $type = optional($query->first())->TYPE;
        $module = optional($query->first())->module;
        $status = optional($query->first())->status;
        $level_no = optional($query->first())->level_no;
        $user_group = optional($queryGroup->first())->group_name;
        $spv = optional($queryUser->first())->supervisor;
        $ref_no  = optional($query->first())->ref_no; // request_no untuk PO SELECTION
        $trx_date  = optional($query->first())->doc_date;
        $trx_date = $trx_date ? Carbon::parse($trx_date)->format('d-m-Y') : null;
        $reason = '0';

        if ($module == 'PO') {
            if ($type == 'Q') {
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_request ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $status);
                $sth->bindParam(5, $level_no);
                $sth->bindParam(6, $user_group);
                $sth->bindParam(7, $user_id);
                $sth->bindParam(8, $spv);
                $sth->bindParam(9, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'S'){
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_selection ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $ref_no);
                $sth->bindParam(5, $trx_date);
                $sth->bindParam(6, $status);
                $sth->bindParam(7, $level_no);
                $sth->bindParam(8, $user_group);
                $sth->bindParam(9, $user_id);
                $sth->bindParam(10, $spv);
                $sth->bindParam(11, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else {
                // return response()->json(['message' => 'EXEC mgr.x_send_mail_approval_po_order'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_order ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $trx_type);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            }
        } else if ($module == 'CB') {
            if ($type == 'D') {
                // return response()->json(['message' => 'EXEC mgr.x_send_mail_approval_cb_rpb'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_cb_rpb ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $trx_type);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'E') {
                // return response()->json(['message' => 'EXEC mgr.x_send_mail_approval_cb_fupd'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_cb_fupd ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $trx_type);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'G') {
                // return response()->json(['message' => 'EXEC mgr.x_send_mail_approval_cb_rum'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_cb_rum ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $trx_type);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'U') {
                // return response()->json(['message' => 'EXEC mgr.x_send_mail_approval_cb_ppu'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_cb_ppu ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $trx_type);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'V') {
                // return response()->json(['message' => 'EXEC mgr.x_send_mail_approval_cb_ppu_vvip'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_cb_ppu_vvip ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $trx_type);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            }
        } else if ($module == 'CM') {
            if ($type == 'A') {
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_cm_progress'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_cm_progress ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $ref_no);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'B') {
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_cm_contractdone'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_cm_contractdone ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $ref_no);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'C') {
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_cm_contractclose'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_cm_contractclose ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $ref_no);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'D') {
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_cm_varianorder'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_cm_varianorder ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $ref_no);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'E') {
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_cm_contract_entry'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_cm_contract_entry ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $ref_no);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_group);
                $sth->bindParam(8, $user_id);
                $sth->bindParam(9, $spv);
                $sth->bindParam(10, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            }
        } else if ($module == 'PL') {
            if ($type == 'Y' && $trx_type == 'RB') {
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_pl_budget_revision'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_pl_budget_revision ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $trx_type);
                $sth->bindParam(5, $status);
                $sth->bindParam(6, $level_no);
                $sth->bindParam(7, $user_id);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } else if ($type == 'Y') {
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_pl_budget_lyman'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_pl_budget_lyman ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $status);
                $sth->bindParam(5, $level_no);
                $sth->bindParam(6, $user_id);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            } 
        } else if ($module == 'TM') {
            if ($type == 'R') {
                $queryUser = DB::connection('BTID')
                ->table('mgr.pm_tenancy_renew')
                ->where('entity_cd', $entity_cd)
                ->where('project_no', $project_no)
                ->where('tenant_no', $ref_no)
                ->get(); // Gunakan get() untuk mengambil hasil query

                $renew_no = optional($query->first())->renew_no;
                // return response()->json(['message' => 'EXEC mgr.xrl_send_mail_approval_tm_contractrenew'], 200);
                $pdo = DB::connection('BTID')->getPdo();
                $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_tm_contractrenew ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                $sth->bindParam(1, $entity_cd);
                $sth->bindParam(2, $project_no);
                $sth->bindParam(3, $doc_no);
                $sth->bindParam(4, $ref_no);
                $sth->bindParam(5, $renew_no);
                $sth->bindParam(6, $status);
                $sth->bindParam(7, $level_no);
                $sth->bindParam(8, $user_group);
                $sth->bindParam(9, $user_id);
                $sth->bindParam(10, $spv);
                $sth->bindParam(11, $reason);
                $sth->execute();
                if ($sth == true) {
                    return response()->json(['message' => 'SUCCESS'], 200);
                } else {
                    return response()->json(['message' => 'FAILED'], 400);
                }
            }
        }
    }
}
