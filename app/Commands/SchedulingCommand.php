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
    public const BREAK_LENGTH_MINUTES = 20;
    public const TALK_LENGTH_MINUTES = 30;
    public const BREAK_SLOT_NAME = 'Break';
    public const EXIT_SLOT_NAME = 'Exit';
    public const LIGHTNING_TALKS_SLOT_NAME = 'LIGHTNING TALKS';

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'scheduling {--no-cache : Clears the cached timezone.}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display Laracon scheduling';

    /**
     * The conference schedule.
     *
     * @var array<string, string>
     */
    protected $scheduling = [
        '14:00' => '"Asynchronous Laravel" by Mohamed Said',
        '14:35' => '"Bugfixing Your Career" by Diana Scharf',
        '15:10' => '"Getting The Most Out Of Cashier Stripe & Paddle" by Dries Vints',
        '15:45' => '"Why Refactoring Is The Best Tool To Write Better Code" by Christoph Rumpel',
        '16:20' => self::BREAK_SLOT_NAME,
        '16:40' => '"Laravel Update" by Taylor Otwell',
        '17:40' => self::LIGHTNING_TALKS_SLOT_NAME,
        '18:40' => self::BREAK_SLOT_NAME,
        '19:00' => '"Types In Laravel" by Nuno Maduro',
        '19:35' => '"Top Ten Tailwind Tricks" by Caneco',
        '20:10' => '"How To Write Delightful Documentation" by Allie Nimmons',
        '20:45' => '"Think Like a Hacker" by Stephen Rees-Carter',
        '21:20' => self::BREAK_SLOT_NAME,
        '21:40' => '"Manage SEO with Laravel and Nova" by Kristin Collins',
        '22:15' => '"Inertia.js Forms, Modals & SSR" by Jonathan Reinink',
        '22:50' => '"Practical Laravel Unit Testing" by Colin DeCarlo',
    ];

    /**
     * The lightning talks schedule.
     *
     * @var array<int, string>
     */
    protected $lightningTalks = [
        '"Learning In Public" by Zuzana Kunckova',
        '"Tailwind Grid" by Shruti Balasa',
        '"Inclusive Language Practices" by Marje Holmstrom-Sabo',
        '"An Introduction To Snapshot Testing" by Freek Van der Herten',
        '"Level Up Your App With Composite Primary Keys" by Alex Wulf'
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
        $this->line("    <options=bold,reverse;fg=magenta> LARACON ONLINE SUMMER 2021 </>");
        $this->line('');

        $this->line('    Your timezone: ' . $userTimeZone . '.');

        $startsAt = '2021-09-01 13:50';
        $endsAt = '2021-09-01 23:25';

        $hoursLeft = Carbon::parse($startsAt, 'UTC')
                ->setTimezone($userTimeZone)
                ->diffInHours(now(), false);

        $minutesLeft = Carbon::parse($startsAt, 'UTC')
                ->setTimezone($userTimeZone)
                ->diffInMinutes(now(), false);

        if ($hoursLeft < 0) {
            $hoursLeft = abs($hoursLeft);
            $this->line("    Event status : Starts in $hoursLeft hours.");
        } elseif ($minutesLeft < 0) {
            $minutesLeft = abs($minutesLeft);
            $this->line("    Event status : Starts in $minutesLeft minutes.");
        } elseif (Carbon::parse($endsAt, 'UTC')->setTimezone($userTimeZone)->isPast()) {
            $this->line("    Event status : Event has ended. See you next time!");
        } else {
            $this->line("    Event status : Already started.");
        }

        $showedHappeningNowOnce = false;

        $this->line('');
        collect($this->scheduling)->each(function ($talk, $schedule) use ($userTimeZone, &$showedHappeningNowOnce) {
            $dateTime = Carbon::parse("2021-09-01 $schedule:00", 'UTC')
                ->setTimezone($userTimeZone);

            $lineOptions = 'bold';

            if (! $showedHappeningNowOnce && $this->happeningNow($dateTime, $userTimeZone, $talk)) {
                $lineOptions = 'bold,reverse;fg=yellow';
                $showedHappeningNowOnce = true;
            }

            $this->line("    <options={$lineOptions}>{$dateTime->calendar()}</> - $talk");

            if ($talk === self::LIGHTNING_TALKS_SLOT_NAME) {
                collect($this->lightningTalks)->each(fn($talk) => $this->line("      - {$talk}"));
            }
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
        $filename = '.laravel-schedule';

        if ($this->option('no-cache') && $disk->exists($filename)) {
            $disk->delete($filename);
        }

        if (! $disk->exists($filename)) {
            $timeZone = $this->getSystemTimeZone($exitCode);

            if ($exitCode > 0 || $timeZone === '') {
                abort(500, 'Unable to retrieve timezone');
            }

            $disk->put($filename, $timeZone);
        }

        return $disk->get($filename);
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
