<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Mail\SendIcTransferMail;
use App\Mail\StaffActionIcTransferMail;
use PDO;
use DateTime;
use Carbon\Carbon;

class IcTransferController extends Controller
{
    public function Mail(Request $request)
    {
        $callback = [
            'data'  => null,
            'Error' => false,
            'Pesan' => '',
            'Status'=> 200
        ];

        try {

            $list_of_approve = explode('; ',  $request->approve_exist);
            $approve_data = [];
            foreach ($list_of_approve as $approve) {
                $approve_data[] = $approve;
            }

            $list_of_urls = explode(',', $request->url_file);
            $list_of_files = explode(',', $request->file_name);

            $url_data = [];
            $file_data = [];

            foreach ($list_of_urls as $url) {
                // $url_data[] = $url;
                $separator = strpos($url, '?') === false ? '?' : '&';
                $url_data[] = $url . $separator . 'v=' . uniqid();
            }

            foreach ($list_of_files as $file) {
                $file_data[] = $file;
            }

            $dataArray = array(
                'entity_cd'         => $request->entity_cd,
                'project_no'        => $request->project_no,
                'doc_no'            => $request->doc_no,
                'trx_type'          => $request->trx_type,
                'approve_seq'       => $request->approve_seq,
                'level_no'          => $request->level_no,
                'usergroup'         => $request->usergroup,
                'user_id'           => $request->user_id,
                'sender'            => $request->sender,
                'sender_addr'       => $request->sender_addr,
                'url_file'          => $url_data,
                'file_name'         => $file_data,
                'entity_name'       => $request->entity_name,
                'email_addr'        => $request->email_addr,
                'user_name'         => $request->user_name,
                'descs'             => $request->descs,
                'approve_list'      => $approve_data,
                'clarify_user'      => $request->clarify_user,
                'clarify_email'     => $request->clarify_email,
                'reason'            => $request->reason,
                'currency_cd'       => $request->currency_cd,
                'supervisor'        => $request->supervisor,
                'subject'          => "Need Approval for IC Transfer No.  ".$request->doc_no,
            );

            $data2Encrypt = array(
                'entity_cd'     => $request->entity_cd,
                'project_no'    => $request->project_no,
                'doc_no'        => $request->doc_no,
                'trx_type'      => $request->trx_type,
                'approve_seq'   => $request->approve_seq,
                'level_no'      => $request->level_no,
                'usergroup'     => $request->usergroup,
                'user_id'       => $request->user_id,
                'supervisor'    => $request->supervisor,
                'email_address' => $request->email_addr,
                'entity_name'   => $request->entity_name,
                'type'          => 'T',
                'type_module'   => 'IC',
                'text'          => 'IC Transfer'
            );

            $encryptedData = Crypt::encrypt($data2Encrypt);

            // isi callback data secara konsisten
            $callback['data'] = [
                'encrypted' => $encryptedData
            ];

            $emailAddresses = strtolower($request->email_addr);
            $approve_seq = $request->approve_seq;
            $entity_cd = $request->entity_cd;
            $doc_no = $request->doc_no;
            $level_no = $request->level_no;
            $app_url = 'IcTransfer';
            $type = 'T';
            $module = 'IC';
        
            // Check if email addresses are provided and not empty
            if (!empty($emailAddresses)) {
                $email = $emailAddresses; // Since $emailAddresses is always a single email address (string)
                
                // Check if the email has been sent before for this document
                $cacheFile = 'email_sent_' . $approve_seq . '_' . $entity_cd . '_' . $doc_no . '_' . $level_no . '.txt';
                $cacheFilePath = storage_path('app/mail_cache/send_ic_transfer/' . date('Ymd') . '/' . $cacheFile);
                $cacheDirectory = dirname($cacheFilePath);
        
                // Ensure the directory exists
                if (!file_exists($cacheDirectory)) {
                    mkdir($cacheDirectory, 0755, true);
                }

                // Acquire an exclusive lock
                $lockFile = $cacheFilePath . '.lock';
                $lockHandle = fopen($lockFile, 'w');
                if (!flock($lockHandle, LOCK_EX)) {
                    // Failed to acquire lock, handle appropriately
                    fclose($lockHandle);
                    throw new Exception('Failed to acquire lock');
                }
        
                if (!file_exists($cacheFilePath)) {
                    // Send email
                    Mail::to($email)->send(new SendIcTransferMail($encryptedData, $dataArray));

                    // Tandai file cache
                    file_put_contents($cacheFilePath, 'sent');

                    // Log keberhasilan kirim email
                    Log::channel('sendmailapproval')->info(
                        'Email IC Transfer doc_no '.$doc_no.' Entity ' . $entity_cd.' berhasil dikirim ke: ' . $email
                    );

                    $callback['Pesan'] = "Email berhasil dikirim ke: $email";
                    $callback['Error'] = false;
                    $callback['Status']= 200;

                } else {
                    // Email was already sent
                    Log::channel('sendmailapproval')->info('Email IC Transfer doc_no '.$doc_no.' Entity ' . $entity_cd.' already sent to: ' . $email);
                    $callback['Pesan'] = "Email sudah pernah dikirim ke: $email";
                    $callback['Error'] = false;
                    $callback['Status']= 201;
                }
            } else {
                // No email address provided
                Log::channel('sendmail')->warning("No email address provided for document " . $doc_no);
                $callback['Pesan'] = "No email address provided";
                $callback['Error'] = true;
                $callback['Status']= 400;
            }
        } catch (\Exception $e) {
            Log::channel('sendmail')->error("Gagal mengirim email: " . $e->getMessage());

            $callback['Pesan'] = "Gagal mengirim email: " . $e->getMessage();
            $callback['Error'] = true;
            $callback['Status']= 500;
        }

        return response()->json($callback, $callback['Status']);
    }

