<?php
// 1. CLEAN BUFFER TO PREVENT CORRUPT FILES
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'db.php';

// 2. LIBRARY CHECK
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    if (isset($_POST['format']) && ($_POST['format'] == 'pdf' || $_POST['format'] == 'excel')) {
        ob_end_clean();
        die("Error: Libraries not found. Please run: composer require tecnickcom/tcpdf phpoffice/phpspreadsheet");
    }
}

// 3. AUTH & VALIDATION
if (!isset($_SESSION['logged_in'])) die("Access denied.");
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['export_sql'])) die("Invalid request.");

// 4. GET DATA
$sql = $_SESSION['export_sql'];
$format = filter_input(INPUT_POST, 'format', FILTER_SANITIZE_STRING);
$selectedCols = $_POST['cols'] ?? []; 

// Retrieve Visual Inputs
$chartMain = $_POST['chart_image_main'] ?? null;
$chartPie = $_POST['chart_image_pie'] ?? null;
$analysisText = $_POST['analysis_insights'] ?? ''; 

// Retrieve Cluster Inputs
$clusterHtml = $_POST['cluster_html_content'] ?? '';
$chartRadar = $_POST['chart_cluster_radar'] ?? null;
$chartBar = $_POST['chart_cluster_bar'] ?? null;
$chartScatter = $_POST['chart_cluster_scatter'] ?? null;

