<?php

namespace App\Commands;

use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
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
        $userTimeZone = $this->getTimeZone();

        $this->line('');
        $this->line("    <options=bold,reverse;fg=magenta> LARACON ONLINE 2021 </>");
        $this->line('');

        $this->line('    Your timezone: ' . $userTimeZone . '.');

        $startsAt = '2021-03-17 10:00';

        $hoursLeft = Carbon::parse($startsAt, 'America/New_York')
                ->setTimezone($userTimeZone)
                ->diffInHours(now(), false);

        $minutesLeft = Carbon::parse($startsAt, 'America/New_York')
                ->setTimezone($userTimeZone)
                ->diffInMinutes(now(), false);

        if ($hoursLeft < 0) {
            $hoursLeft = abs($hoursLeft);
            $this->line("    Event status : Starts in $hoursLeft hours.");
        } elseif ($minutesLeft < 0) {
            $minutesLeft = abs($minutesLeft);
            $this->line("    Event status : Starts in $minutesLeft minutes.");
        } else {
            $this->line("    Event status : Already started.");
        }

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
        $this->line('    Discord : https://discord.com/invite/mPZNm7A.');
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
            $timeZone = $this->getSystemTimeZone($exitCode);

            if ($exitCode > 0 || $timeZone === '') {
                abort(500, 'Unable to retrieve timezone');
            }

            $disk->put('.laravel-schedule', $timeZone);
        }

        return $disk->get('.laravel-schedule');
    }

    /**
     * @param &$exitCode
     * @return string
     */
    private function getSystemTimeZone(&$exitCode): string
    {
        switch (true) {
            case Str::contains(php_uname('s'), 'Darwin'):
                $this->line('Please enter your "sudo" password so we can retrieve your timezone:');
                return ltrim(exec('sudo systemsetup -gettimezone', $_, $exitCode), 'Time Zone: ');
            case Str::contains(php_uname('s'), 'Linux'):
                if (file_exists('/etc/timezone')) {
                    return ltrim(exec('cat /etc/timezone', $_, $exitCode));
                }

                return exec('date +%Z', $_, $exitCode);
            case Str::contains(php_uname('s'), 'Windows'):
                return ltrim($this->getIanaTimeZoneFromWindowsIdentifier(exec('tzutil /g', $_, $exitCode)));
            default:
                abort(401, 'Your OS is not supported at this time.');
        }
    }

    /**
     * Returns an IANA time zone string from a Microsoft Windows time zone identifier
     *  `./data/windowsZones.json` file content from windowsZones.xml
     *  https://github.com/unicode-org/cldr/blob/master/common/supplemental/windowsZones.xml
     *
     * @param string $timeZoneId Windows time zone identifier (i.e. 'E. South America Standard Time')
     * @return string
     */
    private function getIanaTimeZoneFromWindowsIdentifier($timeZoneId)
    {
        $json =Storage::disk('windowsconfig')->get('windowsZones.json');
        $zones = collect(json_decode($json));

        $timeZone = $zones->firstWhere('windowsIdentifier', '=', $timeZoneId);

        abort_if(is_null($timeZone), 401, 'Windows time zone not found.');

        return head($timeZone->iana);
    }
}
