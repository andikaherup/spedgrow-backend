<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_list_transactions()
    {
        // Create test transactions
        Transaction::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'transaction_id',
                            'amount',
                            'currency',
                            'type',
                            'status',
                            'merchant_name',
                            'category',
                            'nfc_data',
                            'transaction_date',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]);
    }

    public function test_can_create_transaction()
    {
        $transactionData = [
            'amount' => 99.99,
            'currency' => 'USD',
            'type' => 'debit',
            'status' => 'completed',
            'merchant_name' => 'Test Merchant',
            'category' => 'food',
            'transaction_date' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'id',
                    'transaction_id',
                    'amount',
                    'currency',
                    'type',
                    'status',
                    'merchant_name',
                    'category',
                    'transaction_date',
                    'created_at',
                    'updated_at'
                ]);

        $this->assertDatabaseHas('transactions', [
            'amount' => 99.99,
            'merchant_name' => 'Test Merchant',
            'type' => 'debit',
            'status' => 'completed'
        ]);
    }

    public function test_can_create_nfc_transaction()
    {
        $nfcTransactionData = [
            'amount' => 149.99,
            'currency' => 'USD',
            'type' => 'debit',
            'status' => 'completed',
            'merchant_name' => 'NFC Test Store',
            'category' => 'shopping',
            'nfc_data' => [
                'card_id' => 'CARD_123456789',
                'terminal_id' => 'TERM_987654',
                'signal_strength' => -45
            ],
            'transaction_date' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/transactions', $nfcTransactionData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('transactions', [
            'amount' => 149.99,
            'merchant_name' => 'NFC Test Store'
        ]);

        // Check NFC data is stored correctly
        $transaction = Transaction::where('merchant_name', 'NFC Test Store')->first();
        $this->assertNotNull($transaction->nfc_data);
        $this->assertEquals('CARD_123456789', $transaction->nfc_data['card_id']);
    }

    public function test_can_get_single_transaction()
    {
        $transaction = Transaction::factory()->create([
            'merchant_name' => 'Single Test Merchant'
        ]);

        $response = $this->getJson("/api/v1/transactions/{$transaction->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'id' => $transaction->id,
                    'merchant_name' => 'Single Test Merchant'
                ]);
    }

    public function test_can_filter_transactions_by_type()
    {
        Transaction::factory()->create(['type' => 'credit']);
        Transaction::factory()->create(['type' => 'debit']);

        $response = $this->getJson('/api/v1/transactions?type=credit');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['total']);
        $this->assertEquals('credit', $data['data'][0]['type']);
    }

    public function test_can_filter_transactions_by_status()
    {
        Transaction::factory()->create(['status' => 'pending']);
        Transaction::factory()->create(['status' => 'completed']);
        Transaction::factory()->create(['status' => 'completed']);

        $response = $this->getJson('/api/v1/transactions?status=completed');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(2, $data['total']);
        foreach ($data['data'] as $transaction) {
            $this->assertEquals('completed', $transaction['status']);
        }
    }

    public function test_can_filter_nfc_only_transactions()
    {
        // Create regular transaction
        Transaction::factory()->create(['nfc_data' => null]);

        // Create NFC transaction
        Transaction::factory()->create([
            'nfc_data' => [
                'card_id' => 'CARD_123',
                'terminal_id' => 'TERM_456'
            ]
        ]);

        $response = $this->getJson('/api/v1/transactions?nfc_only=true');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['total']);
        $this->assertNotNull($data['data'][0]['nfc_data']);
    }

    public function test_can_search_transactions()
    {
        Transaction::factory()->create(['merchant_name' => 'Coffee Shop']);
        Transaction::factory()->create(['merchant_name' => 'Gas Station']);

        $response = $this->getJson('/api/v1/transactions?search=coffee');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['total']);
        $this->assertStringContainsString('Coffee', $data['data'][0]['merchant_name']);
    }

    public function test_can_get_transaction_summary()
    {
        // Create test transactions
        Transaction::factory()->create([
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'completed',
            'transaction_date' => now()
        ]);

        Transaction::factory()->create([
            'type' => 'debit',
            'amount' => 50.00,
            'status' => 'pending',
            'transaction_date' => now()
        ]);

        Transaction::factory()->create([
            'type' => 'debit',
            'amount' => 25.00,
            'status' => 'completed',
            'nfc_data' => ['card_id' => 'CARD_123'],
            'transaction_date' => now()
        ]);

        $response = $this->getJson('/api/v1/transactions/stats/summary');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_transactions',
                    'total_amount',
                    'credit_amount',
                    'debit_amount',
                    'nfc_transactions',
                    'pending_transactions',
                    'completed_transactions',
                    'failed_transactions'
                ]);

        $summary = $response->json();
        $this->assertEquals(3, $summary['total_transactions']);
        $this->assertEquals(100.00, $summary['credit_amount']);
        $this->assertEquals(75.00, $summary['debit_amount']);
        $this->assertEquals(1, $summary['nfc_transactions']);
        $this->assertEquals(1, $summary['pending_transactions']);
        $this->assertEquals(2, $summary['completed_transactions']);
    }

    public function test_can_get_recent_nfc_transactions()
    {
        // Create regular transactions
        Transaction::factory()->count(3)->create(['nfc_data' => null]);

        // Create NFC transactions
        Transaction::factory()->count(5)->create([
            'nfc_data' => [
                'card_id' => 'CARD_123',
                'terminal_id' => 'TERM_456'
            ]
        ]);

        $response = $this->getJson('/api/v1/transactions/nfc/recent');

        $response->assertStatus(200);
        $data = $response->json();

        // Should return only NFC transactions (max 10)
        $this->assertCount(5, $data);
        foreach ($data as $transaction) {
            $this->assertNotNull($transaction['nfc_data']);
        }
    }

    public function test_pagination_works_correctly()
    {
        Transaction::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/transactions?per_page=10&page=1');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['current_page']);
        $this->assertEquals(3, $data['last_page']);
        $this->assertEquals(10, $data['per_page']);
        $this->assertEquals(25, $data['total']);
        $this->assertCount(10, $data['data']);
    }

    public function test_validates_required_fields()
    {
        $invalidData = [
            // Missing required fields
            'currency' => 'USD'
        ];

        $response = $this->postJson('/api/v1/transactions', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount', 'type', 'status', 'transaction_date']);
    }

    public function test_validates_amount_constraints()
    {
        $invalidData = [
            'amount' => -10, // Negative amount
            'currency' => 'USD',
            'type' => 'debit',
            'status' => 'completed',
            'transaction_date' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/transactions', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    public function test_validates_currency_format()
    {
        $invalidData = [
            'amount' => 99.99,
            'currency' => 'INVALID', // Should be 3 characters
            'type' => 'debit',
            'status' => 'completed',
            'transaction_date' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/transactions', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['currency']);
    }

    public function test_validates_enum_fields()
    {
        $invalidData = [
            'amount' => 99.99,
            'currency' => 'USD',
            'type' => 'invalid_type',
            'status' => 'invalid_status',
            'transaction_date' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/transactions', $invalidData);

        $response->assertStatus(422);
        $responseData = $response->json();
        dump($response->status());
        dump($response->json());
        $this->assertTrue(
            isset($responseData['message']) || isset($responseData['errors']),
            'Response should contain validation errors'
            );
    }

    public function test_handles_nonexistent_transaction()
    {
        $response = $this->getJson('/api/v1/transactions/99999');

        $response->assertStatus(404);
    }

    public function test_handles_date_range_filtering()
    {
        // Create transactions with different dates
        Transaction::factory()->create([
            'transaction_date' => now()->subDays(10)
        ]);

        Transaction::factory()->create([
            'transaction_date' => now()->subDays(5)
        ]);

        Transaction::factory()->create([
            'transaction_date' => now()->subDays(1)
        ]);

        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson("/api/v1/transactions?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $data = $response->json();

        // Should return 2 transactions (within the last 7 days)
        $this->assertEquals(2, $data['total']);
    }
}