<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Mail\SendCmProgressMail;
use PDO;
use DateTime;

class CmProgressController extends Controller
{
    public function Mail(Request $request)
    {

        $curr_progress = number_format( $request->curr_progress , 2 , '.' , ',' );

        $prev_progress = number_format( $request->prev_progress , 2 , '.' , ',' );

        $amount = number_format( $request->amount , 2 , '.' , ',' );

        $prev_progress_amt = number_format( $request->prev_progress_amt , 2 , '.' , ',' );

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
            $url_data[] = $url;
        }

        foreach ($list_of_files as $file) {
            $file_data[] = $file;
        }

        $dataArray = array(
            'sender'            => $request->sender,
            'entity_name'       => $request->entity_name,
            'descs'             => $request->descs,
            'user_name'         => $request->user_name,
            'progress_no'       => $request->progress_no,
            "surveyor"			=> $request->surveyor,
            'doc_link'          => $request->doc_link,
            'curr_cd'           => $request->curr_cd,
            "contract_desc"		=> $request->contract_desc,
            'curr_progress'     => $curr_progress,
            'approve_seq'       => $request->approve_seq,
            'amount'            => $amount,
            'prev_progress'     => $prev_progress,
            'prev_progress_amt' => $prev_progress_amt,
            'contract_no'       => $request->contract_no,
            'entity_name'       => $request->entity_name,
            'module'            => $request->module,
            'approve_list'      => $approve_data,
            'url_file'          => $url_data,
            'file_name'         => $file_data,
            'clarify_user'      => $request->clarify_user,
            'clarify_email'     => $request->clarify_email,
            'sender_addr'       => $request->sender_addr,
            'body'              => "Please approve Contract Progress No. ".$request->doc_no." for ".$request->descs,
            'subject'           => "Need Approval for Contract Progress No.  ".$request->doc_no,
        );

        $data2Encrypt = array(
            'entity_cd'     => $request->entity_cd,
            'project_no'    => $request->project_no,
            'email_address' => $request->email_addr,
            'level_no'      => $request->level_no,
            'doc_no'        => $request->doc_no,
            'ref_no'        => $request->ref_no,
            'usergroup'     => $request->usergroup,
            'user_id'       => $request->user_id,
            'supervisor'    => $request->supervisor,
            'type'          => 'A',
            'type_module'   => 'CM',
            'text'          => 'Contract Progress'
        );

        

        // Melakukan enkripsi pada $dataArray
        $encryptedData = Crypt::encrypt($data2Encrypt);

        $type = $data2Encrypt['type'];
        $type_module = $data2Encrypt['type_module'];
        $module = 'cmprogress';
    
