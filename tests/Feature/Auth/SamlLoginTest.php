<?php

use App\Models\LoginProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function samlProvider(): LoginProvider
{
    return LoginProvider::create([
        'type' => 'saml',
        'name' => 'Okta',
        'slug' => 'okta',
        'is_active' => true,
        'config' => [
            'idp_entity_id' => 'https://idp.example.com/metadata',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_x509_cert' => 'not-a-real-cert',
            'attr_email' => 'email',
        ],
    ]);
}

test('metadata endpoint returns valid sp metadata xml', function () {
    $provider = samlProvider();

    $response = $this->get(route('login.saml.metadata', $provider));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    expect($response->getContent())->toContain('<md:EntityDescriptor');
    expect($response->getContent())->toContain(route('login.saml.acs', $provider));
});

test('redirect sends the browser to the idp sso url with a SAMLRequest', function () {
    $provider = samlProvider();

    $response = $this->get(route('login.saml.redirect', $provider));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://idp.example.com/sso?');
    expect($location)->toContain('SAMLRequest=');
});

test('acs rejects a request with no SAMLResponse instead of erroring', function () {
    $provider = samlProvider();

    $response = $this->post(route('login.saml.acs', $provider), []);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    expect(session('error'))->not->toBeNull();
});

test('acs rejects an invalid SAMLResponse', function () {
    $provider = samlProvider();

    $response = $this->post(route('login.saml.acs', $provider), [
        'SAMLResponse' => base64_encode('<garbage/>'),
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    expect(session('error'))->not->toBeNull();
});

test('inactive saml provider redirect is not reachable', function () {
    $provider = samlProvider();
    $provider->update(['is_active' => false]);

    $this->get(route('login.saml.redirect', $provider))->assertNotFound();
});
