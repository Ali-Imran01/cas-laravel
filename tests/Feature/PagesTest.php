<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the dashboard page', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->has('kpis'));
});

it('renders the login page', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Login'));
});