    public function processData($status = '', $encrypt = '')
    {
        Artisan::call('config:cache');
        Artisan::call('cache:clear');
        Cache::flush();

        $cacheKey = 'processData_' . $encrypt;
        Cache::forget($cacheKey);

        $data = Crypt::decrypt($encrypt);

        Log::info('Decrypted data: ' . json_encode($data));

        /*
        * Check whether the approval request has already been processed
        */
        $where = array(
            'doc_no'      => $data["doc_no"],
            'entity_cd'   => $data["entity_cd"],
            'level_no'    => $data["level_no"],
            'type'        => $data["type"],
            'module'      => $data["type_module"],
            'approve_seq' => $data["approve_seq"],
        );

        $query = DB::connection('BTID')
            ->table('mgr.cb_cash_request_appr')
            ->where($where)
            ->whereIn('status', array('A', 'R', 'C'))
            ->exists();

        Log::info('First query result: ' . json_encode($query));

        if ($query) {
            $msg = 'You Have Already Made a Request to '
                . $data["text"]
                . ' No. '
                . $data["doc_no"];

            $msg1 = array(
                "Pesan" => $msg,
                "St"    => "OK",
                "notif" => "Restricted !",
                "image" => "double_approve.png"
            );

            return view("email.after", $msg1);
        }

        /*
        * Check whether the approval request is still pending
        */
        $where2 = array(
            'doc_no'      => $data["doc_no"],
            'status'      => 'P',
            'entity_cd'   => $data["entity_cd"],
            'level_no'    => $data["level_no"],
            'type'        => $data["type"],
            'module'      => $data["type_module"],
            'approve_seq' => $data["approve_seq"],
        );

        $query2 = DB::connection('BTID')
            ->table('mgr.cb_cash_request_appr')
            ->where($where2)
            ->exists();

        Log::info('Second query result: ' . json_encode($query2));

        if (!$query2) {
            $msg = 'There is no '
                . $data["text"]
                . ' with No. '
                . $data["doc_no"];

            $msg1 = array(
                "Pesan" => $msg,
                "St"    => "OK",
                "notif" => "Restricted !",
                "image" => "double_approve.png"
            );

            return view("email.after", $msg1);
        }

        /*
        * Get doc_date from approval request
        *
        * Same condition as $where2, but without status
        */
        $whereDocDate = array(
            'doc_no'      => $data["doc_no"],
            'entity_cd'   => $data["entity_cd"],
            'level_no'    => $data["level_no"],
            'type'        => $data["type"],
            'module'      => $data["type_module"],
            'approve_seq' => $data["approve_seq"],
        );

        $docDate = DB::connection('BTID')
            ->table('mgr.cb_cash_request_appr')
            ->where($whereDocDate)
            ->value('doc_date');

        Log::info('Document date: ' . $docDate);
        if ($status == 'A') {
            /*
            * Check Account Period
            */
            if (!empty($docDate)) {

                $fyear   = date('Y', strtotime($docDate));
                $aperiod = (int) date('m', strtotime($docDate));

                $glclosed = DB::connection('BTID')
                    ->table('mgr.cf_acctperiod')
                    ->where('entity_cd', $data["entity_cd"])
                    ->where('fyear', $fyear)
                    ->where('aperiod', $aperiod)
                    ->value('glclosed');

                Log::info('Account Period Check: ' . json_encode(array(
                    'entity_cd' => $data["entity_cd"],
                    'fyear'     => $fyear,
                    'aperiod'   => $aperiod,
                    'glclosed'    => $glclosed
                )));

                if ($glclosed == 'Y') {

                    $msg = 'Account Period already closed. Please Revise or Cancel this request.';

                    $msg1 = array(
                        "Pesan" => $msg,
                        "St"    => "OK",
                        "notif" => "Restricted !",
                        "image" => "double_approve.png"
                    );

                    return view("email.after", $msg1);
                }
            }

            /*
            * Check GRN Lock Day
            */
            $lockDay = DB::connection('BTID')
                ->table('mgr.po_spec')
                ->value('grn_lock_day');

            Log::info('GRN Lock Day: ' . $lockDay);

            if (!empty($docDate) && $lockDay !== null) {

                $docDateOnly = date('Y-m-d', strtotime($docDate));
                $today       = date('Y-m-d');

                $docDateTime = new DateTime($docDateOnly);
                $todayTime   = new DateTime($today);

                $interval = $docDateTime->diff($todayTime);
                $diffDays = (int) $interval->days;

                Log::info('GRN Lock Day Check: ' . json_encode(array(
                    'doc_date' => $docDateOnly,
                    'today'    => $today,
                    'diff_days' => $diffDays,
                    'lock_day' => $lockDay
                )));

                /*
                * Example:
                * doc_date   = 2026-08-01
                * lock_day   = 3
                *
                * 2026-08-04 = 3 days -> Still allowed
                * 2026-08-05 = 4 days -> Blocked
                */
                if ($diffDays > (int) $lockDay) {

                    $msg = 'Transaction Date Is Affected By Lock Day ('
                        . $lockDay
                        . ' Days). Please Revise or Cancel this request.';

                    $msg1 = array(
                        "Pesan" => $msg,
                        "St"    => "OK",
                        "notif" => "Restricted !",
                        "image" => "double_approve.png"
                    );

                    return view("email.after", $msg1);
                }
            }
        }
        /*
        * Set approval action information
        */
        $name    = '';
        $bgcolor = '';
        $valuebt = '';

        if ($status == 'A') {
            $name    = 'Approval';
            $bgcolor = '#40de1d';
            $valuebt = 'Approve';
        } elseif ($status == 'R') {
            $name    = 'Revision';
            $bgcolor = '#f4bd0e';
            $valuebt = 'Revise';
        } else {
            $name    = 'Cancellation';
            $bgcolor = '#e85347';
            $valuebt = 'Cancel';
        }

        /*
        * Prepare data for approval page
        */
        $data = array(
            "status"      => $status,
            "doc_no"      => $data["doc_no"],
            "email"       => $data["email_address"],
            "entity_name" => $data["entity_name"],
            "encrypt"     => $encrypt,
            "name"        => $name,
            "bgcolor"     => $bgcolor,
            "valuebt"     => $valuebt
        );

        return view('email/ictransfer/passcheckwithremark', $data);
    }

