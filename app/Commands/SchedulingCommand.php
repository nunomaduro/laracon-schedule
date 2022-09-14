<?php

namespace App\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class SchedulingCommand extends Command
{
    public const BREAK_LENGTH_MINUTES = 20;

    public const TALK_LENGTH_MINUTES = 40;

    public const BREAK_SLOT_NAME = 'Break';

    public const EXIT_SLOT_NAME = 'Exit';

    public const LIGHTNING_TALKS_1 = 'LIGHTNING TALKS #1';

    public const LIGHTNING_TALKS_2 = 'LIGHTNING TALKS #2';

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
    protected array $scheduling = [
        '13:00' => '<options=bold>"Not Quite My Type"</> by Kai Sassnowski',
        '13:40' => '<options=bold>"Kubernetes and Laravel"</> by Bosun Egberinde',
        '14:20' => '<options=bold>"The future of Livewire"</> by Caleb Porzio',
        '15:00' => self::BREAK_SLOT_NAME,
        '15:20' => self::LIGHTNING_TALKS_1,
        '16:35' => '<options=bold>"Abstracting too Early"</> by Matt Stauffer',
        '17:15' => self::BREAK_SLOT_NAME,
        '17:35' => '<options=bold>"Laravel Update"</> by Taylor Otwell',
        '18:35' => '<options=bold>"Database Performance for Application Developers"</> by Aaro Francis',
        '19:15' => '<options=bold>"Christoph Dreams of Simple Code"</> by Christoph Rumpel',
        '19:55' => self::BREAK_SLOT_NAME,
        '20:15' => self::LIGHTNING_TALKS_2,
        '21:45' => '<options=bold>"Browsers are Magical Creatures"</> by Stephen Rees-Carter',
    ];

    /**
     * The 1st lightning talks schedule.
     *
     * @var array<int, string>
     */
    protected array $lightningTalksOne = [
        '<options=bold>"Sustainable Self-Care"</> by Marje Holmstrom-Sabo',
        '<options=bold>"Let\'s Get Physical: Database Internals and You"</> by Tim Martin',
        '<options=bold>"Deep Dive into Carbon"</> by Ralph J. Smit',
        '<options=bold>"UI and Component testing with Cypress"</> by Marcel Pociot',
        '<options=bold>"The Hitchhiker\'s Guide to the Laravel Community"</> by Caneco',
    ];

    /**
     * The 2nd lightning talks schedule.
     *
     * @var array<int, string>
     */
    protected array $lightningTalksTwo = [
        '<options=bold>"Is there any problem Git interactive rebase can\'t solve?"</> by Rissa Jackson',
        '<options=bold>"Meaningful Mentorship"</> by Alex Six',
        '<options=bold>"I shall say... err define this only once"</> by Freek Van der Herten',
        '<options=bold>"I can\'t believe it\'s not local!"</> by Chris Fidao',
        '<options=bold>"Valid Variants of Validating Validation"</> by Luke Downing',
        '<options=bold>"A Grab Bag of Useful Tips"</> by Colin DeCarlo',
    ];

    /**
     * Community.
     *
     * @var array<string, string>
     */
    protected array $community = [
        'Telegram' => 'https://t.me/+J0X0cCt2z8c5YTEy',
        'Discord' => 'https://discord.com/invite/mPZNm7A',
    ];

    public function handle(): void
    {
        $userTimeZone = $this->getTimeZone();
        $late = (int) $this->option('late');

        $this->line('');
        $this->line(self::INDENT.'<options=bold,reverse;bg=white;fg=bright-red> '.self::TITLE.' </>');
        $this->line('');

        $this->line(self::INDENT.'Your timezone: '.$userTimeZone.'.');
        if ($late != 0) {
            $this->line(self::INDENT.'Laracon is running '.$late.' minutes late.');
        }

        $startsAt = self::DATE.' '.self::STARTS_AT_TIME;
        $endsAt = self::DATE.' '.self::ENDS_AT_TIME;

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
            $this->line(self::INDENT."Event status : Starts in $hoursLeft hours.");
        } elseif ($minutesLeft < 0) {
            $minutesLeft = abs($minutesLeft);
            $this->line(self::INDENT."Event status : Starts in $minutesLeft minutes.");
        } elseif (Carbon::parse($endsAt, self::TIMEZONE)->setTimezone($userTimeZone)->isPast()) {
            $this->line(self::INDENT.'Event status : Event has ended. See you next time!');
        } else {
            $this->line(self::INDENT.'Event status : Already started.');
        }

        $showedHappeningNowOnce = false;

        $this->line('');
        collect($this->scheduling)->each(function ($talk, $schedule) use (
            $userTimeZone,
            $late,
            &$showedHappeningNowOnce
        ) {
            $dateTime = Carbon::parse(self::DATE." $schedule:00", self::TIMEZONE)
                ->addMinutes($late)
                ->setTimezone($userTimeZone);

            $lineOptions = 'bold';

            if (! $showedHappeningNowOnce && $this->happeningNow($dateTime, $userTimeZone, $talk)) {
                $lineOptions = 'bold,reverse;fg=yellow';
                $showedHappeningNowOnce = true;
            }

            $this->line(self::INDENT."<options={$lineOptions}>{$dateTime->calendar()}</> - $talk");

            if ($talk === self::LIGHTNING_TALKS_1) {
                collect($this->lightningTalksOne)->each(fn ($talk) => $this->line(self::INDENT."  - {$talk}"));
            }
            if ($talk === self::LIGHTNING_TALKS_2) {
                collect($this->lightningTalksTwo)->each(fn ($talk) => $this->line(self::INDENT."  - {$talk}"));
            }
        });

        $this->line('');
        $this->line(self::INDENT.'<fg=magenta;options=bold>Join the community:</> ');
        foreach ($this->community as $platform => $link) {
            $this->line(self::INDENT."$platform: $link");
        }
        $this->line('');
    }

    /**
     * Returns the user's timezone.
     *
     * @return string
     */
    public function getTimeZone(): string
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

        return str($disk->get($filename))->trim()->value();
    }

    private function getSystemTimeZone(int|null &$exitCode): string
    {
        switch (true) {
            case Str::contains(php_uname('s'), ['Darwin', 'Linux']):
                if (file_exists('/etc/timezone')) {
                    return str(exec('cat /etc/timezone', $_, $exitCode))->trim();
                }

                if (file_exists('/etc/localtime')) {
                    return str(
                        exec('ls -l /etc/localtime', $_, $exitCode)
                    )->after('zoneinfo/')->trim();
                }

                return exec('date +%Z', $_, $exitCode);
            case Str::contains(php_uname('s'), 'Windows'):
                return str(
                    $this->getIanaTimeZoneFromWindowsIdentifier(
                        exec('tzutil /g', $_, $exitCode)
                    )
                )->trim();
            default:
                abort(401, 'Your OS is not supported at this time.');
        }
    }

    /**
     * Returns an IANA time zone string from a Microsoft Windows time zone identifier
     *  `./data/windowsZones.json` file content from windowsZones.xml
     *  https://github.com/unicode-org/cldr/blob/master/common/supplemental/windowsZones.xml
     *
     * @param  string  $timeZoneId  Windows time zone identifier (i.e. 'E. South America Standard Time')
     * @return string
     */
    private function getIanaTimeZoneFromWindowsIdentifier(string $timeZoneId): string
    {
        $timeZone = collect(
            json_decode(Storage::disk('data')->get('windowsZones.json'))
        )->firstWhere('windowsIdentifier', '=', $timeZoneId);

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
