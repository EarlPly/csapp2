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
 * Unit Tests for KMeansClustering::euclideanDistance()
 * =====================================================
 * Run with: php test_euclidean_distance.php
 * 
 * Note: Since euclideanDistance() is private, we use ReflectionClass
 * to access it for testing purposes.
 */

// Suppress database connection output when including run_clustering.php
ob_start();
require_once 'run_clustering.php';
ob_end_clean();

// ============================================================================
// Test Suite
// ============================================================================

class EuclideanDistanceTest {
    
    private $kmeans;
    private $reflectionMethod;
    private $passedTests = 0;
    private $failedTests = 0;
    
    public function __construct() {
        $this->kmeans = new KMeansClustering();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass('KMeansClustering');
        $this->reflectionMethod = $reflection->getMethod('euclideanDistance');
        $this->reflectionMethod->setAccessible(true);
    }
    
    /**
     * Helper to call private euclideanDistance method
     */
    private function callEuclideanDistance($point1, $point2) {
        return $this->reflectionMethod->invoke($this->kmeans, $point1, $point2);
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
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "$testName\n";
        echo str_repeat("=", 70) . "\n";
    }
    
    /**
     * Test 1: Identical points (distance should be 0)
     */
    public function testIdenticalPoints() {
        $this->printTestHeader("TEST 1: Identical Points (Distance = 0)");
        
        $point1 = ['age' => 30, 'income' => 50000, 'purchase_amount' => 2000];
        $point2 = ['age' => 30, 'income' => 50000, 'purchase_amount' => 2000];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        
        $this->assertAlmostEqual(0, $distance, 0.0001, "Distance between identical points should be 0");
    }
    
    /**
     * Test 2: Points differing in one dimension only
     */
    public function testOneDimensionDifference() {
        $this->printTestHeader("TEST 2: One Dimension Difference");
        
        // Age difference only
        $point1 = ['age' => 30, 'income' => 50000, 'purchase_amount' => 2000];
        $point2 = ['age' => 40, 'income' => 50000, 'purchase_amount' => 2000];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        $expected = 10; // sqrt((40-30)^2) = 10
        
        $this->assertAlmostEqual($expected, $distance, 0.0001, "Distance with age difference only should be 10");
        
        // Income difference only
        $point1 = ['age' => 30, 'income' => 50000, 'purchase_amount' => 2000];
        $point2 = ['age' => 30, 'income' => 60000, 'purchase_amount' => 2000];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        $expected = 10000; // sqrt((60000-50000)^2) = 10000
        
        $this->assertAlmostEqual($expected, $distance, 0.0001, "Distance with income difference only should be 10000");
        
        // Purchase amount difference only
        $point1 = ['age' => 30, 'income' => 50000, 'purchase_amount' => 2000];
        $point2 = ['age' => 30, 'income' => 50000, 'purchase_amount' => 3000];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        $expected = 1000; // sqrt((3000-2000)^2) = 1000
        
        $this->assertAlmostEqual($expected, $distance, 0.0001, "Distance with purchase difference only should be 1000");
    }
    
    /**
     * Test 3: Standard 3D Euclidean distance calculation
     */
    public function testStandard3DDistance() {
        $this->printTestHeader("TEST 3: Standard 3D Euclidean Distance");
        
        $point1 = ['age' => 0, 'income' => 0, 'purchase_amount' => 0];
        $point2 = ['age' => 3, 'income' => 4, 'purchase_amount' => 0];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        $expected = 5; // sqrt(3^2 + 4^2 + 0^2) = sqrt(25) = 5
        
        $this->assertAlmostEqual($expected, $distance, 0.0001, "3-4-5 triangle should have distance 5");
        
        // Another test case
        $point1 = ['age' => 1, 'income' => 2, 'purchase_amount' => 2];
        $point2 = ['age' => 4, 'income' => 6, 'purchase_amount' => 8];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        // sqrt((4-1)^2 + (6-2)^2 + (8-2)^2) = sqrt(9 + 16 + 36) = sqrt(61) ≈ 7.81
        $expected = sqrt(61);
        
        $this->assertAlmostEqual($expected, $distance, 0.0001, "Distance should be sqrt(61) ≈ 7.81");
    }
    
