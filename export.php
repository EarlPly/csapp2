<?php

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// 1. CLEAR BUFFER & SUPPRESS WARNINGS (Critical for file downloads)
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'db.php';

// 2. CHECK LIBRARIES
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    // If libraries are missing, allow CSV but block PDF/Excel
    if (isset($_POST['format']) && ($_POST['format'] == 'pdf' || $_POST['format'] == 'excel')) {
        ob_end_clean();
        die("Error: Libraries not found. Please run: composer require tecnickcom/tcpdf phpoffice/phpspreadsheet");
    }
}

// 3. AUTHENTICATION & VALIDATION
if (!isset($_SESSION['logged_in'])) {
    ob_end_clean();
    die("Access denied. Please login.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['export_sql'])) {
    ob_end_clean();
    die("Invalid request. Please run a segmentation first.");
}

// 4. RETRIEVE DATA
$sql = $_SESSION['export_sql'];
$format = filter_input(INPUT_POST, 'format', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Retrieve inputs
$selectedCols = $_POST['cols'] ?? []; 
$chartMain = $_POST['chart_image_main'] ?? null;
$chartPie = $_POST['chart_image_pie'] ?? null;
$analysisText = $_POST['analysis_insights'] ?? ''; 

try {
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_end_clean();
    die("Error fetching data: " . $e->getMessage());
}

if (empty($data)) {
    ob_end_clean();
    die("No records found to export.");
}

// 5. FILTER COLUMNS LOGIC
$finalData = [];

if (!empty($selectedCols)) {
    foreach ($data as $row) {
        $filteredRow = [];
        foreach ($selectedCols as $colName) {
            if (array_key_exists($colName, $row)) {
                $filteredRow[$colName] = $row[$colName];
            }
        }
        $finalData[] = $filteredRow;
    }
} else {
    // Fallback if array is empty 
    $finalData = $data; 
}

// Ensure we have data after filtering
if (empty($finalData)) {
    ob_end_clean();
    die("No columns selected for export.");
}

// =========================================================
// 5.5 SAVE TO HISTORY (NEW CODE)
// =========================================================
try {
    // 1. Prepare the column list for JSON storage
    // If $selectedCols is empty, it means "all", otherwise store the array
    $colsToStore = empty($selectedCols) ? ['all'] : $selectedCols;
    
    // 2. Get User ID (Assumes $_SESSION['user_id'] exists, defaults to 0 or NULL if not)
    $userId = $_SESSION['user_id'] ?? 0; 

    // 3. Prepare SQL
    $historySql = "INSERT INTO export_history 
                   (user_id, export_format, record_count, exported_columns, export_date) 
                   VALUES (:uid, :fmt, :count, :cols, NOW())";
    
    $historyStmt = $pdo->prepare($historySql);
    
    // 4. Execute
    $historyStmt->execute([
        ':uid'   => $userId,
        ':fmt'   => $format,
        ':count' => count($finalData),
        ':cols'  => json_encode($colsToStore)
    ]);

} catch (PDOException $e) {
    // Optional: Log the error, but allow the export to continue
    error_log("Failed to save export history: " . $e->getMessage());
}

// 6. EXPORT LOGIC

// =========================================================
// CSV EXPORT (Table Data Only)
// =========================================================
if ($format === 'csv') {
    ob_end_clean();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="segmentation_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    
    // 1. Write Headers
    if (!empty($finalData)) {
        fputcsv($output, array_keys($finalData[0]));
    }
    
    // 2. Write Data Rows
    foreach ($finalData as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// =========================================================
// PDF EXPORT
// =========================================================
elseif ($format === 'pdf') {
    if (!class_exists('TCPDF')) {
        ob_end_clean();
        die("Error: TCPDF not installed.");
    }

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Dashboard');
    $pdf->SetTitle('Segmentation Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    // 1. Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Customer Segmentation Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(5);

    // 2. Analysis Insights
    if (!empty($analysisText)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Write(0, 'Analysis Insights:', '', 0, 'L', true);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(2);
        $cleanText = strip_tags($analysisText); 
        $pdf->MultiCell(0, 5, $cleanText, 0, 'L');
        $pdf->Ln(10);
    }

    // 3. Charts
    $yPos = $pdf->GetY();
    
    // If not enough space for charts (approx 90mm height), add page
    if ($yPos > 180) { 
        $pdf->AddPage(); 
        $yPos = 15; 
    }

    $chartsAdded = false;

    // Main Chart (Left)
    if ($chartMain) {
        $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartMain));
        $pdf->Image('@'.$imgData, 15, $yPos, 90, 0, 'PNG');
        $chartsAdded = true;
    }

    // Pie Chart (Right)
    if ($chartPie) {
        $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartPie));
        $pdf->Image('@'.$imgData, 110, $yPos, 80, 0, 'PNG');
        $chartsAdded = true;
    }
    
    if ($chartsAdded) {
        $pdf->Ln(95); 
    }

    // 4. Data Table
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Write(0, 'Data Overview:', '', 0, 'L', true);
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 9);
    
    $html = '<table border="1" cellpadding="4" cellspacing="0">';
    
    // Headers
    $html .= '<tr style="background-color:#f2f2f2; font-weight:bold;">';
    foreach (array_keys($finalData[0]) as $header) {
        $html .= '<th>' . ucfirst(str_replace('_', ' ', $header)) . '</th>';
    }
    $html .= '</tr>';
    
    // Rows
    foreach ($finalData as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    ob_end_clean(); 
    $pdf->Output('report.pdf', 'D');
    exit;
}

// =========================================================
// EXCEL EXPORT (FIXED)
// =========================================================
elseif ($format === 'excel') {
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        ob_end_clean();
        die("Error: PhpSpreadsheet library not installed.");
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data');

    $rowNum = 1;

    // 1. Analysis Insights
    if (!empty($analysisText)) {
        $sheet->setCellValue('A1', 'Analysis Insights');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $rowNum++;
        
        $sheet->setCellValue('A' . $rowNum, strip_tags($analysisText));
        $sheet->mergeCells('A' . $rowNum . ':E' . $rowNum); 
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setWrapText(true);
        $rowNum += 3; // Add spacing
    }

    // 2. Headers
    $col = 1;
    foreach (array_keys($finalData[0]) as $header) {
        // FIX: Convert Number to Letter (e.g., 1 -> A)
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        
        // Use standard setCellValue with coordinates
        $sheet->setCellValue($colLetter . $rowNum, ucfirst(str_replace('_', ' ', $header)));
        $sheet->getStyle($colLetter . $rowNum)->getFont()->setBold(true);
        $col++;
    }
    $rowNum++;

    // 3. Data Rows
    foreach ($finalData as $row) {
        $col = 1;
        foreach ($row as $cell) {
            // FIX: Convert Number to Letter
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            
            // Set Value
            $sheet->setCellValue($colLetter . $rowNum, $cell);
            $col++;
        }
        $rowNum++;
    }

    // 4. Embed Main Chart (Side by Side)
    if ($chartMain) {
        $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartMain));
        $tmpFile = tempnam(sys_get_temp_dir(), 'chart_main');
        file_put_contents($tmpFile, $imgData);
        
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Main Chart');
        $drawing->setPath($tmpFile);
        $drawing->setCoordinates('G2'); // Place to the right of data
        $drawing->setHeight(300);
        $drawing->setWorksheet($sheet);
    }

    // 5. Embed Pie Chart (Below Main Chart)
    if ($chartPie) {
        $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartPie));
        $tmpFilePie = tempnam(sys_get_temp_dir(), 'chart_pie');
        file_put_contents($tmpFilePie, $imgData);
        
        $drawingPie = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawingPie->setName('Pie Chart');
        $drawingPie->setPath($tmpFilePie);
        $drawingPie->setCoordinates('G20'); // Place below the main chart
        $drawingPie->setHeight(300);
        $drawingPie->setWorksheet($sheet);
    }

    ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="export.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>