<?php

namespace App\Mail;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Announcement $announcement) {}

    public function build(): self
    {
        return $this->subject($this->announcement->title)->view('emails.announcement');
    }
}