    public function getaccess(Request $request)
    {
        $data = Crypt::decrypt($request->encrypt);

        $status = $request->status;

        $reasonget = $request->reason;

        $descstatus = " ";
        $imagestatus = " ";

        $msg = " ";
        $msg1 = " ";
        $notif = " ";
        $st = " ";
        $image = " ";

        if ($reasonget == '' || $reasonget == NULL) {
            $reason = 'no reason';
        } else {
            $reason = $reasonget;
        }

        if ($status == "A") {
            $descstatus = "Approved";
            $imagestatus = "approved.png";
        } else if ($status == "R") {
            $descstatus = "Revised";
            $imagestatus = "revise.png";
        } else {
            $descstatus = "Cancelled";
            $imagestatus = "reject.png";
        }
        $pdo = DB::connection('BTID')->getPdo();
        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_ic_transfer ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
        $sth->bindParam(1, $data["entity_cd"]);
        $sth->bindParam(2, $data["project_no"]);
        $sth->bindParam(3, $data["doc_no"]);
        $sth->bindParam(4, $data["trx_type"]);
        $sth->bindParam(5, $status);
        $sth->bindParam(6, $data["level_no"]);
        $sth->bindParam(7, $data["usergroup"]);
        $sth->bindParam(8, $data["user_id"]);
        $sth->bindParam(9, $data["supervisor"]);
        $sth->bindParam(10, $reason);
        $sth->execute();
        if ($sth == true) {
            $msg = "You have successfully ".$descstatus." the IC Transfer No. ".$data["doc_no"];
            $notif = $descstatus."!";
            $st = 'OK';
            $image = $imagestatus;
        } else {
            $msg = "You failed to ".$descstatus." the IC Transfer No.".$data["doc_no"];
            $notif = 'Fail to '.$descstatus.'!';
            $st = 'OK';
            $image = "reject.png";
        }
        $msg1 = array(
            "Pesan" => $msg,
            "St" => $st,
            "notif" => $notif,
            "image" => $image
        );
        return view("email.after", $msg1);
    }

