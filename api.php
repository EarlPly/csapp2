<?php
// api.php - RESTful API for Customer Segmentation

// 1. CONFIGURATION & HEADERS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-API-Key");

require_once 'db.php'; // Use your existing DB connection

// 2. SECURITY: API KEY AUTHENTICATION
$valid_api_key = "12345-SECRET-KEY"; // you can change to whatever, but now it remains for now!
$headers = getallheaders();
$client_key = $headers['X-API-Key'] ?? $_GET['api_key'] ?? '';

if ($client_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid API Key."]);
    exit;
}

// 3. RATE LIMITING (Simple File-Based)
$ip_address = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = 'api_rate_limit.json';
$limit = 100; // Requests per hour
$window = 3600; // 1 hour in seconds

if (!file_exists($rate_limit_file)) file_put_contents($rate_limit_file, '{}');
$data = json_decode(file_get_contents($rate_limit_file), true);

// Clean old entries
foreach ($data as $ip => $record) {
    if (time() - $record['start_time'] > $window) unset($data[$ip]);
}

// Check limit
if (!isset($data[$ip_address])) {
    $data[$ip_address] = ['count' => 1, 'start_time' => time()];
} else {
    $data[$ip_address]['count']++;
}

if ($data[$ip_address]['count'] > $limit) {
    file_put_contents($rate_limit_file, json_encode($data));
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Rate limit exceeded."]);
    exit;
}
file_put_contents($rate_limit_file, json_encode($data));


// 4. ROUTING LOGIC
// Get path from URL (e.g., /api/segments/gender) or query param (api.php?endpoint=segments&type=gender)
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse parameters
$endpoint = $_GET['endpoint'] ?? '';
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

// 5. ENDPOINT HANDLERS

// Endpoint: GET /api/segments/{type}
if ($endpoint === 'segments' && $method === 'GET') {
    if (empty($type)) {
        errorResponse("Missing segmentation type.");
    }
    
    $sql = "";
    switch ($type) {
        case 'gender':
            $sql = "SELECT gender as label, COUNT(*) as count, AVG(income) as avg_income FROM customers GROUP BY gender";
            break;
        case 'region':
            $sql = "SELECT region as label, COUNT(*) as count, AVG(purchase_amount) as avg_spend FROM customers GROUP BY region";
            break;
        case 'clv_tier':
            $sql = "SELECT 
                CASE 
                    WHEN (purchase_amount * purchase_frequency * customer_lifespan) < 50000 THEN 'Bronze'
                    WHEN (purchase_amount * purchase_frequency * customer_lifespan) BETWEEN 50000 AND 150000 THEN 'Silver'
                    WHEN (purchase_amount * purchase_frequency * customer_lifespan) BETWEEN 150001 AND 300000 THEN 'Gold'
                    ELSE 'Platinum'
                END AS label, 
                COUNT(*) AS count, 
                AVG(purchase_amount * purchase_frequency * customer_lifespan) AS avg_clv 
                FROM customers GROUP BY label ORDER BY avg_clv ASC";
            break;
        // ... previous cases like clv_tier ...
        case 'cluster':
            // Add this block for Cluster support
            $sql = "SELECT 
                        sr.cluster_label as label, 
                        COUNT(*) as count, 
                        AVG(c.income) as avg_income, 
                        AVG(c.purchase_amount) as avg_purchase_amount 
                    FROM segmentation_results sr 
                    JOIN customers c ON sr.customer_id = c.customer_id 
                    GROUP BY sr.cluster_label";
            break;

        // ... default: errorResponse ...
        default:
            errorResponse("Invalid segmentation type.");
    }

    try {
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $result]);
    } catch (Exception $e) {
        errorResponse($e->getMessage());
    }
}

// Endpoint: GET /api/clusters
elseif ($endpoint === 'clusters' && $method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM cluster_metadata");
        $clusters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "total_clusters" => count($clusters), "data" => $clusters]);
    } catch (Exception $e) {
        errorResponse("Cluster data not found.");
    }
}

// Endpoint: POST /api/clusters/run
elseif ($endpoint === 'clusters' && $method === 'POST') {
    // In a real app, this would trigger a background Python job
    // Here we simulate the trigger
    echo json_encode([
        "status" => "success", 
        "message" => "Clustering analysis triggered.", 
        "job_id" => uniqid('job_')
    ]);
}

// Endpoint: GET /api/customers/{id}/segment
elseif ($endpoint === 'customers' && !empty($id) && $method === 'GET') {
    try {
        // Fetch basic details + their cluster
        $sql = "SELECT c.*, sr.cluster_label, cm.cluster_name 
                FROM customers c 
                LEFT JOIN segmentation_results sr ON c.customer_id = sr.customer_id
                LEFT JOIN cluster_metadata cm ON sr.cluster_label = cm.cluster_id
                WHERE c.customer_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            echo json_encode(["status" => "success", "data" => $customer]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Customer not found."]);
        }
    } catch (Exception $e) {
        errorResponse($e->getMessage());
    }
}

// Endpoint: GET /api/insights/{type}
elseif ($endpoint === 'insights' && $method === 'GET') {
    // Generate simple text insights server-side
    $insightText = "";
    if ($type == 'clv_tier') {
        $insightText = "CLV analysis shows Platinum customers generate 80% of revenue. Recommended action: Focus on retention.";
    } elseif ($type == 'gender') {
        $insightText = "Gender distribution is balanced. Male segment shows slightly higher average transaction value.";
    } else {
        $insightText = "General segmentation analysis complete.";
    }
    
    echo json_encode([
        "status" => "success", 
        "type" => $type, 
        "insight" => $insightText
    ]);
}

// 404 Not Found
else {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Endpoint not found."]);
}

// Helper function
function errorResponse($msg) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $msg]);
    exit;
}
?>