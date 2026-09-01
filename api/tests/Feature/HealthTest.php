<?php

it('GET /up responde 200', function () {
    $this->get('/up')->assertOk();
});

it('GET /api/ping devolve o nome do produto', function () {
    $this->getJson('/api/ping')
        ->assertOk()
        ->assertJsonPath('ok', true);
});
