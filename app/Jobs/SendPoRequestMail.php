<?php

namespace App\Jobs;

use App\Mail\SendPoRMail;
use Illuminate\Bus\Queueable;
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

            Log::info('Email success: '.$this->emailAddress);

        } catch (\Exception $e) {

            Log::error('Email failed: '.$e->getMessage());
        }
    }
}