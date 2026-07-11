<?php

test('the home page redirects to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
