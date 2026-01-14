<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'db.php';

if (file_exists('vendor/autoload.php')) { require 'vendor/autoload.php'; }
else { if (isset($_POST['format']) && in_array($_POST['format'], ['pdf', 'excel'])) die("Error: Libraries missing."); }

if (!isset($_SESSION['logged_in']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['export_sql'])) die("Invalid Request.");

$sql = $_SESSION['export_sql'];
$format = filter_input(INPUT_POST, 'format', FILTER_SANITIZE_STRING);
$selectedCols = $_POST['cols'] ?? []; 

// Data Retrieve
try {
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

if (empty($data)) die("No records.");

// Filter Cols
$finalData = [];
if (!empty($selectedCols)) {
    foreach ($data as $row) {
        $filteredRow = [];
        foreach ($selectedCols as $colName) {
            if (array_key_exists($colName, $row)) $filteredRow[$colName] = $row[$colName];
        }
        $finalData[] = $filteredRow;
    }
} else { $finalData = $data; }

// --- CSV ---
if ($format === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="export.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($finalData)) fputcsv($out, array_keys($finalData[0]));
    foreach ($finalData as $row) fputcsv($out, $row);
    fclose($out); exit;
}

// --- PDF ---
elseif ($format === 'pdf') {
    if (!class_exists('TCPDF')) die("Error: TCPDF missing.");
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetMargins(15, 15, 15); $pdf->SetAutoPageBreak(TRUE, 15); $pdf->AddPage();
    
    // Header
    $pdf->SetFont('helvetica', 'B', 16); $pdf->Cell(0, 10, 'Segmentation Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10); $pdf->Cell(0, 10, 'Date: ' . date('Y-m-d'), 0, 1, 'C'); $pdf->Ln(5);

    // Insights
    if (!empty($_POST['analysis_insights'])) {
        $pdf->SetFont('helvetica', 'B', 12); $pdf->Write(0, 'Analysis Insights:', '', 0, 'L', true); $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->writeHTML(nl2br(strip_tags($_POST['analysis_insights'])), true, false, true, false, ''); $pdf->Ln(5);
    }

    // Standard Charts
    if ($pdf->GetY() > 200) $pdf->AddPage();
    $y = $pdf->GetY();
    if (!empty($_POST['chart_image_main'])) {
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['chart_image_main']));
        $pdf->Image('@'.$img, 15, $y, 90, 0, 'PNG');
    }
    if (!empty($_POST['chart_image_pie'])) {
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['chart_image_pie']));
        $pdf->Image('@'.$img, 110, $y, 80, 0, 'PNG');
    }
    if (!empty($_POST['chart_image_main']) || !empty($_POST['chart_image_pie'])) $pdf->SetY($y + 70);

    // Cluster Text
    if (!empty($_POST['cluster_html_content'])) {
        $pdf->Ln(5);
        $style = '<style>h4{font-weight:bold;font-size:14pt;} table{width:100%;border-collapse:collapse;} th{background:#333;color:#fff;font-weight:bold;} td{border:1px solid #ccc;}</style>';
        $pdf->writeHTML($style . strip_tags($_POST['cluster_html_content'], '<h4><h6><p><div><table><thead><tbody><tr><th><td><b><strong><style>'), true, false, true, false, '');
    }

    // Cluster Charts
    if (!empty($_POST['chart_cluster_radar']) || !empty($_POST['chart_cluster_bar'])) {
        if ($pdf->GetY() > 180) $pdf->AddPage();
        $pdf->Ln(5); $y = $pdf->GetY();
        if (!empty($_POST['chart_cluster_radar'])) {
            $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['chart_cluster_radar']));
            $pdf->Image('@'.$img, 15, $y, 80, 0, 'PNG');
        }
        if (!empty($_POST['chart_cluster_bar'])) {
            $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['chart_cluster_bar']));
            $pdf->Image('@'.$img, 100, $y, 95, 0, 'PNG');
        }
        $pdf->SetY($y + 75);
    }
    if (!empty($_POST['chart_cluster_scatter'])) {
        if ($pdf->GetY() > 200) $pdf->AddPage();
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['chart_cluster_scatter']));
        $pdf->Image('@'.$img, 30, $pdf->GetY(), 150, 0, 'PNG'); $pdf->Ln(80);
    }

    // --- RAW DATA TABLE (RESTORED) ---
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12); $pdf->Write(0, 'Data Overview:', '', 0, 'L', true); $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 9);
    
    $html = '<table border="1" cellpadding="4"><thead><tr style="background-color:#eee;">';
    foreach (array_keys($finalData[0]) as $h) $html .= '<th>' . ucfirst(str_replace('_', ' ', $h)) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($finalData as $row) {
        $html .= '<tr>';
        foreach ($row as $c) $html .= '<td>' . htmlspecialchars($c) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');

    ob_end_clean(); $pdf->Output('report.pdf', 'D'); exit;
}

// --- EXCEL ---
elseif ($format === 'excel') {
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) die("Error: Library missing.");
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); $sheet = $spreadsheet->getActiveSheet();
    $rowNum = 1;

    if (!empty($_POST['analysis_insights'])) { $sheet->setCellValue('A1', strip_tags($_POST['analysis_insights'])); $rowNum += 3; }

    $col = 1;
    foreach (array_keys($finalData[0]) as $h) {
        $l = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++);
        $sheet->setCellValue($l.$rowNum, ucfirst($h));
    } $rowNum++;

    foreach ($finalData as $row) {
        $col = 1;
        foreach ($row as $c) {
            $l = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++);
            $sheet->setCellValue($l.$rowNum, $c);
        } $rowNum++;
    }

    function addImg($b64, $c, $s) {
        if (!$b64) return;
        $d = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $b64));
        $t = tempnam(sys_get_temp_dir(), 'img'); file_put_contents($t, $d);
        $dr = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $dr->setPath($t); $dr->setCoordinates($c); $dr->setHeight(200); $dr->setWorksheet($s);
    }
    addImg($_POST['chart_image_main'] ?? null, 'G2', $sheet);
    addImg($_POST['chart_image_pie'] ?? null, 'G15', $sheet);
    addImg($_POST['chart_cluster_radar'] ?? null, 'N2', $sheet);
    addImg($_POST['chart_cluster_bar'] ?? null, 'N15', $sheet);

    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="export.xlsx"');
    $w = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet); $w->save('php://output'); exit;
}
?>