        try {
            $pdo = DB::connection('BTID')->getPdo();
            $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_azure ?, ?, ?, ?, ?, ?, ?, ?;");
            $sth->bindParam(1, $request->entity_cd);
            $sth->bindParam(2, $request->doc_no);
            $sth->bindParam(3, $type);
            $sth->bindParam(4, $request->level_no);
            $sth->bindParam(5, $type_module);
            $sth->bindParam(6, $module);
            $sth->bindParam(7, $encryptedData);
            $sth->bindParam(8, $request->email_addr);
            $sth->execute();

            $sth->execute();
            $result = $sth->fetch(PDO::FETCH_NUM);
            $columnValue = $result[0];

            $emailAddresses = strtolower($request->email_addr);
            $approve_seq = $request->approve_seq;
            $entity_cd = $request->entity_cd;
            $doc_no = $request->doc_no;
            $level_no = $request->level_no;
            $dataArray['approve_id'] = $columnValue;
        
            // Check if email addresses are provided and not empty
            if (!empty($emailAddresses)) {
                $email = $emailAddresses; // Since $emailAddresses is always a single email address (string)
                
                // Check if the email has been sent before for this document
                $cacheFile = 'email_sent_' . $approve_seq . '_' . $entity_cd . '_' . $doc_no . '_' . $level_no . '.txt';
                $cacheFilePath = storage_path('app/mail_cache/send_cmprogress/' . date('Ymd') . '/' . $cacheFile);
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
                    Mail::to($email)->send(new SendCmProgressMail($encryptedData, $dataArray));
        
                    // Mark email as sent
                    file_put_contents($cacheFilePath, 'sent');
        
                    // Log the success
                    Log::channel('sendmailapproval')->info('Email CM Progress doc_no '.$doc_no.' Entity ' . $entity_cd.' berhasil dikirim ke: ' . $email);
                    return 'Email berhasil dikirim ke: ' . $email;
                } else {
                    // Email was already sent
                    Log::channel('sendmailapproval')->info('Email CM Progress doc_no '.$doc_no.' Entity ' . $entity_cd.' already sent to: ' . $email);
                    return 'Email has already been sent to: ' . $email;
                }
            } else {
                // No email address provided
                Log::channel('sendmail')->warning("No email address provided for document " . $doc_no);
                return "No email address provided";
            }
        } catch (\Exception $e) {
            Log::channel('sendmail')->error('Gagal mengirim email: ' . $e->getMessage());
            return "Gagal mengirim email: " . $e->getMessage();
        }
    }

    public function processData($status='', $encrypt='')
    {
        Artisan::call('config:cache');
        Artisan::call('cache:clear');
        Cache::flush();
        $cacheKey = 'processData_' . $encrypt;

        // Check if the data is already cached
        if (Cache::has($cacheKey)) {
            // If cached data exists, clear it
            Cache::forget($cacheKey);
        }

        Log::info('Starting database query execution for processData');
        $data = Crypt::decrypt($encrypt);
        
        $msg = " ";
        $msg1 = " ";
        $notif = " ";
        $st = " ";
        $image = " ";

        Log::info('Decrypted data: ' . json_encode($data));

        $where = [
            'doc_no'        => $data["doc_no"],
            'entity_cd'     => $data["entity_cd"],
            'level_no'      => $data["level_no"],
            'type'          => $data["type"],
            'module'        => $data["type_module"],
        ];

        $query = DB::connection('BTID')
            ->table('mgr.cb_cash_request_appr')
            ->where($where)
            ->whereIn('status', ["A", "R", "C"])
            ->get();

        Log::info('First query result: ' . json_encode($query));

        if (count($query) > 0) {
            $msg = 'You Have Already Made a Request to Contract Progress No. ' . $data["doc_no"];
            $notif = 'Restricted!';
            $st  = 'OK';
            $image = "double_approve.png";
            $msg1 = [
                "Pesan" => $msg,
                "St" => $st,
                "notif" => $notif,
                "image" => $image
            ];
            return view("email.after", $msg1);
        } else {
            $where2 = [
                'doc_no'        => $data["doc_no"],
                'status'        => 'P',
                'entity_cd'     => $data["entity_cd"],
                'level_no'      => $data["level_no"],
                'type'          => $data["type"],
                'module'        => $data["type_module"],
            ];

            $query2 = DB::connection('BTID')
                ->table('mgr.cb_cash_request_appr')
                ->where($where2)
                ->get();

            Log::info('Second query result: ' . json_encode($query2));

            if (count($query2) == 0) {
                $msg = 'There is no Contract Progress with No. ' . $data["doc_no"];
                $notif = 'Restricted!';
                $st  = 'OK';
                $image = "double_approve.png";
                $msg1 = [
                    "Pesan" => $msg,
                    "St" => $st,
                    "notif" => $notif,
                    "image" => $image
                ];
                return view("email.after", $msg1);
            } else {
                $name   = " ";
                $bgcolor = " ";
                $valuebt  = " ";
                if ($status == 'A') {
                    $name   = 'Approval';
                    $bgcolor = '#40de1d';
                    $valuebt  = 'Approve';
                } elseif ($status == 'R') {
                    $name   = 'Revision';
                    $bgcolor = '#f4bd0e';
                    $valuebt  = 'Revise';
                } else {
                    $name   = 'Cancellation';
                    $bgcolor = '#e85347';
                    $valuebt  = 'Cancel';
                }
                $dataArray = Crypt::decrypt($encrypt);
                $data = [
                    "status"    => $status,
                    "encrypt"   => $encrypt,
                    "name"      => $name,
                    "bgcolor"   => $bgcolor,
                    "valuebt"   => $valuebt
                ];
                Artisan::call('config:cache');
                Artisan::call('cache:clear');
                return view('email/cmprogress/passcheckwithremark', $data);
            }
        }
    }

    public function update(Request $request)
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
            $reason = '0';
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
        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_cm_progress ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
        $sth->bindParam(1, $data["entity_cd"]);
        $sth->bindParam(2, $data["project_no"]);
        $sth->bindParam(3, $data["doc_no"]);
        $sth->bindParam(4, $data["ref_no"]);
        $sth->bindParam(5, $status);
        $sth->bindParam(6, $data["level_no"]);
        $sth->bindParam(7, $data["usergroup"]);
        $sth->bindParam(8, $data["user_id"]);
        $sth->bindParam(9, $data["supervisor"]);
        $sth->bindParam(10, $reason);
        $sth->execute();
        if ($sth == true) {
            $msg = "You Have Successfully ".$descstatus." the Contract Progress No. ".$data["doc_no"];
            $notif = $descstatus." !";
            $st = 'OK';
            $image = $imagestatus;
        } else {
            $msg = "You Failed to ".$descstatus." the Contract Progress No.".$data["doc_no"];
            $notif = 'Fail to '.$descstatus.' !';
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
}
