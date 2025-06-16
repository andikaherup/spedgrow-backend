<?php

namespace Tests\Unit;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_factory_creates_valid_transaction()
    {
        $transaction = Transaction::factory()->create();

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertNotNull($transaction->transaction_id);
        $this->assertGreaterThan(0, $transaction->amount);
        $this->assertContains($transaction->type, ['debit', 'credit']);
        $this->assertContains($transaction->status, ['pending', 'completed', 'failed']);
    }

    public function test_transaction_casts_work_correctly()
    {
        $nfcData = [
            'card_id' => 'CARD_123',
            'terminal_id' => 'TERM_456',
            'signal_strength' => -45
        ];

        $transaction = Transaction::factory()->create([
            'nfc_data' => $nfcData,
            'amount' => '99.99'
        ]);

        // Test JSON casting
        $this->assertIsArray($transaction->nfc_data);
        $this->assertEquals('CARD_123', $transaction->nfc_data['card_id']);

        // Test decimal casting
        $this->assertIsNumeric($transaction->amount);
        $this->assertEquals('99.99', $transaction->amount);


        // Test datetime casting
        $this->assertInstanceOf(\Carbon\Carbon::class, $transaction->transaction_date);
    }

    public function test_by_date_range_scope()
    {
        // Create transactions with different dates
        $oldTransaction = Transaction::factory()->create([
            'transaction_date' => now()->subDays(10)
        ]);

        $recentTransaction = Transaction::factory()->create([
            'transaction_date' => now()->subDays(2)
        ]);

        $startDate = now()->subDays(5);
        $endDate = now();

        $results = Transaction::byDateRange($startDate, $endDate)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($recentTransaction->id, $results->first()->id);
    }

    public function test_by_type_scope()
    {
        Transaction::factory()->create(['type' => 'credit']);
        Transaction::factory()->create(['type' => 'debit']);
        Transaction::factory()->create(['type' => 'debit']);

        $creditTransactions = Transaction::byType('credit')->get();
        $debitTransactions = Transaction::byType('debit')->get();

        $this->assertCount(1, $creditTransactions);
        $this->assertCount(2, $debitTransactions);
    }

    public function test_by_status_scope()
    {
        Transaction::factory()->create(['status' => 'pending']);
        Transaction::factory()->create(['status' => 'completed']);
        Transaction::factory()->create(['status' => 'completed']);

        $pendingTransactions = Transaction::byStatus('pending')->get();
        $completedTransactions = Transaction::byStatus('completed')->get();

        $this->assertCount(1, $pendingTransactions);
        $this->assertCount(2, $completedTransactions);
    }

    public function test_with_nfc_scope()
    {
        Transaction::factory()->create(['nfc_data' => null]);
        Transaction::factory()->create([
            'nfc_data' => ['card_id' => 'CARD_123']
        ]);

        $nfcTransactions = Transaction::withNfc()->get();

        $this->assertCount(1, $nfcTransactions);
        $this->assertNotNull($nfcTransactions->first()->nfc_data);
    }

    public function test_search_scope()
    {
        Transaction::factory()->create(['merchant_name' => 'Coffee Shop']);
        Transaction::factory()->create(['merchant_name' => 'Gas Station']);
        Transaction::factory()->create(['category' => 'coffee']);

        $results = Transaction::search('coffee')->get();

        $this->assertCount(2, $results);
    }

    public function test_fillable_attributes()
    {
        $data = [
            'transaction_id' => 'TXN_TEST_123',
            'amount' => 99.99,
            'currency' => 'USD',
            'type' => 'debit',
            'status' => 'completed',
            'merchant_name' => 'Test Merchant',
            'category' => 'test',
            'nfc_data' => ['card_id' => 'CARD_123'],
            'transaction_date' => now()
        ];

        $transaction = Transaction::create($data);

        $this->assertEquals('TXN_TEST_123', $transaction->transaction_id);
        $this->assertEquals(99.99, $transaction->amount);
        $this->assertEquals('Test Merchant', $transaction->merchant_name);
    }
}