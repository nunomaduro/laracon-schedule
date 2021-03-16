<?php

namespace App\Commands;

use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Carbon\Factory;
use LaravelZero\Framework\Commands\Command;

class SchedulingCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'scheduling';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display Laracon scheduling';

    /**
     * The conference schedule.
     *
     * @var array
     */
    protected $scheduling = [
        '10:00' => '"Diving the Queue" by MOHAMED SAID',
        '11:00' => '"The final Laravel Service Container talk" by CHRISTOPH RUMPEL',
        '12:20' => '"Routing Laravel" by BOBBY BOUWMANN',
        '13:20' => '"Laravel Update" by TAYLOR OTWELL',
        '14:40' => '"Understanding Laravel broadcasting" by MARCEL POCIOT',
        '15:40' => '"Understanding Foundation: What ties everything together" by MIGUEL PIEDRAFITA',
        '17:00' => '"Doing small things with Livewire & Alpine" by CALEB PORZIO',
        '18:00' => '"Laravel\'s Artisan Console component" by NUNO MADURO',
    ];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! Str::contains(php_uname('s'), 'Darwin')) {
            abort(401, 'Only Mac OS is supported at this time.');
        }

        $userTimeZone = $this->getTimeZone();

        $this->line('');
        $this->line("    <options=bold,reverse;fg=magenta> LARACON ONLINE 2021 </>");
        $this->line('');

        $this->line('    Your timezone: ' . $userTimeZone . '.');
        $this->line('');
        collect($this->scheduling)->each(function ($talk, $schedule) use ($userTimeZone) {
            $dateTimeAsString = Carbon::parse("2021-03-17 $schedule:00", 'America/New_York')
                ->setTimezone($userTimeZone)
                ->calendar();

            $this->line("    <options=bold>$dateTimeAsString</> - $talk");
        });

        $this->line('');
        $this->line('    <fg=magenta;options=bold>Join the community:</> ');
        $this->line('    Telegram: https://t.me/laracononline2021.');
        $this->line('    Discord: https://discord.com/invite/mPZNm7A.');
        $this->line('');
    }

    /**
     * Returns the user's timezone.
     *
     * @return string
     */
    public function getTimeZone()
    {
        $disk = Storage::disk('local');

        if (! $disk->exists('.laravel-schedule')) {
            $timeZone = exec('/bin/ls -l /etc/localtime|/usr/bin/cut -d"/" -f8,9', $_, $exitCode);

            if ($exitCode > 0) {
                abort(500, 'Unable to retrieve timezone');
            }

            $disk->put('.laravel-schedule', $timeZone);
        }

        return $disk->get('.laravel-schedule');
    }
}
