<?php

namespace WpOrg\Requests\Tests\Cookie;

use WpOrg\Requests\Cookie;
use WpOrg\Requests\Iri;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Tests\TestCase;

final class CookieTest extends TestCase {
	public function testBasicCookie() {
		$cookie = new Cookie('requests-testcookie', 'testvalue');

		$this->assertSame('requests-testcookie', $cookie->name);
		$this->assertSame('testvalue', $cookie->value);
		$this->assertSame('testvalue', (string) $cookie);

		$this->assertSame('requests-testcookie=testvalue', $cookie->format_for_header());
		$this->assertSame('requests-testcookie=testvalue', $cookie->format_for_set_cookie());
	}

	public function testCookieWithAttributes() {
		$attributes = [
			'httponly',
			'path' => '/',
		];
		$cookie     = new Cookie('requests-testcookie', 'testvalue', $attributes);

		$this->assertSame('requests-testcookie=testvalue', $cookie->format_for_header());
		$this->assertSame('requests-testcookie=testvalue; httponly; path=/', $cookie->format_for_set_cookie());
	}

	public function testEmptyCookieName() {
		$cookie = Cookie::parse('test');
		$this->assertSame('', $cookie->name);
		$this->assertSame('test', $cookie->value);
	}

	public function testEmptyAttributes() {
		$cookie = Cookie::parse('foo=bar; HttpOnly');
		$this->assertTrue($cookie->attributes['httponly']);
	}

	public function testReceivingCookies() {
		$options = [
			'follow_redirects' => false,
		];
		$url     = httpbin('/cookies/set?requests-testcookie=testvalue');

		$response = Requests::get($url, [], $options);

		$cookie = $response->cookies['requests-testcookie'];
		$this->assertNotEmpty($cookie);
		$this->assertSame('testvalue', $cookie->value);
	}

	public function testPersistenceOnRedirect() {
		$options = [
			'follow_redirects' => true,
		];
		$url     = httpbin('/cookies/set?requests-testcookie=testvalue');

		$response = Requests::get($url, [], $options);

		$cookie = $response->cookies['requests-testcookie'];
		$this->assertNotEmpty($cookie);
		$this->assertSame('testvalue', $cookie->value);
	}

	private function setCookieRequest($cookies) {
		$options  = [
			'cookies' => $cookies,
		];
		$response = Requests::get(httpbin('/cookies/set'), [], $options);

		$data = json_decode($response->body, true);
		$this->assertIsArray($data);
		$this->assertArrayHasKey('cookies', $data);
		return $data['cookies'];
	}

	public function testSendingCookie() {
		$cookies = [
			'requests-testcookie1' => 'testvalue1',
		];

		$data = $this->setCookieRequest($cookies);

		$this->assertArrayHasKey('requests-testcookie1', $data);
		$this->assertSame('testvalue1', $data['requests-testcookie1']);
	}

	/**
	 * @depends testSendingCookie
	 */
	public function testCookieExpiration() {
		$options = [
			'follow_redirects' => true,
		];
		$url     = httpbin('/cookies/set/testcookie/testvalue');
		$url    .= '?expiry=1';

		$response = Requests::get($url, [], $options);
		$response->throw_for_status();

		$data = json_decode($response->body, true);
		$this->assertEmpty($data['cookies']);
	}

	public function testSendingMultipleCookies() {
		$cookies = [
			'requests-testcookie1' => 'testvalue1',
			'requests-testcookie2' => 'testvalue2',
		];
		$data    = $this->setCookieRequest($cookies);

		$this->assertArrayHasKey('requests-testcookie1', $data);
		$this->assertSame('testvalue1', $data['requests-testcookie1']);

		$this->assertArrayHasKey('requests-testcookie2', $data);
		$this->assertSame('testvalue2', $data['requests-testcookie2']);
	}

	/**
	 * Manually set cookies without a domain/path set should always be valid
	 *
	 * Cookies parsed from headers internally in Requests will always have a
	 * domain/path set, but those created manually will not. Manual cookies
	 * should be regarded as "global" cookies (that is, set for `.`)
	 */
	public function testUrlMatchManuallySet() {
		$cookie = new Cookie('requests-testcookie', 'testvalue');
		$this->assertTrue($cookie->domain_matches('example.com'));
		$this->assertTrue($cookie->domain_matches('example.net'));
		$this->assertTrue($cookie->path_matches('/'));
		$this->assertTrue($cookie->path_matches('/test'));
		$this->assertTrue($cookie->path_matches('/test/'));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.com/')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.com/test')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.com/test/')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.net/')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.net/test')));
		$this->assertTrue($cookie->uri_matches(new Iri('http://example.net/test/')));
	}
}
