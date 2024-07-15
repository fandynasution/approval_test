<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Mail\SendPoSMail;
use App\Mail\FeedbackMail;
use App\Mail\StaffActionMail;
use App\Mail\StaffActionPoRMail;
use App\Mail\StaffActionPoSMail;
use Carbon\Carbon;
use PDO;
use DateTime;


class AutoSendController extends Controller
{
    public function index()
    {
        ini_set('memory_limit', '8192M');

        $query = DB::connection('BTID')
        ->table('mgr.cb_cash_request_appr')
        ->whereNull('mgr.cb_cash_request_appr.sent_mail_date')
        ->where('mgr.cb_cash_request_appr.status', '=', 'P')
        ->whereNotNull('mgr.cb_cash_request_appr.currency_cd')
        ->where('mgr.cb_cash_request_appr.entity_cd', '!=', 'DKY')
        ->where('mgr.cb_cash_request_appr.audit_date', '>=', DB::raw("CONVERT(datetime, '2024-03-28', 120)"))
        ->where('mgr.cb_cash_request_appr.level_no', '=', function ($query) {
            $query->select(DB::raw('MIN(a.level_no)'))
                ->from('mgr.cb_cash_request_appr as a')
                ->whereColumn('a.entity_cd', '=', 'mgr.cb_cash_request_appr.entity_cd')
                ->whereColumn('a.approve_seq', '=', 'mgr.cb_cash_request_appr.approve_seq')
                ->whereColumn('a.doc_no', '=', 'mgr.cb_cash_request_appr.doc_no')
                ->whereColumn('a.module', '=', 'mgr.cb_cash_request_appr.module')
                ->whereColumn('a.request_type', '=', 'mgr.cb_cash_request_appr.request_type')
                ->whereNull('a.sent_mail_date');
        })
        ->orderByDesc('mgr.cb_cash_request_appr.doc_no')
        ->get();

        foreach ($query as $data) {
            $entity_cd = $data->entity_cd;
            $exploded_values = explode(" ", $entity_cd);
            $project_no = implode('', $exploded_values) . '01';
            $doc_no = $data->doc_no;
            $trx_type = $data->trx_type;
            $level_no = $data->level_no;
            $user_id = $data->user_id;
            $type = $data->TYPE;
            $module = $data->module;
            $ref_no = $data->ref_no;
            $doc_date = $data->doc_date;
            $dateTime = new DateTime($doc_date);
            $supervisor = 'Y';
            $reason = '0';

            if ($type == 'U' && $module == "CB") {
                $exec = 'mgr.x_send_mail_approval_cb_ppu';
            } else if ($type == 'V' && $module == "CB"){
                $exec = 'mgr.x_send_mail_approval_cb_ppu_vvip';
            } else if ($type == 'D' && $module == "CB") {
                $exec = 'mgr.x_send_mail_approval_cb_rpb';
            } else if ($type == 'G' && $module == "CB") {
                $exec = 'mgr.x_send_mail_approval_cb_rum';
            } else if ($type == 'E' && $module == "CB") {
                $exec = 'mgr.x_send_mail_approval_cb_fupd';
            } else if ($type == 'B' && $module == "PL") {
                $exec = 'mgr.xrl_send_mail_approval_pl_budget_lyman';
            } else if ($type == 'R' && $module == "PL") {
                $exec = 'mgr.xrl_send_mail_approval_pl_budget_revision';
            } else if ($type == 'A' && $module == "PO") {
                $exec = 'mgr.x_send_mail_approval_po_order';
            } else if ($type == 'E' && $module == "CM") {
                $exec = 'mgr.xrl_send_mail_approval_cm_contract_entry';
            } else if ($type == 'C' && $module == "CM") {
                $exec = 'mgr.xrl_send_mail_approval_cm_contractclose';
            } else if ($type == 'B' && $module == "CM") {
                $exec = 'mgr.xrl_send_mail_approval_cm_contractdone';
            } else if ($type == 'A' && $module == "CM") {
                $exec = 'mgr.xrl_send_mail_approval_cm_progress';
            } else if ($type == 'D' && $module == "CM") {
                $exec = 'mgr.xrl_send_mail_approval_cm_varianorder';
            }
            $whereUg = array(
                'user_name' => $user_id
            );

            $queryUg = DB::connection('BTID')
            ->table('mgr.security_groupings')
            ->where($whereUg)
            ->get();

            $user_group = $queryUg[0]->group_name;

            if ($level_no == 1) {
                $statussend = 'P';
                $downLevel = '0';
                if ($type == 'S' && $module == "PO") {
                    $date = date('d-m-Y', strtotime($doc_date));
                    $pdo = DB::connection('BTID')->getPdo();
                    $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_selection ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                    $sth->bindParam(1, $entity_cd);
                    $sth->bindParam(2, $project_no);
                    $sth->bindParam(3, $doc_no);
                    $sth->bindParam(4, $ref_no);
                    $sth->bindParam(5, $date);
                    $sth->bindParam(6, $statussend);
                    $sth->bindParam(7, $downLevel);
                    $sth->bindParam(8, $user_group);
                    $sth->bindParam(9, $user_id);
                    $sth->bindParam(10, $supervisor);
                    $sth->bindParam(11, $reason);
                    $sth->execute();
                } else if ($type == 'Q' && $module == "PO") {
                    $pdo = DB::connection('BTID')->getPdo();
                    $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_request ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                    $sth->bindParam(1, $entity_cd);
                    $sth->bindParam(2, $project_no);
                    $sth->bindParam(3, $doc_no);
                    $sth->bindParam(4, $statussend);
                    $sth->bindParam(5, $downLevel);
                    $sth->bindParam(6, $user_group);
                    $sth->bindParam(7, $user_id);
                    $sth->bindParam(8, $supervisor);
                    $sth->bindParam(9, $reason);
                    $sth->execute();
                } else {
                    if ($module == 'CM') {
                        $pdo = DB::connection('BTID')->getPdo();
                        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC ".$exec." ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                        $sth->bindParam(1, $entity_cd);
                        $sth->bindParam(2, $project_no);
                        $sth->bindParam(3, $doc_no);
                        $sth->bindParam(4, $ref_no);
                        $sth->bindParam(5, $statussend);
                        $sth->bindParam(6, $downLevel);
                        $sth->bindParam(7, $user_group);
                        $sth->bindParam(8, $user_id);
                        $sth->bindParam(9, $supervisor);
                        $sth->bindParam(10, $reason);
                        $sth->execute();
                    } else {
                        $pdo = DB::connection('BTID')->getPdo();
                        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC ".$exec." ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                        $sth->bindParam(1, $entity_cd);
                        $sth->bindParam(2, $project_no);
                        $sth->bindParam(3, $doc_no);
                        $sth->bindParam(4, $trx_type);
                        $sth->bindParam(5, $statussend);
                        $sth->bindParam(6, $downLevel);
                        $sth->bindParam(7, $user_group);
                        $sth->bindParam(8, $user_id);
                        $sth->bindParam(9, $supervisor);
                        $sth->bindParam(10, $reason);
                        $sth->execute();
                    }
                }
            } else if ($level_no > 1){
                $downLevel  = $level_no - 1;
                $statussend = 'A';
                $wherebefore = array(
                    'doc_no' => $doc_no,
                    'entity_cd' => $entity_cd,
                    'level_no'  => $downLevel
                );
    
                $querybefore = DB::connection('BTID')
                ->table('mgr.cb_cash_request_appr')
                ->where($wherebefore)
                ->get();
    
                $level_data = $querybefore[0]->status;

                if ($level_data == 'A'){
                    if ($type == 'S' && $module == "PO") {
                        $date = date('d-m-Y', strtotime($doc_date));
                        $pdo = DB::connection('BTID')->getPdo();
                        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_selection ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                        $sth->bindParam(1, $entity_cd);
                        $sth->bindParam(2, $project_no);
                        $sth->bindParam(3, $doc_no);
                        $sth->bindParam(4, $ref_no);
                        $sth->bindParam(5, $date);
                        $sth->bindParam(6, $statussend);
                        $sth->bindParam(7, $downLevel);
                        $sth->bindParam(8, $user_group);
                        $sth->bindParam(9, $user_id);
                        $sth->bindParam(10, $supervisor);
                        $sth->bindParam(11, $reason);
                        $sth->execute();
                    } else if ($type == 'Q' && $module == "PO") {
                        $pdo = DB::connection('BTID')->getPdo();
                        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_request ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                        $sth->bindParam(1, $entity_cd);
                        $sth->bindParam(2, $project_no);
                        $sth->bindParam(3, $doc_no);
                        $sth->bindParam(4, $statussend);
                        $sth->bindParam(5, $downLevel);
                        $sth->bindParam(6, $user_group);
                        $sth->bindParam(7, $user_id);
                        $sth->bindParam(8, $supervisor);
                        $sth->bindParam(9, $reason);
                        $sth->execute();
                    } else {
                        if ($module == 'CM') {
                            $pdo = DB::connection('BTID')->getPdo();
                            $sth = $pdo->prepare("SET NOCOUNT ON; EXEC ".$exec." ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                            $sth->bindParam(1, $entity_cd);
                            $sth->bindParam(2, $project_no);
                            $sth->bindParam(3, $doc_no);
                            $sth->bindParam(4, $ref_no);
                            $sth->bindParam(5, $statussend);
                            $sth->bindParam(6, $downLevel);
                            $sth->bindParam(7, $user_group);
                            $sth->bindParam(8, $user_id);
                            $sth->bindParam(9, $supervisor);
                            $sth->bindParam(10, $reason);
                            $sth->execute();
                        } else {
                            $pdo = DB::connection('BTID')->getPdo();
                            $sth = $pdo->prepare("SET NOCOUNT ON; EXEC ".$exec." ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
                            $sth->bindParam(1, $entity_cd);
                            $sth->bindParam(2, $project_no);
                            $sth->bindParam(3, $doc_no);
                            $sth->bindParam(4, $trx_type);
                            $sth->bindParam(5, $statussend);
                            $sth->bindParam(6, $downLevel);
                            $sth->bindParam(7, $user_group);
                            $sth->bindParam(8, $user_id);
                            $sth->bindParam(9, $supervisor);
                            $sth->bindParam(10, $reason);
                            $sth->execute();
                        }
                    }
                }
            }
        }
    }
}
