<?php

namespace App\Commands;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class SchedulingCommand extends Command
{
    public const BREAK_LENGTH_MINUTES = 20;
    public const TALK_LENGTH_MINUTES = 40;
    public const BREAK_SLOT_NAME = 'Break';
    public const EXIT_SLOT_NAME = 'Exit';
    public const LIGHTNING_TALKS_SLOT_NAME = 'LIGHTNING TALKS';
    public const TITLE = 'LARACON ONLINE SUMMER 2022';
    public const TIMEZONE = 'UTC';
    public const DATE = '2022-09-14';
    public const STARTS_AT_TIME = '12:45';
    public const ENDS_AT_TIME = '21:45';
    public const INDENT = '    ';

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'scheduling {--late=0 : Adjust all times by this many minutes.} {--no-cache : Clears the cached timezone.}';

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
        '13:00' => '"Not Quite My Type" by Kai Sassnowski',
        '13:40' => '"Kubernetes and Laravel" by Bosun Egberinde',
        '14:20' => '"The future of Livewire" by Caleb Porzio',
        '15:00' => self::BREAK_SLOT_NAME,
        '15:20' => self::LIGHTNING_TALKS_SLOT_NAME,
        '16:35' => '"Abstracting too Early" by Matt Stauffer',
        '17:15' => self::BREAK_SLOT_NAME,
        '17:35' => '"Laravel Update" by Taylor Otwell',
        '18:35' => '"Database Performance for Application Developers" by Aaro Francis',
        '19:15' => '"Christoph Dreams of Simple Code" by Christoph Rumpel',
        '19:55' => self::BREAK_SLOT_NAME,
        '20:15' => self::LIGHTNING_TALKS_SLOT_NAME,
        '21:45' => '"Browsers are Magical Creatures" by Stephen Rees-Carter',
    ];

    /**
     * The lightning talks schedule.
     *
     * @var array<int, string>
     */
    protected $lightningTalks = [
        '"Sustainable Self-Care" by Marje Holmstrom-Sabo',
        '"Let\'s Get Physical: Database Internals and You" by Tim Martin',
        '"Deep Dive into Carbon" by Ralph J. Smit',
        '"UI and Component testing with Cypress" by Marcel Pociot',
        '"The Hitchhiker\'s Guide to the Laravel Community" by Caneco',
        '"Is there any problem Git interactive rebase can\'t solve?" by Rissa Jackson',
        '"Meaningful Mentorship" by Alex Six',
        '"I shall say... err define this only once" by Freek Van der Herten',
        '"I can\'t believe it\'s not local!" by Chris Fidao',
        '"Valid Variants of Validating Validation" by Luke Downing',
        '"A Grab Bag of Useful Tips" by Colin DeCarlo'
    ];

    /**
     * Community.
     *
     * @var array<string, string>
     */
    protected $community = [
        'Telegram' => 'https://t.me/laracononline2021.',
        'Discord' => 'https://discord.com/invite/mPZNm7A.'
    ];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $userTimeZone = $this->getTimeZone();
        $late = (int) $this->option('late');

        $this->line('');
        $this->line(self::INDENT . '<options=bold,reverse;fg=magenta>' . self::TITLE . '</>');
        $this->line('');

        $this->line(self::INDENT . 'Your timezone: ' . $userTimeZone . '.');
        if ($late <> 0) {
            $this->line(self::INDENT . 'Laracon is running ' . $late .' minutes late.');
        }

        $startsAt = self::DATE . ' ' . self::STARTS_AT_TIME;
        $endsAt = self::DATE . ' ' . self::ENDS_AT_TIME;

        $hoursLeft = Carbon::parse($startsAt, self::TIMEZONE)
                ->setTimezone($userTimeZone)
                ->addMinutes($late)
                ->diffInHours(now(), false);

        $minutesLeft = Carbon::parse($startsAt, self::TIMEZONE)
                ->setTimezone($userTimeZone)
                ->addMinutes($late)
                ->diffInMinutes(now(), false);

        if ($hoursLeft < 0) {
            $hoursLeft = abs($hoursLeft);
            $this->line(self::INDENT . "Event status : Starts in $hoursLeft hours.");
        } elseif ($minutesLeft < 0) {
            $minutesLeft = abs($minutesLeft);
            $this->line(self::INDENT . "Event status : Starts in $minutesLeft minutes.");
        } elseif (Carbon::parse($endsAt, self::TIMEZONE)->setTimezone($userTimeZone)->isPast()) {
            $this->line(self::INDENT . "Event status : Event has ended. See you next time!");
        } else {
            $this->line(self::INDENT . "Event status : Already started.");
        }

        $showedHappeningNowOnce = false;

        $this->line('');
        collect($this->scheduling)->each(function ($talk, $schedule) use ($userTimeZone, $late, &$showedHappeningNowOnce) {
            $dateTime = Carbon::parse(self::DATE . " $schedule:00", self::TIMEZONE)
                ->addMinutes($late)
                ->setTimezone($userTimeZone);

            $lineOptions = 'bold';

            if (! $showedHappeningNowOnce && $this->happeningNow($dateTime, $userTimeZone, $talk)) {
                $lineOptions = 'bold,reverse;fg=yellow';
                $showedHappeningNowOnce = true;
            }

            $this->line(self::INDENT . "<options={$lineOptions}>{$dateTime->calendar()}</> - $talk");

            if ($talk === self::LIGHTNING_TALKS_SLOT_NAME) {
                collect($this->lightningTalks)->each(fn($talk) => $this->line(self::INDENT . "  - {$talk}"));
            }
        });

        $this->line('');
        $this->line(self::INDENT . '<fg=magenta;options=bold>Join the community:</> ');
        foreach($this->community as $platform => $link){
            $this->line(self::INDENT . "$platform: $link");
        }
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
