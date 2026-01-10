<?php

test('example', function () {
    $response = $this->post('/auth/api/login', ["email" => "test@user.co", "password" => "12345678"]);

    $response->assertStatus(200);
});
