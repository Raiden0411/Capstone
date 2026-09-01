<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a successful health check', function () {
    $response = $this->get('/health');

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});