    /**
     * Test 4: Symmetry (distance(A,B) = distance(B,A))
     */
    public function testSymmetry() {
        $this->printTestHeader("TEST 4: Symmetry Property");
        
        $point1 = ['age' => 25, 'income' => 40000, 'purchase_amount' => 1500];
        $point2 = ['age' => 55, 'income' => 80000, 'purchase_amount' => 3500];
        
        $distanceAB = $this->callEuclideanDistance($point1, $point2);
        $distanceBA = $this->callEuclideanDistance($point2, $point1);
        
        $this->assertAlmostEqual($distanceAB, $distanceBA, 0.0001, "Distance should be symmetric: d(A,B) = d(B,A)");
    }
    
    /**
     * Test 5: Negative values
     */
    public function testNegativeValues() {
        $this->printTestHeader("TEST 5: Negative Values");
        
        $point1 = ['age' => -10, 'income' => -5000, 'purchase_amount' => -500];
        $point2 = ['age' => 10, 'income' => 5000, 'purchase_amount' => 500];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        // sqrt((10-(-10))^2 + (5000-(-5000))^2 + (500-(-500))^2)
        // = sqrt(20^2 + 10000^2 + 1000^2)
        // = sqrt(400 + 100000000 + 1000000)
        // = sqrt(101000400) ≈ 10049.9
        $expected = sqrt(400 + 100000000 + 1000000);
        
        $this->assertAlmostEqual($expected, $distance, 0.1, "Should handle negative values correctly");
    }
    
    /**
     * Test 6: Large values (no overflow)
     */
    public function testLargeValues() {
        $this->printTestHeader("TEST 6: Large Values (No Overflow)");
        
        $point1 = ['age' => 1000, 'income' => 1000000, 'purchase_amount' => 100000];
        $point2 = ['age' => 2000, 'income' => 2000000, 'purchase_amount' => 200000];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        
        // Should not be INF or NaN
        $this->assertEqual(true, is_finite($distance), "Distance should be finite (not overflow)");
        $this->assertEqual(true, $distance > 0, "Distance should be positive");
        
        // Calculate expected
        $expected = sqrt(1000*1000 + 1000000*1000000 + 100000*100000);
        $this->assertAlmostEqual($expected, $distance, 1, "Should calculate large distances correctly");
    }
    
    /**
     * Test 7: Zero values
     */
    public function testZeroValues() {
        $this->printTestHeader("TEST 7: Zero Values");
        
        $point1 = ['age' => 0, 'income' => 0, 'purchase_amount' => 0];
        $point2 = ['age' => 0, 'income' => 0, 'purchase_amount' => 0];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        
        $this->assertAlmostEqual(0, $distance, 0.0001, "Distance between two zero points should be 0");
        
        // One zero, one non-zero
        $point1 = ['age' => 0, 'income' => 0, 'purchase_amount' => 0];
        $point2 = ['age' => 5, 'income' => 12, 'purchase_amount' => 0];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        $expected = 13; // sqrt(5^2 + 12^2) = 13
        
        $this->assertAlmostEqual($expected, $distance, 0.0001, "Distance from origin should be correct");
    }
    
    /**
     * Test 8: Decimal/Float values
     */
    public function testFloatValues() {
        $this->printTestHeader("TEST 8: Decimal/Float Values");
        
        $point1 = ['age' => 25.5, 'income' => 50000.75, 'purchase_amount' => 2000.25];
        $point2 = ['age' => 35.8, 'income' => 60000.50, 'purchase_amount' => 3000.99];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        
        // Calculate expected
        $ageDiff = 35.8 - 25.5;
        $incomeDiff = 60000.50 - 50000.75;
        $purchaseDiff = 3000.99 - 2000.25;
        $expected = sqrt($ageDiff*$ageDiff + $incomeDiff*$incomeDiff + $purchaseDiff*$purchaseDiff);
        
        $this->assertAlmostEqual($expected, $distance, 0.01, "Should handle float values correctly");
        $this->assertEqual(true, is_finite($distance), "Distance should be finite");
    }
    
