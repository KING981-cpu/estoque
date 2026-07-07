<?php

require_once __DIR__ . '/../app/EnvLoader.php';
require_once __DIR__ . '/../app/InventoryApp.php';

EnvLoader::load(__DIR__ . '/../.env');

$tipoRegistro = isset($_GET['tipo_registro']) && in_array($_GET['tipo_registro'], ['0', '1', 'all'], true)
    ? $_GET['tipo_registro']
    : '1';

$tipoRegistroFilter = $tipoRegistro === 'all' ? null : (int)$tipoRegistro;

$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'] ?? '') ? $_GET['start_date'] : null;
$endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'] ?? '') ? $_GET['end_date'] : null;

$movements = [];
$exportError = null;

try {
    $app = new InventoryApp();
    $movements = $app->getMovementReport(null, $tipoRegistroFilter, $startDate, $endDate);
} catch (Throwable $exception) {
    $exportError = 'Não foi possível carregar os dados do relatório.';
}

$format = strtolower($_GET['format'] ?? 'csv');

function csvEscape(string $value): string
{
    $escaped = str_replace('"', '""', $value);
    return '"' . $escaped . '"';
}

bold_font_name = 'Helvetica-Bold'
bold_font_candidates = [
story.append(Paragraph(payload['title'], styles['title']))
story.append(Paragraph(payload['subtitle'], styles['subtitle']))
story.append(Spacer(1, 0.12 * inch))
function pdfEscapeText(string $value): string
{
    $text = trim((string)$value);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    } elseif (function_exists('mb_convert_encoding')) {
        $text = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function truncateText(string $value, int $length): string
{
    $value = trim($value);
    if (mb_strlen($value) <= $length) {
        return $value;
    }

    return mb_substr($value, 0, max(1, $length - 1)) . '…';
}

function buildPdfReport(array $movements, ?string $errorMessage = null): string
{
    $pageWidth = 612;
    $pageHeight = 792;
    $marginX = 18;
    $marginY = 18;
    $rowHeight = 12;
    $rowsPerPage = 30;
    $pages = array_chunk($movements, $rowsPerPage);

    if ($pages === []) {
        $pages = [[]];
    }

    $objects = [];
    $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $kids = [];
    $fontObjectId = 4;
    $boldFontObjectId = 5;
    $objects[$fontObjectId] = "$fontObjectId 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
    $objects[$boldFontObjectId] = "$boldFontObjectId 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

    $objectId = 6;
    $columnSpecs = [
        ['title' => 'ID', 'width' => 30, 'length' => 5],
        ['title' => 'Data', 'width' => 46, 'length' => 10],
        ['title' => 'Tipo', 'width' => 42, 'length' => 8],
        ['title' => 'Item', 'width' => 120, 'length' => 20],
        ['title' => 'Qtd', 'width' => 28, 'length' => 6],
        ['title' => 'Uso', 'width' => 44, 'length' => 8],
        ['title' => 'Funcionário', 'width' => 78, 'length' => 12],
        ['title' => 'Localidade', 'width' => 72, 'length' => 12],
        ['title' => 'Patrimônio', 'width' => 52, 'length' => 10],
    ];

    foreach ($pages as $pageRows) {
        $contentParts = [];
        $y = $pageHeight - $marginY - 20;
        $contentParts[] = "BT /F2 16 Tf $marginX $y Td (" . pdfEscapeText('Relatório de Movimentações') . ") Tj ET";
        $y -= 12;
        $subtitle = $errorMessage ?? 'Gerado pelo sistema de estoque';
        $contentParts[] = "BT /F1 9 Tf $marginX $y Td (" . pdfEscapeText($subtitle) . ") Tj ET";
        $y -= 16;

        $contentParts[] = "0.8 0.8 0.8 rg";
        $contentParts[] = "$marginX " . ($y - $rowHeight) . " " . ($pageWidth - (2 * $marginX)) . " $rowHeight re f";
        $contentParts[] = "0 0 0 rg";
        $contentParts[] = "$marginX " . ($y - $rowHeight) . " " . ($pageWidth - (2 * $marginX)) . " $rowHeight re S";

        $x = $marginX;
        foreach ($columnSpecs as $index => $columnSpec) {
            $cellText = $index === 0 ? 'ID' : $columnSpec['title'];
            $contentParts[] = "BT /F2 6.5 Tf $x " . ($y - 9) . " Td (" . pdfEscapeText($cellText) . ") Tj ET";
            $x += $columnSpec['width'];
        }

        $y -= $rowHeight;
        if ($errorMessage !== null) {
            $contentParts[] = "BT /F1 9 Tf $marginX $y Td (" . pdfEscapeText($errorMessage) . ") Tj ET";
        } else {
            foreach ($pageRows as $row) {
                if ($y < $marginY + 10) {
                    break;
                }

                $contentParts[] = "0.95 0.95 0.95 rg";
                $contentParts[] = "$marginX " . ($y - $rowHeight) . " " . ($pageWidth - (2 * $marginX)) . " $rowHeight re f";
                $contentParts[] = "0 0 0 rg";
                $contentParts[] = "$marginX " . ($y - $rowHeight) . " " . ($pageWidth - (2 * $marginX)) . " $rowHeight re S";

                $values = [
                    (string)($row['id_movimentacao'] ?? ''),
                    (string)($row['data_item'] ?? ''),
                    (string)($row['tipo'] ?? ''),
                    (string)($row['item_name'] ?? ''),
                    (string)($row['quantidade'] ?? ''),
                    (string)($row['uso'] ?? ''),
                    (string)($row['funcionario'] ?? ''),
                    (string)($row['localidade'] ?? ''),
                    (string)($row['patrimonio'] ?? ''),
                ];

                $x = $marginX;
                foreach ($columnSpecs as $index => $columnSpec) {
                    $cellText = truncateText($values[$index] ?? '', $columnSpec['length']);
                    $contentParts[] = "BT /F1 6 Tf $x " . ($y - 8) . " Td (" . pdfEscapeText($cellText) . ") Tj ET";
                    $x += $columnSpec['width'];
                }

                $y -= $rowHeight;
            }
        }

        $content = implode("\n", $contentParts);
        $pageObjectId = $objectId;
        $contentObjectId = $objectId + 1;
        $objects[$pageObjectId] = "$pageObjectId 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Resources << /Font << /F1 $fontObjectId 0 R /F2 $boldFontObjectId 0 R >> >> /Contents $contentObjectId 0 R >>\nendobj\n";
        $objects[$contentObjectId] = "$contentObjectId 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream\nendobj\n";
        $kids[] = "$pageObjectId 0 R";
        $objectId += 2;
    }

    $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pages) . " >>\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $maxObjectId = max(array_keys($objects));
    foreach ($objects as $objectId => $objectContent) {
        $offsets[$objectId] = strlen($pdf);
        $pdf .= $objectContent;
    }

    $startXref = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObjectId; $i++) {
        $pdf .= str_pad((string)($offsets[$i] ?? 0), 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n$startXref\n%%EOF\n";

    return $pdf;
}

if ($format === 'pdf') {
    $filename = 'movimentacoes_' . date('Ymd_His') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo buildPdfReport($movements, $exportError);
    exit;
}

$filename = 'movimentacoes_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";

$columns = ['ID', 'Data', 'Tipo', 'Item', 'Quantidade', 'Uso', 'Funcionário', 'Localidade', 'Patrimônio'];
echo implode(';', array_map('csvEscape', $columns)) . "\r\n";

foreach ($movements as $movimento) {
    $row = [
        $movimento['id_movimentacao'] ?? '',
        $movimento['data_item'] ?? '',
        $movimento['tipo'] ?? '',
        $movimento['item_name'] ?? '',
        $movimento['quantidade'] ?? '',
        $movimento['uso'] ?? '',
        $movimento['funcionario'] ?? '',
        $movimento['localidade'] ?? '',
        $movimento['patrimonio'] ?? '',
    ];
    echo implode(';', array_map('csvEscape', array_map('strval', $row))) . "\r\n";
}
