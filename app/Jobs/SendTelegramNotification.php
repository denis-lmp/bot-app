<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 14/12/2023
 * Time: 14:04
 */

namespace App\Jobs;

use App\Models\User;
use App\Notifications\TelegramNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $message;
    private string $chatId;

    /**
     * Create a new job instance.
     *
     * @param  string  $message
     * @return void
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        User::find(1)->notify(new TelegramNotification($this->message));
    }
}