<?php

test('scheduling command works', function () {
    $this->artisan('scheduling')
         ->expectsOutputToContain('LARACON ONLINE')
         ->expectsOutputToContain('Your timezone')
         ->assertExitCode(0);
});
