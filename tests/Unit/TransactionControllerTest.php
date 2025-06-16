<?php

namespace Tests\Unit;

use App\Http\Controllers\TransactionController;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TransactionController();
    }

    public function test_index_returns_paginated_results()
    {
        Transaction::factory()->count(25)->create();

        $request = Request::create('/api/v1/transactions', 'GET', ['per_page' => 10]);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('current_page', $data);
        $this->assertEquals(10, count($data['data']));
    }

    public function test_store_creates_transaction_with_generated_id()
    {
        $requestData = [
            'amount' => 99.99,
            'currency' => 'USD',
            'type' => 'debit',
            'status' => 'completed',
            'merchant_name' => 'Test Store',
            'transaction_date' => now()->toISOString()
        ];

        $request = Request::create('/api/v1/transactions', 'POST', $requestData);
        $response = $this->controller->store($request);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertStringStartsWith('TXN_', $data['transaction_id']);
        $this->assertEquals(99.99, $data['amount']);
    }

    public function test_show_returns_single_transaction()
    {
        $transaction = Transaction::factory()->create();

        $response = $this->controller->show($transaction);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($transaction->id, $data['id']);
    }

    public function test_summary_calculates_correctly()
    {
        // Create test data
        Transaction::factory()->create([
            'type' => 'credit',
            'amount' => 100,
            'status' => 'completed',
             'transaction_date' => now()
        ]);

        Transaction::factory()->create([
            'type' => 'debit',
            'amount' => 50,
            'status' => 'pending',
             'transaction_date' => now()
        ]);

        $request = Request::create('/api/v1/transactions/stats/summary', 'GET');
        $response = $this->controller->summary($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(2, $data['total_transactions']);
        $this->assertEquals(100.0, $data['credit_amount']);
        $this->assertEquals(50.0, $data['debit_amount']);
    }

    public function test_recent_nfc_transactions_filters_correctly()
    {
        // Create NFC transaction
        Transaction::factory()->create([
            'nfc_data' => ['card_id' => 'CARD_123']
        ]);

        // Create regular transaction
        Transaction::factory()->create(['nfc_data' => null]);

        $response = $this->controller->recentNfcTransactions();

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertNotNull($data[0]['nfc_data']);
    }
}