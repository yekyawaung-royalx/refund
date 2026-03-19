<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExportCompletedMail extends Mailable
{
    use SerializesModels;

    public $exportDate;
    public $totalRows;
    public $duration;
    public $fileName;
    public $startTime;
    public $endTime;

    public function __construct($exportDate, $totalRows, $duration, $fileName, $startTime, $endTime)
    {
        $this->exportDate = $exportDate;
        $this->totalRows  = $totalRows;
        $this->duration   = $duration;
        $this->fileName   = $fileName;
        $this->startTime  = $startTime;
        $this->endTime    = $endTime;
    }

    public function build()
    {
        return $this->subject("Export Completed - {$this->exportDate}")
            ->view('emails.export-completed');
    }
}