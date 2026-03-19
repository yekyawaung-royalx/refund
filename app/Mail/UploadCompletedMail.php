<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;

class UploadCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $title;
    public int $total;
    public int $processed;
    public int $failed;
    public string $upload_by;

    public function __construct($title, $total, $processed, $failed, $upload_by)
    {
        $this->title = $title;
        $this->total = $total;
        $this->processed = $processed;
        $this->failed = $failed;
        $this->upload_by = $upload_by;
    }

    public function build()
    {
        return $this->subject('File Import Completed')
            ->view('emails.upload-completed');
    }
}