    public function feedback_ictransfer(Request $request)
    {
        $callback = array(
            'Error' => false,
            'Pesan' => '',
            'Status' => 200
        );

        try {
            $action = ''; // Initialize $action
            $bodyEMail = '';

            if (strcasecmp($request->status, 'R') == 0) {

                $action = 'Revision';
                $bodyEMail = 'Please revise '.$request->descs.' No. '.$request->doc_no.' with the reason : '.$request->reason;

            } else if (strcasecmp($request->status, 'C') == 0){
                
                $action = 'Cancellation';
                $bodyEMail = $request->descs.' No. '.$request->doc_no.' has been cancelled with the reason : '.$request->reason;

            } else if (strcasecmp($request->status, 'A') == 0) {
                $action = 'Approval';
                $bodyEMail = 'Your Request '.$request->descs.' No. '.$request->doc_no.' has been Approved';
            }

            // $list_of_urls = explode('; ', $request->url_file);
            // $list_of_files = explode('; ', $request->file_name);
            // $list_of_doc = explode('; ', $request->document_link);

            // $url_data = [];
            // $file_data = [];
            // $doc_data = [];

            // foreach ($list_of_urls as $url) {
            //     $url_data[] = $url;
            // }

            // foreach ($list_of_files as $file) {
            //     $file_data[] = $file;
            // }
            // foreach ($list_of_doc as $doc) {
            //     $doc_data[] = $doc;
            // }

            $EmailBack = array(
                'doc_no'            => $request->doc_no,
                'action'            => $action,
                'reason'            => $request->reason,
                'descs'             => $request->descs,
                'subject'		    => $request->subject,
                'bodyEMail'		    => $bodyEMail,
                'user_name'         => $request->user_name,
                'staff_act_send'    => $request->staff_act_send,
                'entity_name'       => $request->entity_name,
                'entity_cd'         => $request->entity_cd,
                // 'url_file'          => $url_data,
                // 'file_name'         => $file_data,
                // 'doc_link'          => $doc_data,
                'action_date'       => Carbon::now('Asia/Jakarta')->format('d-m-Y H:i')
            );

            $emailAddresses = strtolower($request->email_addr);
            $doc_no = $request->doc_no;
            $entity_name = $request->entity_name;
            $entity_cd = $request->entity_cd;
            $status = $request->status;
            $approve_seq = $request->approve_seq;
            if (!empty($emailAddresses)) {
                $emails = $emailAddresses;

                $emailSent = false;
                // Check if the email has been sent before for this document
                $cacheFile = 'email_feedback_sent_' . $approve_seq . '_' . $entity_cd . '_' . $doc_no . '_' . $status . '.txt';
                $cacheFilePath = storage_path('app/mail_cache/feedback_Ic_Transfer/' . date('Ymd'). '/' . $cacheFile);
                $cacheDirectory = dirname($cacheFilePath);
            
                // Ensure the directory exists
                if (!file_exists($cacheDirectory)) {
                    mkdir($cacheDirectory, 0755, true);
                }

                // Acquire an exclusive lock
                $lockFile = $cacheFilePath . '.lock';
                $lockHandle = fopen($lockFile, 'w');
                if (!flock($lockHandle, LOCK_EX)) {
                    // Failed to acquire lock, handle appropriately
                    fclose($lockHandle);
                    throw new Exception('Failed to acquire lock');
                }
        
                if (!file_exists($cacheFilePath)) {
                    // Send email
                    Mail::to($emails)->send(new StaffActionIcTransferMail($EmailBack));
            
                    // Mark email as sent
                    file_put_contents($cacheFilePath, 'sent');
                    $sentTo = $emailAddresses;
                    Log::channel('sendmailfeedback')->info('Email Feedback IC Transfer doc_no '.$doc_no.' Entity ' . $entity_cd.' berhasil dikirim ke: ' . $sentTo);
                    // return 'Email berhasil dikirim ke: ' . $sentTo;
                    // $emailSent = true;
                    $callback['Pesan'] = "Email feedback berhasil dikirim ke: $sentTo";
                    $callback['Error'] = false;
                    $callback['Status']= 200;
                }
            } else {
                Log::channel('sendmail')->warning("Tidak ada alamat email untuk feedback yang diberikan");
                Log::channel('sendmail')->warning($doc_no);
                // return "Tidak ada alamat email untuk feedback yang diberikan";
                $callback['Pesan'] = "Tidak ada alamat email untuk feedback yang diberikan";
                $callback['Error'] = true;
                $callback['Status']= 400;
            }
        } catch (\Exception $e) {
            Log::channel('sendmail')->error('Gagal mengirim email: ' . $e->getMessage());
            // return "Gagal mengirim email. Cek log untuk detailnya.";
            $callback['Pesan'] = "Gagal mengirim email: " . $e->getMessage();
            $callback['Error'] = true;
            $callback['Status']= 500;
        }   
    }
}
