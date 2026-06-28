<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '+7 900 000 00 00',
        'role' => 'shipper',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree_to_terms' => '1',
        'agree_to_privacy' => '1',
        'agree_to_platform_role' => '1',
    ]);

    $this->assertAuthenticated();
    $this->assertNotNull(auth()->user()->terms_accepted_at);
    $this->assertNotNull(auth()->user()->privacy_accepted_at);
    $this->assertNotNull(auth()->user()->platform_role_accepted_at);
    $response->assertRedirect(route('freight.company.edit', absolute: false));
});
