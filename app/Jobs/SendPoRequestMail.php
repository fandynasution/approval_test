<?php

namespace App\Jobs;

use App\Mail\SendPoRMail;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendPoRequestMail
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $emailAddress;
    protected $encryptedData;
    protected $dataArray;

    public function __construct($emailAddress, $encryptedData, $dataArray)
    {
        $this->emailAddress = $emailAddress;
        $this->encryptedData = $encryptedData;
        $this->dataArray = $dataArray;
    }

    public function handle()
    {
        try {

            Mail::to($this->emailAddress)->send(
                new SendPoRMail($this->encryptedData, $this->dataArray)
            );

            DB::connection('BTID')
            ->table('mgr.cb_cash_request_appr')
            ->where([
                'entity_cd' => $this->dataArray['entity_cd'],
                'doc_no' => $this->dataArray['doc_no'],
                'status' => 'P',
                'type' => 'Q',
                'module' => 'PO',
                'approve_seq' => $this->dataArray['approve_seq'],
                'level_no' => $this->dataArray['level_no'],
            ])
            ->update([
                'sent_mail' => 'Y',
                'sent_mail_date' => now(),
            ]);

            Log::channel('sendmailapproval')->info(
                'Email success: ' . $this->emailAddress
            );

        } catch (\Exception $e) {

            Log::channel('sendmailapproval')->error(
                'Email failed: ' . $e->getMessage()
            );
        }
    }
}