try {
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

if (empty($data)) die("No records.");

// Filter Columns
$finalData = [];
if (!empty($selectedCols)) {
    foreach ($data as $row) {
        $filteredRow = [];
        foreach ($selectedCols as $colName) {
            if (array_key_exists($colName, $row)) $filteredRow[$colName] = $row[$colName];
        }
        $finalData[] = $filteredRow;
    }
} else {
    $finalData = $data; 
}

// =========================================================
// CSV EXPORT
// =========================================================
if ($format === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="export.csv"');
    $output = fopen('php://output', 'w');
    if (!empty($finalData)) fputcsv($output, array_keys($finalData[0]));
    foreach ($finalData as $row) fputcsv($output, $row);
    fclose($output);
    exit;
}

// =========================================================
// PDF EXPORT
// =========================================================
elseif ($format === 'pdf') {
    if (!class_exists('TCPDF')) die("Error: TCPDF missing.");

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Dashboard');
    $pdf->SetTitle('Report');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Segmentation Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Date: ' . date('Y-m-d'), 0, 1, 'C');
    $pdf->Ln(5);

    // 1. DYNAMIC INSIGHTS
    if (!empty($analysisText)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Write(0, 'Analysis Insights:', '', 0, 'L', true);
        $pdf->Ln(5); // Add spacing before text
        
        $pdf->SetFont('helvetica', '', 10);
        // writeHTML ensures text flows correctly and updates GetY()
        $pdf->writeHTML(nl2br(strip_tags($analysisText)), true, false, true, false, '');
        $pdf->Ln(10); // Add spacing after text
    }

    // 2. STANDARD CHARTS (Fixed Positioning Logic)
    // Calculate remaining space on the page
    // PageHeight (approx 297mm) - CurrentY - BottomMargin (15mm)
    $remainingSpace = $pdf->getPageHeight() - $pdf->GetY() - 15;
    $requiredHeight = 90; // Approx height needed for charts

    // If not enough space, move to next page
    if ($remainingSpace < $requiredHeight) {
        $pdf->AddPage();
    }
    
    $y = $pdf->GetY();
    if ($chartMain) {
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartMain));
        $pdf->Image('@'.$img, 15, $y, 90, 0, 'PNG');
    }
    if ($chartPie) {
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartPie));
        $pdf->Image('@'.$img, 110, $y, 80, 0, 'PNG');
    }
    
    // Move cursor down only if charts were drawn
    if ($chartMain || $chartPie) {
        $pdf->SetY($y + 75); 
    }

    // 3. CLUSTER TEXT DATA (Characteristics & Stats)
    if (!empty($clusterHtml)) {
        $pdf->Ln(5);
        $style = '<style>
            h4 { font-weight: bold; font-size: 14pt; }
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #333; color: white; font-weight: bold; }
            td { border: 1px solid #ccc; }
        </style>';
        $cleanHtml = strip_tags($clusterHtml, '<h4><h6><p><div><table><thead><tbody><tr><th><td><b><strong><style>');
        $pdf->writeHTML($style . $cleanHtml, true, false, true, false, '');
    }

    // 4. CLUSTER CHARTS
    if ($chartRadar || $chartBar) {
        $remainingSpace = $pdf->getPageHeight() - $pdf->GetY() - 15;
        if ($remainingSpace < 80) $pdf->AddPage();
        
        $pdf->Ln(5);
        $y = $pdf->GetY();
        
        if ($chartRadar) {
            $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartRadar));
            $pdf->Image('@'.$img, 15, $y, 80, 0, 'PNG');
        }
        if ($chartBar) {
            $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartBar));
            $pdf->Image('@'.$img, 100, $y, 95, 0, 'PNG');
        }
        $pdf->SetY($y + 75);
    }

    if ($chartScatter) {
        $remainingSpace = $pdf->getPageHeight() - $pdf->GetY() - 15;
        if ($remainingSpace < 90) $pdf->AddPage();
        
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartScatter));
        $pdf->Image('@'.$img, 30, $pdf->GetY(), 150, 0, 'PNG');
        $pdf->Ln(80);
    }

    // 5. RAW DATA TABLE
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Write(0, 'Data Overview:', '', 0, 'L', true);
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 9);
    
    $html = '<table border="1" cellpadding="4"><thead><tr style="background-color:#eee;">';
    // Headers
    foreach (array_keys($finalData[0]) as $h) {
        $html .= '<th>' . ucfirst(str_replace('_', ' ', $h)) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    // Rows
    foreach ($finalData as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    ob_end_clean();
    $pdf->Output('report.pdf', 'D');
    exit;
}

// =========================================================
// EXCEL EXPORT
// =========================================================
elseif ($format === 'excel') {
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) die("Error: Library missing.");

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $rowNum = 1;

    // 1. ANALYSIS INSIGHTS (Fixed Formatting)
    if (!empty($analysisText)) {
        // Label
        $sheet->setCellValue('A' . $rowNum, 'Analysis Insights:');
        $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(12);
        $rowNum++;

        // Content
        $textCell = 'A' . $rowNum;
        $sheet->setCellValue($textCell, strip_tags($analysisText));
        
        // Merge cells across 6 columns (A to F)
        $sheet->mergeCells("A{$rowNum}:F{$rowNum}");
        
        // Style: Wrap Text + Align Top
        $sheet->getStyle($textCell)->getAlignment()->setWrapText(true);
        $sheet->getStyle($textCell)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
        
        // Calculate rough height (Excel default line height ~15px)
        // Estimate: 100 characters per line
        $lines = ceil(strlen(strip_tags($analysisText)) / 100);
        $sheet->getRowDimension($rowNum)->setRowHeight($lines * 15 + 10);
        
        $rowNum += 2; // Add some spacing after
    }
    
    // 2. HEADERS
    $col = 1;
    foreach (array_keys($finalData[0]) as $h) {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++);
        $sheet->setCellValue($letter.$rowNum, ucfirst($h));
        $sheet->getStyle($letter.$rowNum)->getFont()->setBold(true);
    }
    $rowNum++;

    // 3. DATA ROWS
    foreach ($finalData as $row) {
        $col = 1;
        foreach ($row as $cell) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++);
            $sheet->setCellValue($letter.$rowNum, $cell);
        }
        $rowNum++;
    }

    // Auto-size columns for better readability
    $colCount = count($finalData[0]);
    for ($i = 1; $i <= $colCount; $i++) {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($letter)->setAutoSize(true);
    }

    // Image Helper
    function addImg($b64, $coords, $sheet) {
        if (!$b64) return;
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $b64));
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, $data);
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setPath($tmp);
        $drawing->setCoordinates($coords);
        $drawing->setHeight(200);
        $drawing->setWorksheet($sheet);
    }

    // Place images to the right of the data (Column G)
    addImg($chartMain, 'G2', $sheet);
    addImg($chartPie, 'G15', $sheet);
    if ($chartRadar) addImg($chartRadar, 'N2', $sheet);
    if ($chartBar) addImg($chartBar, 'N15', $sheet);

    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="export.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>