<?php
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html><html><head>
          <meta charset='UTF-8'>
          <title>KMeans normalizeData Tests</title>
          <style>
              body {
                  background: #0f172a;
                  color: #e5e7eb;
                  font-family: monospace;
              }
              .pass { color: #22c55e; }
              .fail { color: #ef4444; }
          </style>
          </head><body><pre>";
}

/**
 * Unit Tests for KMeansClustering::normalizeData()
 * =================================================
 * Run with: php test_normalize_data.php
 */

// Suppress database connection output when including run_clustering.php
ob_start();
require_once 'run_clustering.php';
ob_end_clean();

// ============================================================================
// Test Suite
// ============================================================================

class NormalizeDataTest {
    
    private $kmeans;
    private $passedTests = 0;
    private $failedTests = 0;
    private $currentTestName = '';
    
    public function __construct() {
        $this->kmeans = new KMeansClustering();
    }
    
    /**
     * Helper function to assert equality with tolerance for floating point
     */
    private function assertAlmostEqual($expected, $actual, $tolerance = 0.0001, $message = '') {
        if (abs($expected - $actual) <= $tolerance) {
            $this->passedTests++;
            $this->printPass($message);
            return true;
        } else {
            $this->failedTests++;
            $this->printFail($message, "Expected: $expected, Got: $actual");
            return false;
        }
    }
    
    private function assertEqual($expected, $actual, $message = '') {
        if ($expected === $actual) {
            $this->passedTests++;
            $this->printPass($message);
            return true;
        } else {
            $this->failedTests++;
            $this->printFail($message, "Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true));
            return false;
        }
    }
    
    private function printPass($message) {
        echo "  ✓ PASS: $message\n";
    }
    
    private function printFail($message, $details = '') {
        echo "  ✗ FAIL: $message\n";
        if ($details) {
            echo "    → $details\n";
        }
    }
    
    private function printTestHeader($testName) {
        $this->currentTestName = $testName;
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "$testName\n";
        echo str_repeat("=", 70) . "\n";
    }
    
    /**
     * Test 1: Normal data with typical values
     */
    public function testNormalData() {
        $this->printTestHeader("TEST 1: Normal Data");
        
        $data = [
            ['customer_id' => 1, 'age' => 25, 'income' => 50000, 'purchase_amount' => 2000],
            ['customer_id' => 2, 'age' => 35, 'income' => 60000, 'purchase_amount' => 2500],
            ['customer_id' => 3, 'age' => 45, 'income' => 70000, 'purchase_amount' => 3000],
            ['customer_id' => 4, 'age' => 55, 'income' => 80000, 'purchase_amount' => 3500],
            ['customer_id' => 5, 'age' => 65, 'income' => 90000, 'purchase_amount' => 4000],
        ];
        
        $normalized = $this->kmeans->normalizeData($data);
        
        // Verify count
        $this->assertEqual(count($data), count($normalized), "Should preserve number of records");
        
        // Verify customer_id is preserved
        for ($i = 0; $i < count($data); $i++) {
            $this->assertEqual(
                $data[$i]['customer_id'], 
                $normalized[$i]['customer_id'], 
                "Customer ID should be preserved for record $i"
            );
        }
        
        // Calculate expected mean (should be close to 0 for each feature)
        $ageSum = 0;
        $incomeSum = 0;
        $purchaseSum = 0;
        
        foreach ($normalized as $point) {
            $ageSum += $point['age'];
            $incomeSum += $point['income'];
            $purchaseSum += $point['purchase_amount'];
        }
        
        $ageMean = $ageSum / count($normalized);
        $incomeMean = $incomeSum / count($normalized);
        $purchaseMean = $purchaseSum / count($normalized);
        
        $this->assertAlmostEqual(0, $ageMean, 0.0001, "Normalized age mean should be ~0");
        $this->assertAlmostEqual(0, $incomeMean, 0.0001, "Normalized income mean should be ~0");
        $this->assertAlmostEqual(0, $purchaseMean, 0.0001, "Normalized purchase_amount mean should be ~0");
        
        // Calculate standard deviation (should be close to 1)
        $ageVariance = 0;
        $incomeVariance = 0;
        $purchaseVariance = 0;
        
        foreach ($normalized as $point) {
            $ageVariance += pow($point['age'] - $ageMean, 2);
            $incomeVariance += pow($point['income'] - $incomeMean, 2);
            $purchaseVariance += pow($point['purchase_amount'] - $purchaseMean, 2);
        }
        
        $ageStd = sqrt($ageVariance / count($normalized));
        $incomeStd = sqrt($incomeVariance / count($normalized));
        $purchaseStd = sqrt($purchaseVariance / count($normalized));
        
        $this->assertAlmostEqual(1, $ageStd, 0.01, "Normalized age std dev should be ~1");
        $this->assertAlmostEqual(1, $incomeStd, 0.01, "Normalized income std dev should be ~1");
        $this->assertAlmostEqual(1, $purchaseStd, 0.01, "Normalized purchase_amount std dev should be ~1");
    }
    
    /**
     * Test 2: Zero standard deviation (all values the same)
     */
    public function testZeroStandardDeviation() {
        $this->printTestHeader("TEST 2: Zero Standard Deviation (Identical Values)");
        
        $data = [
            ['customer_id' => 1, 'age' => 30, 'income' => 50000, 'purchase_amount' => 2000],
            ['customer_id' => 2, 'age' => 30, 'income' => 50000, 'purchase_amount' => 2000],
            ['customer_id' => 3, 'age' => 30, 'income' => 50000, 'purchase_amount' => 2000],
        ];
        
        $normalized = $this->kmeans->normalizeData($data);
        
        // When std dev is 0, normalized values should all be 0
        foreach ($normalized as $i => $point) {
            $this->assertAlmostEqual(0, $point['age'], 0.0001, "Age should be 0 when all values identical (record $i)");
            $this->assertAlmostEqual(0, $point['income'], 0.0001, "Income should be 0 when all values identical (record $i)");
            $this->assertAlmostEqual(0, $point['purchase_amount'], 0.0001, "Purchase should be 0 when all values identical (record $i)");
        }
        
        // Test partial zero std dev (only one feature constant)
        $data = [
            ['customer_id' => 1, 'age' => 30, 'income' => 40000, 'purchase_amount' => 2000],
            ['customer_id' => 2, 'age' => 30, 'income' => 50000, 'purchase_amount' => 2000],
            ['customer_id' => 3, 'age' => 30, 'income' => 60000, 'purchase_amount' => 2000],
        ];
        
        $normalized = $this->kmeans->normalizeData($data);
        
        // Age should be 0 (constant), income should vary, purchase should be 0
        foreach ($normalized as $i => $point) {
            $this->assertAlmostEqual(0, $point['age'], 0.0001, "Age should be 0 (constant feature, record $i)");
            $this->assertAlmostEqual(0, $point['purchase_amount'], 0.0001, "Purchase should be 0 (constant feature, record $i)");
        }
        
        // Income should have variation
        $incomeValues = array_column($normalized, 'income');
        $hasVariation = (max($incomeValues) - min($incomeValues)) > 0.01;
        $this->assertEqual(true, $hasVariation, "Income should have variation when not constant");
    }
    
    /**
     * Test 3: Negative values
     */
    public function testNegativeValues() {
        $this->printTestHeader("TEST 3: Negative Values");
        
        $data = [
            ['customer_id' => 1, 'age' => -5, 'income' => -10000, 'purchase_amount' => -500],
            ['customer_id' => 2, 'age' => 25, 'income' => 50000, 'purchase_amount' => 2000],
            ['customer_id' => 3, 'age' => 55, 'income' => 110000, 'purchase_amount' => 4500],
        ];
        
        $normalized = $this->kmeans->normalizeData($data);
        
        // Should not throw errors
        $this->assertEqual(3, count($normalized), "Should handle negative values without errors");
        
        // Verify normalization still produces valid output
        foreach ($normalized as $point) {
            $this->assertEqual(true, is_numeric($point['age']), "Age should be numeric after normalization");
            $this->assertEqual(true, is_numeric($point['income']), "Income should be numeric after normalization");
            $this->assertEqual(true, is_numeric($point['purchase_amount']), "Purchase should be numeric after normalization");
            $this->assertEqual(true, is_finite($point['age']), "Age should be finite");
            $this->assertEqual(true, is_finite($point['income']), "Income should be finite");
            $this->assertEqual(true, is_finite($point['purchase_amount']), "Purchase should be finite");
        }
        
        // Mean should still be close to 0
        $ageSum = array_sum(array_column($normalized, 'age'));
        $ageMean = $ageSum / count($normalized);
        $this->assertAlmostEqual(0, $ageMean, 0.0001, "Mean should be ~0 even with negative values");
    }
    
    /**
     * Test 4: Empty array
     */
    public function testEmptyArray() {
        $this->printTestHeader("TEST 4: Empty Array");
        
        $data = [];
        
        // Should now work without throwing errors
        $normalized = $this->kmeans->normalizeData($data);
        $this->assertEqual(0, count($normalized), "Empty input should return empty array");
    }
    
    /**
     * Test 5: Single record
     */
    public function testSingleRecord() {
        $this->printTestHeader("TEST 5: Single Record");
        
        $data = [
            ['customer_id' => 1, 'age' => 30, 'income' => 50000, 'purchase_amount' => 2000],
        ];
        
        $normalized = $this->kmeans->normalizeData($data);
        
        $this->assertEqual(1, count($normalized), "Should preserve single record");
        
        // With single record, std dev is 0, so all normalized values should be 0
        $this->assertAlmostEqual(0, $normalized[0]['age'], 0.0001, "Single record age should normalize to 0");
        $this->assertAlmostEqual(0, $normalized[0]['income'], 0.0001, "Single record income should normalize to 0");
        $this->assertAlmostEqual(0, $normalized[0]['purchase_amount'], 0.0001, "Single record purchase should normalize to 0");
    }
    
    /**
     * Test 6: Large values (boundary testing)
     */
    public function testLargeValues() {
        $this->printTestHeader("TEST 6: Large Values");
        
        $data = [
            ['customer_id' => 1, 'age' => 100, 'income' => 1000000, 'purchase_amount' => 100000],
            ['customer_id' => 2, 'age' => 200, 'income' => 2000000, 'purchase_amount' => 200000],
            ['customer_id' => 3, 'age' => 300, 'income' => 3000000, 'purchase_amount' => 300000],
        ];
        
        $normalized = $this->kmeans->normalizeData($data);
        
        // Should handle large values without overflow
        foreach ($normalized as $point) {
            $this->assertEqual(true, is_finite($point['age']), "Large age should not overflow");
            $this->assertEqual(true, is_finite($point['income']), "Large income should not overflow");
            $this->assertEqual(true, is_finite($point['purchase_amount']), "Large purchase should not overflow");
        }
        
        // Mean should still be close to 0
        $ageMean = array_sum(array_column($normalized, 'age')) / count($normalized);
        $this->assertAlmostEqual(0, $ageMean, 0.0001, "Mean should be ~0 for large values");
    }
    
    /**
     * Test 7: Mixed precision (integers and floats)
     */
    public function testMixedPrecision() {
        $this->printTestHeader("TEST 7: Mixed Precision (Integers and Floats)");
        
        $data = [
            ['customer_id' => 1, 'age' => 25.5, 'income' => 50000.75, 'purchase_amount' => 2000.25],
            ['customer_id' => 2, 'age' => 35, 'income' => 60000, 'purchase_amount' => 2500],
            ['customer_id' => 3, 'age' => 45.8, 'income' => 70000.50, 'purchase_amount' => 3000.99],
        ];
        
        $normalized = $this->kmeans->normalizeData($data);
        
        $this->assertEqual(3, count($normalized), "Should handle mixed int/float data");
        
        // All outputs should be numeric
        foreach ($normalized as $point) {
            $this->assertEqual(true, is_numeric($point['age']), "Should handle float ages");
            $this->assertEqual(true, is_numeric($point['income']), "Should handle float incomes");
            $this->assertEqual(true, is_numeric($point['purchase_amount']), "Should handle float purchases");
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "K-MEANS CLUSTERING: normalizeData() Test Suite\n";
        echo str_repeat("=", 70) . "\n";
        
        $this->testNormalData();
        $this->testZeroStandardDeviation();
        $this->testNegativeValues();
        $this->testEmptyArray();
        $this->testSingleRecord();
        $this->testLargeValues();
        $this->testMixedPrecision();
        
        // Summary
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        echo "✓ Passed: {$this->passedTests}\n";
        echo "✗ Failed: {$this->failedTests}\n";
        echo "Total: " . ($this->passedTests + $this->failedTests) . "\n";
        echo str_repeat("=", 70) . "\n";
        
        if ($this->failedTests === 0) {
            echo "🎉 ALL TESTS PASSED!\n";
        } else {
            echo "⚠️  SOME TESTS FAILED\n";
        }
        echo "\n";
        
        return $this->failedTests === 0;
    }
}

// Run tests
$tester = new NormalizeDataTest();
$allPassed = $tester->runAllTests();

// Exit with appropriate code
exit($allPassed ? 0 : 1);
?>