<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Mail\SendPLLymanMail;

class PlBudgetLymanController extends Controller
{
    public function processModule($data) 
    {
        $amount = number_format( $data["amount"] , 2 , '.' , ',' );

        $list_of_approve = explode('; ',  $data["approve_exist"]);
        $approve_data = [];
        foreach ($list_of_approve as $approve) {
            $approve_data[] = $approve;
        }

        $dataArray = array(
            'descs'         => $data["descs"],
            'entity_name'   => $data["entity_name"],
            'project_name'  => $data["project_name"],
            'amount'        => $amount,
            'doc_no'        => $data["doc_no"],
            'user_name'     => $data["user_name"],
            'sender'        => $data["sender"],
            'module'        => $data["module"],
            'approve_list'  => $approve_data,
            'clarify_user'  => $data['clarify_user'],
            'clarify_email' => $data['clarify_email'],
            'sender_addr'   => $data['sender_addr'],
            'body'          => "Please approve RAB Budget No. ".$data['doc_no']." project ".$data["project_name"]. " with Amount ".$amount,
            'subject'       => "Need Approval for RAB Budget No. ".$data['doc_no'],
        );

        $data2Encrypt = array(
            'entity_cd'     => $data["entity_cd"],
            'project_no'    => $data["project_no"],
            'email_address' => $data["email_addr"],
            'level_no'      => $data["level_no"],
            'doc_no'        => $data["doc_no"],
            'user_id'       => $data["user_id"],
            'type'          => 'B',
            'type_module'   => 'PL',
            'text'          => 'Budget Lyman'
        );  

        // Melakukan enkripsi pada $dataArray
        $encryptedData = Crypt::encrypt($data2Encrypt);

        $type = $data2Encrypt['type'];
        $type_module = $data2Encrypt['type_module'];
        $module = 'plbudgetlyman';
    
        try {
            $pdo = DB::connection('BTID')->getPdo();
            $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_azure ?, ?, ?, ?, ?, ?, ?, ?;");
            $sth->bindParam(1, $data["entity_cd"]);
            $sth->bindParam(2, $data["doc_no"]);
            $sth->bindParam(3, $type);
            $sth->bindParam(4, $data["level_no"]);
            $sth->bindParam(5, $type_module);
            $sth->bindParam(6, $module);
            $sth->bindParam(7, $encryptedData);
            $sth->bindParam(8, $data["email_addr"]);
            $sth->execute();

            $sth->execute();
            $result = $sth->fetch(PDO::FETCH_NUM);
            $columnValue = $result[2];

            $emailAddresses = strtolower($data["email_addr"]);
            $doc_no = $data["doc_no"];
            $entity_cd = $data["entity_cd"];
            $dataArray['approve_id'] = $columnValue;
        
            // Check if email addresses are provided and not empty
            if (!empty($emailAddresses)) {
                $email = $emailAddresses; // Since $emailAddresses is always a single email address (string)

                // Check if the email has been sent before for this document
                $cacheFile = 'email_sent_' . $approve_seq . '_' . $entity_cd . '_' . $ref_no . '_' . $level_no . '.txt';
                $cacheFilePath = storage_path('app/mail_cache/send_PlBudget/' . date('Ymd') . '/' . $cacheFile);
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
                    Mail::to($email)->send(new SendPLLymanMail($encryptedData, $dataArray));
        
                    // Mark email as sent
                    file_put_contents($cacheFilePath, 'sent');
        
                    // Log the success
                    Log::channel('sendmailapproval')->info('Email PL Budget doc_no '.$doc_no.' Entity ' . $entity_cd.' berhasil dikirim ke: ' . $email);
                    return 'Email berhasil dikirim';
                } else {
                    // Email was already sent
                    Log::channel('sendmailapproval')->info('Email PL Budget doc_no '.$doc_no.' Entity ' . $entity_cd.' already sent to: ' . $email);
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

    public function update($status, $encrypt, $reason)
    {
        $data = Crypt::decrypt($encrypt);

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
        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_pl_budget_lyman ?, ?, ?, ?, ?, ?;");
        $sth->bindParam(1, $data["entity_cd"]);
        $sth->bindParam(2, $data["project_no"]);
        $sth->bindParam(3, $data["doc_no"]);
        $sth->bindParam(4, $status);
        $sth->bindParam(5, $data["level_no"]);
        $sth->bindParam(6, $data["user_id"]);
        $sth->execute();
        if ($sth == true) {
            $msg = "You Have Successfully ".$descstatus." the RAB Budget No. ".$data["doc_no"];
            $notif = $descstatus." !";
            $st = 'OK';
            $image = $imagestatus;
        } else {
            $msg = "You Failed to ".$descstatus." the RAB Budget No.".$data["doc_no"];
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
