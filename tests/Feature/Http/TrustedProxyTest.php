<?php

it('builds https urls when the proxy in front says the request came in over https', function () {
    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'carms.bfcgroup.ph', 'X-Forwarded-Port' => '443'])
        ->get('/login')
        ->assertOk()
        ->assertSee('https://carms.bfcgroup.ph/livewire', false)
        ->assertDontSee('http://carms.bfcgroup.ph/livewire', false);
});
