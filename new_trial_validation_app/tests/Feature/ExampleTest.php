<?php

test('redirects guests straight to the login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
