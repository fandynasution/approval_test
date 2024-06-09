<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Mail\SendCmEntryMail;
use PDO;
use DateTime;

class CmEntryController extends Controller
{
    public function Mail(Request $request)
    {
        $contract_amt = number_format( $request->contract_amt , 2 , '.' , ',' );
        $auth_vo = number_format( $request->auth_vo , 2 , '.' , ',' );

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
            'sender'        => $request->sender,
            'sender_addr'   => $request->sender_addr,
            'entity_name'   => $request->entity_name,
            'descs'         => $request->descs,
            'doc_link'      => $request->doc_link,
            'works_descs'   => $request->works_descs,
            'user_name'     => $request->user_name,
            'approve_seq'   => $request->approve_seq,
            'module'        => $request->module,
            'contract_no'   => $request->contract_no,
            'contract_amt'  => $contract_amt,
            'auth_vo'       => $auth_vo,
            'approve_list'  => $approve_data,
            'url_file'          => $url_data,
            'file_name'         => $file_data,
            'clarify_user'  => $request->clarify_user,
            'clarify_email' => $request->clarify_email,
            'body'          => "Please approve Contract Entry No. ".$request->doc_no." for ".$request->descs,
            'subject'       => "Need Approval for Contract Entry No.  ".$request->doc_no,
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
            'type'          => 'E',
            'type_module'   => 'CM',
            'text'          => 'Contract Entry'
        );

        

        // Melakukan enkripsi pada $dataArray
        $encryptedData = Crypt::encrypt($data2Encrypt);
    
        try {
            $emailAddresses = strtolower($request->email_addr);
            $approve_seq = $request->approve_seq;
            $entity_cd = $request->entity_cd;
            $doc_no = $request->doc_no;
            $status = $request->status;
            $level_no = $request->level_no;
        
            // Check if email addresses are provided and not empty
            if (!empty($emailAddresses)) {
                $email = $emailAddresses; // Since $emailAddresses is always a single email address (string)
                
                // Check if the email has been sent before for this document
                $cacheFile = 'email_sent_' . $approve_seq . '_' . $entity_cd . '_' . $doc_no . '_' . $status . '_' . $level_no . '.txt';
                $cacheFilePath = storage_path('app/mail_cache/send_cmentry/' . date('Ymd') . '/' . $cacheFile);
                $cacheDirectory = dirname($cacheFilePath);
        
                // Ensure the directory exists
                if (!file_exists($cacheDirectory)) {
                    mkdir($cacheDirectory, 0755, true);
                }
        
                if (!file_exists($cacheFilePath)) {
                    // Send email
                    Mail::to($email)->send(new SendCmEntryMail($encryptedData, $dataArray));
        
                    // Mark email as sent
                    file_put_contents($cacheFilePath, 'sent');
        
                    // Log the success
                    Log::channel('sendmailfeedback')->info('Email CM Entry doc_no '.$doc_no.' Entity ' . $entity_cd.' berhasil dikirim ke: ' . $email);
                    return 'Email berhasil dikirim ke: ' . $email;
                } else {
                    // Email was already sent
                    Log::channel('sendmailfeedback')->info('Email CM Entry doc_no '.$doc_no.' Entity ' . $entity_cd.' already sent to: ' . $email);
                    return 'Email has already been sent to: ' . $email;
                }
            } else {
                Log::channel('sendmail')->warning("Tidak ada alamat email yang diberikan");
                Log::channel('sendmail')->info($doc_no);
                return "Tidak ada alamat email yang diberikan";
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
            $msg = 'You Have Already Made a Request to Contract Entry No. ' . $data["doc_no"];
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
                $msg = 'There is no Contract Entry with No. ' . $data["doc_no"];
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
                return view('email/cmentry/passcheckwithremark', $data);
            }
        }
    }

    public function update(Request $request)
    {
        $data = Crypt::decrypt($request->encrypt);

        $status = $request->status;

        $descstatus = " ";
        $imagestatus = " ";

        $msg = " ";
        $msg1 = " ";
        $notif = " ";
        $st = " ";
        $image = " ";

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
        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_cm_contract_entry ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
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
            $msg = "You Have Successfully ".$descstatus." the Contract Entry No. ".$data["doc_no"];
            $notif = $descstatus." !";
            $st = 'OK';
            $image = $imagestatus;
        } else {
            $msg = "You Failed to ".$descstatus." the Contract Entry No.".$data["doc_no"];
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
