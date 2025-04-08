<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ValidatorIntegrationTest extends TestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => 'http://127.0.0.1:8080',
            'timeout' => 30.0, // Aumentar o timeout para 30 segundos
            'http_errors' => false, // Evitar que o Guzzle lance exceções para erros 4xx/5xx
        ]);
    }

    public function testValidCss()
    {
        try {
            $response = $this->client->post('/validator.php', [
                'form_params' => [
                    'css' => 'body { color: blue; }',
                    'profile' => 'css3svg',
                    'lang' => 'en'
                ]
            ]);
        } catch (GuzzleException $e) {
            $this->fail('Failed to make request: ' . $e->getMessage());
        }

        $this->assertEquals(200, $response->getStatusCode(), 'Expected HTTP status 200');
        $body = json_decode($response->getBody(), true);

        $this->assertIsArray($body, 'Response body should be an array');
        $this->assertArrayHasKey('cssvalidation', $body, 'Response should contain "cssvalidation" key');
        $cssValidation = $body['cssvalidation'];

        $this->assertIsArray($cssValidation, '"cssvalidation" should be an array');
        $this->assertTrue($cssValidation['validity'], 'CSS should be valid');
        $this->assertEquals(0, $cssValidation['result']['errorcount'], 'Should have 0 errors');
        $this->assertEquals(0, $cssValidation['result']['warningcount'], 'Should have 0 warnings');
        $this->assertCount(0, $cssValidation['errors'], 'Errors array should be empty');
        $this->assertCount(0, $cssValidation['warnings'], 'Warnings array should be empty');
    }

    public function testInvalidCss()
    {
        try {
            $response = $this->client->post('/validator.php', [
                'form_params' => [
                    'css' => 'body { color: blue; } p { margin: ; }',
                    'profile' => 'css3svg',
                    'lang' => 'en'
                ]
            ]);
        } catch (GuzzleException $e) {
            $this->fail('Failed to make request: ' . $e->getMessage());
        }

        $this->assertEquals(200, $response->getStatusCode(), 'Expected HTTP status 200');
        $body = json_decode($response->getBody(), true);

        $this->assertIsArray($body, 'Response body should be an array');
        $this->assertArrayHasKey('cssvalidation', $body, 'Response should contain "cssvalidation" key');
        $cssValidation = $body['cssvalidation'];

        $this->assertIsArray($cssValidation, '"cssvalidation" should be an array');
        $this->assertFalse($cssValidation['validity'], 'CSS should be invalid');
        $this->assertEquals(1, $cssValidation['result']['errorcount'], 'Should have 1 error');
        $this->assertCount(1, $cssValidation['errors'], 'Should have 1 error in errors array');

        $error = $cssValidation['errors'][0];
        $this->assertIsArray($error, 'Error should be an array');
        $this->assertEquals('p', $error['context'], 'Error context should be "p"');
        $this->assertEquals('parse-error', $error['errortype'], 'Error type should be "parse-error"');
        $this->assertStringContainsString('Value Error :', $error['message'], 'Error message should contain "Value Error :"');
        $this->assertStringContainsString('margin', $error['message'], 'Error message should contain "margin"');
    }

    public function testInvalidProfile()
    {
        $response = $this->client->post('/validator.php', [
            'form_params' => [
                'css' => 'body { color: blue; }',
                'profile' => 'invalid_profile',
                'lang' => 'en'
            ]
        ]);

        $this->assertEquals(400, $response->getStatusCode(), 'Expected HTTP status 400');
        $body = json_decode($response->getBody(), true);

        $this->assertIsArray($body, 'Response body should be an array');
        $this->assertArrayHasKey('error', $body, 'Response should contain "error" key');
        $this->assertStringContainsString('Invalid profile', $body['error'], 'Error message should contain "Invalid profile"');
    }

    public function testInvalidRequestMethod()
    {
        $response = $this->client->get('/validator.php');

        $this->assertEquals(400, $response->getStatusCode(), 'Expected HTTP status 400');
        $body = json_decode($response->getBody(), true);

        $this->assertIsArray($body, 'Response body should be an array');
        $this->assertArrayHasKey('error', $body, 'Response should contain "error" key');
        $this->assertEquals(
            'Send CSS via POST in the "css" field with optional parameters',
            $body['error'],
            'Error message should match expected'
        );
    }
}