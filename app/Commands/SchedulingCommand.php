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
    const BREAK_LENGTH_MINUTES = 20;
    const TALK_LENGTH_MINUTES = 60;
    const BREAK_SLOT_NAME = 'Break';
    const EXIT_SLOT_NAME = 'Exit';

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
        '12:00' => self::BREAK_SLOT_NAME,
        '12:20' => '"Routing Laravel" by BOBBY BOUWMANN',
        '13:20' => '"Laravel Update" by TAYLOR OTWELL',
        '14:20' => self::BREAK_SLOT_NAME,
        '14:40' => '"Understanding Laravel broadcasting" by MARCEL POCIOT',
        '15:40' => '"Understanding Foundation: What ties everything together" by MIGUEL PIEDRAFITA',
        '16:40' => self::BREAK_SLOT_NAME,
        '17:00' => '"Doing small things with Livewire & Alpine" by CALEB PORZIO',
        '18:00' => '"Laravel\'s Artisan Console component" by NUNO MADURO',
        '19:00' => self::EXIT_SLOT_NAME,
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
        $endsAt = '2021-03-17 19:00';

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
        } elseif (Carbon::parse($endsAt, 'America/New_York')->setTimezone($userTimeZone)->isPast()) {
            $this->line("    Event status : Event has ended. See you next time!");
        } else {
            $this->line("    Event status : Already started.");
        }

        $showedHappeningNowOnce = false;

        $this->line('');
        collect($this->scheduling)->each(function ($talk, $schedule) use ($userTimeZone, &$showedHappeningNowOnce) {
            $dateTime = Carbon::parse("2021-03-17 $schedule:00", 'America/New_York')
                ->setTimezone($userTimeZone);

            $lineOptions = 'bold';

            if (! $showedHappeningNowOnce && $this->happeningNow($dateTime, $userTimeZone, $talk)) {
                $lineOptions = 'bold,reverse;fg=yellow';
                $showedHappeningNowOnce = true;
            }

            $this->line("    <options={$lineOptions}>{$dateTime->calendar()}</> - $talk");
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
            case Str::contains(php_uname('s'), ['Darwin', 'Linux']):
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

    private function happeningNow(Carbon $dateTime, string $userTimeZone, string $talk): bool
    {
        if ($talk === self::EXIT_SLOT_NAME) {
            return false;
        }

        $length = $talk === self::BREAK_SLOT_NAME
            ? self::BREAK_LENGTH_MINUTES
            : self::TALK_LENGTH_MINUTES;

        return Carbon::now($userTimeZone)->between(
            $dateTime,
            $dateTime->copy()->addMinutes($length)
        );
    }
}