    /**
     * Test 9: Triangle inequality (d(A,C) <= d(A,B) + d(B,C))
     */
    public function testTriangleInequality() {
        $this->printTestHeader("TEST 9: Triangle Inequality Property");
        
        $pointA = ['age' => 20, 'income' => 30000, 'purchase_amount' => 1000];
        $pointB = ['age' => 40, 'income' => 50000, 'purchase_amount' => 2000];
        $pointC = ['age' => 60, 'income' => 70000, 'purchase_amount' => 3000];
        
        $distAB = $this->callEuclideanDistance($pointA, $pointB);
        $distBC = $this->callEuclideanDistance($pointB, $pointC);
        $distAC = $this->callEuclideanDistance($pointA, $pointC);
        
        $this->assertEqual(
            true, 
            $distAC <= ($distAB + $distBC + 0.0001), // Small tolerance for float precision
            "Triangle inequality: d(A,C) <= d(A,B) + d(B,C)"
        );
    }
    
    /**
     * Test 10: Normalized vs non-normalized data
     */
    public function testNormalizedData() {
        $this->printTestHeader("TEST 10: Works with Normalized Data");
        
        // Normalized data (mean=0, std=1)
        $point1 = ['age' => -1.5, 'income' => -0.5, 'purchase_amount' => 0.0];
        $point2 = ['age' => 1.5, 'income' => 0.5, 'purchase_amount' => 0.0];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        
        // sqrt((1.5-(-1.5))^2 + (0.5-(-0.5))^2 + 0^2) = sqrt(9 + 1 + 0) = sqrt(10)
        $expected = sqrt(10);
        
        $this->assertAlmostEqual($expected, $distance, 0.0001, "Should work correctly with normalized data");
    }
    
    /**
     * Test 11: Real-world customer data scenario
     */
    public function testRealWorldScenario() {
        $this->printTestHeader("TEST 11: Real-World Customer Data");
        
        // Two similar customers
        $customer1 = ['age' => 35, 'income' => 55000, 'purchase_amount' => 2300];
        $customer2 = ['age' => 37, 'income' => 57000, 'purchase_amount' => 2500];
        
        $distance1 = $this->callEuclideanDistance($customer1, $customer2);
        
        // Two very different customers
        $customer3 = ['age' => 22, 'income' => 28000, 'purchase_amount' => 800];
        $customer4 = ['age' => 65, 'income' => 95000, 'purchase_amount' => 4500];
        
        $distance2 = $this->callEuclideanDistance($customer3, $customer4);
        
        $this->assertEqual(
            true,
            $distance2 > $distance1,
            "Distance between dissimilar customers should be greater than similar ones"
        );
        
        echo "    ℹ Similar customers distance: " . number_format($distance1, 2) . "\n";
        echo "    ℹ Different customers distance: " . number_format($distance2, 2) . "\n";
    }
    
    /**
     * Test 12: Return type validation
     */
    public function testReturnType() {
        $this->printTestHeader("TEST 12: Return Type Validation");
        
        $point1 = ['age' => 30, 'income' => 50000, 'purchase_amount' => 2000];
        $point2 = ['age' => 40, 'income' => 60000, 'purchase_amount' => 3000];
        
        $distance = $this->callEuclideanDistance($point1, $point2);
        
        $this->assertEqual(true, is_numeric($distance), "Distance should be numeric");
        $this->assertEqual(true, is_float($distance), "Distance should be float");
        $this->assertEqual(true, $distance >= 0, "Distance should be non-negative");
        $this->assertEqual(true, is_finite($distance), "Distance should be finite");
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "K-MEANS CLUSTERING: euclideanDistance() Test Suite\n";
        echo str_repeat("=", 70) . "\n";
        
        $this->testIdenticalPoints();
        $this->testOneDimensionDifference();
        $this->testStandard3DDistance();
        $this->testSymmetry();
        $this->testNegativeValues();
        $this->testLargeValues();
        $this->testZeroValues();
        $this->testFloatValues();
        $this->testTriangleInequality();
        $this->testNormalizedData();
        $this->testRealWorldScenario();
        $this->testReturnType();
        
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
$tester = new EuclideanDistanceTest();
$allPassed = $tester->runAllTests();

// Exit with appropriate code
exit($allPassed ? 0 : 1);
?>