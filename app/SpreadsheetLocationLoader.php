<?php

class SpreadsheetLocationLoader
{
    private string $filePath;
    private string $jsonFilePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->jsonFilePath = __DIR__ . '/../data/localidade_hierarchy.json';
    }

    public function loadHierarchy(): array
    {
        if (file_exists($this->jsonFilePath) && is_readable($this->jsonFilePath)) {
            $json = file_get_contents($this->jsonFilePath);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (!file_exists($this->filePath)) {
            return [];
        }

        $hierarchy = [];
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($this->filePath) === true) {
                $sharedStrings = $this->loadSharedStrings($zip);
                $sheets = $this->loadSheetDefinitions($zip);
                foreach ($sheets as $sheet) {
                    $worksheetPath = $this->resolveSheetPath($sheet['rid'], $zip);
                    if ($worksheetPath === null) {
                        continue;
                    }

                    $rows = $this->loadWorksheetRows($zip, $worksheetPath, $sharedStrings);
                    if (empty($rows)) {
                        continue;
                    }

                    $sheetName = $sheet['name'];
                    if (preg_match('/^Table\s+\d+$/i', $sheetName) && !empty($rows)) {
                        $sheetName = trim((string)($rows[0][0] ?? $sheetName));
                    }

                    $sectionHierarchy = $this->buildHierarchy($sheetName, $rows);
                    foreach ($sectionHierarchy as $section) {
                        $hierarchy[] = $section;
                    }
                }
                $zip->close();
            }
        }

        if (empty($hierarchy)) {
            $hierarchy = $this->loadFromPython();
        }

        return $hierarchy;

        foreach ($sheets as $sheet) {
            $worksheetPath = $this->resolveSheetPath($sheet['rid'], $zip);
            if ($worksheetPath === null) {
                continue;
            }

            $rows = $this->loadWorksheetRows($zip, $worksheetPath, $sharedStrings);
            if (empty($rows)) {
                continue;
            }

            $sheetName = $sheet['name'];
            if (preg_match('/^Table\s+\d+$/i', $sheetName) && !empty($rows)) {
                $sheetName = trim((string)($rows[0][0] ?? $sheetName));
            }

            $sectionHierarchy = $this->buildHierarchy($sheetName, $rows);
            foreach ($sectionHierarchy as $section) {
                $hierarchy[] = $section;
            }
        }

        $zip->close();
        return $hierarchy;
    }

    private function loadSharedStrings(ZipArchive $zip): array
    {
        $shared = [];
        $index = $zip->locateName('xl/sharedStrings.xml');
        if ($index === false) {
            return $shared;
        }

        $xml = simplexml_load_string($zip->getFromIndex($index));
        if ($xml === false) {
            return $shared;
        }

        $xml->registerXPathNamespace('d', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($xml->xpath('//d:si') as $sharedItem) {
            $shared[] = $this->extractText($sharedItem);
        }

        return $shared;
    }

    private function loadSheetDefinitions(ZipArchive $zip): array
    {
        $definitions = [];
        $xml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        if ($xml === false) {
            return $definitions;
        }

        $xml->registerXPathNamespace('d', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($xml->xpath('//d:sheet') as $sheet) {
            $rule = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $definitions[] = [
                'name' => trim((string)$sheet['name']),
                'rid' => trim((string)$rule['id']),
            ];
        }

        return $definitions;
    }

    private function resolveSheetPath(string $rid, ZipArchive $zip): ?string
    {
        $relsXml = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($relsXml === false) {
            return null;
        }

        $relsXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($relsXml->xpath('//r:Relationship') as $relationship) {
            $attributes = $relationship->attributes();
            if ((string)$attributes['Id'] === $rid) {
                $target = trim((string)$attributes['Target']);
                return $target;
            }
        }

        return null;
    }

    private function loadWorksheetRows(ZipArchive $zip, string $worksheetPath, array $sharedStrings): array
    {
        $content = $zip->getFromName('xl/' . $worksheetPath);
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return [];
        }

        $xml->registerXPathNamespace('d', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($xml->xpath('//d:sheetData/d:row') as $rowElement) {
            $row = [];
            foreach ($rowElement->xpath('d:c') as $cell) {
                $value = '';
                $type = trim((string)$cell['t']);
                if (isset($cell->v)) {
                    $raw = (string)$cell->v;
                    if ($type === 's' && ctype_digit($raw) && isset($sharedStrings[(int)$raw])) {
                        $value = $sharedStrings[(int)$raw];
                    } else {
                        $value = $raw;
                    }
                }
                $row[] = $this->cleanLabel($value);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function buildHierarchy(string $sheetName, array $rows): array
    {
        $sections = [];
        $currentSecretaria = null;
        $currentDivision = null;

        foreach ($rows as $row) {
            $text = trim($row[0] ?? '');
            if ($text === '' || $this->isHeaderRow($text)) {
                continue;
            }

            if ($this->isTopLevelUnit($text)) {
                if ($currentSecretaria !== null) {
                    $sections[] = $currentSecretaria;
                }

                $currentSecretaria = [
                    'secretaria' => $this->cleanLabel($text),
                    'divisoes' => [],
                ];
                $currentDivision = null;
                continue;
            }

            if ($currentSecretaria === null) {
                continue;
            }

            if ($this->isDivision($text)) {
                $currentSecretaria['divisoes'][] = [
                    'name' => $this->cleanLabel($text),
                    'setores' => [],
                ];
                $currentDivision = &$currentSecretaria['divisoes'][count($currentSecretaria['divisoes']) - 1];
                continue;
            }

            if ($this->isSector($text)) {
                if ($currentDivision === null) {
                    $currentSecretaria['divisoes'][] = [
                        'name' => 'Geral',
                        'setores' => [],
                    ];
                    $currentDivision = &$currentSecretaria['divisoes'][count($currentSecretaria['divisoes']) - 1];
                }
                $currentDivision['setores'][] = $this->cleanLabel($text);
            }
        }

        if ($currentSecretaria !== null) {
            $sections[] = $currentSecretaria;
        }

        if (empty($sections) && $sheetName !== '') {
            $currentSecretaria = [
                'secretaria' => $this->cleanLabel($sheetName),
                'divisoes' => [],
            ];
            $currentDivision = null;

            foreach ($rows as $row) {
                $text = trim($row[0] ?? '');
                if ($text === '' || $this->isHeaderRow($text) || $this->isTopLevelUnit($text)) {
                    continue;
                }

                if ($this->isDivision($text)) {
                    $currentSecretaria['divisoes'][] = [
                        'name' => $this->cleanLabel($text),
                        'setores' => [],
                    ];
                    $currentDivision = &$currentSecretaria['divisoes'][count($currentSecretaria['divisoes']) - 1];
                    continue;
                }

                if ($this->isSector($text)) {
                    if ($currentDivision === null) {
                        $currentSecretaria['divisoes'][] = [
                            'name' => 'Geral',
                            'setores' => [],
                        ];
                        $currentDivision = &$currentSecretaria['divisoes'][count($currentSecretaria['divisoes']) - 1];
                    }
                    $currentDivision['setores'][] = $this->cleanLabel($text);
                }
            }

            $sections[] = $currentSecretaria;
        }

        return $sections;
    }

    private function cleanLabel(string $text): string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($text));
        $clean = preg_replace("/^'+/u", '', $clean);
        return $clean === '' ? '' : $clean;
    }

    private function isTopLevelUnit(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');
        if (preg_match('/\b(sec\. mun\.|secretaria municipal|secretaria|gabinete do prefeito|gabinete|fundo social)(?=[\s\W]|$)/i', $lower) !== 1) {
            return false;
        }

        return preg_match('/\b(secretário|presidente|diretor|chefe|coordenador|supervisor|gerente)\b/i', $lower) !== 1;
    }

    private function isHeaderRow(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');
        return preg_match('/^(função|nome|portaria|e-mail|email|organograma das secretarias|table\s+\d+)$/i', $lower) === 1;
    }

    private function isDivision(string $text): bool
    {
        return preg_match('/diretor.*divis/i', mb_strtolower($text, 'UTF-8')) === 1;
    }

    private function isSector(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');
        if ($this->isDivision($text)) {
            return false;
        }

        return preg_match('/chefe.*setor|coordenador|supervisor|gerente|diretor.*setor|setor/i', $lower) === 1;
    }

    private function extractText(SimpleXMLElement $node): string
    {
        $text = '';
        $namespaces = $node->getNamespaces(true);
        $node->registerXPathNamespace('d', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($node->xpath('.//d:t') as $part) {
            $text .= (string)$part;
        }
        return $this->cleanLabel($text);
    }

    private function loadFromPython(): array
    {
        $pythonScript = escapeshellarg(__DIR__ . '/convert_localidade_xlsx.py');
        $xlsxFile = escapeshellarg($this->filePath);
        $command = sprintf('/usr/bin/python3 %s %s 2>/dev/null', $pythonScript, $xlsxFile);
        $output = shell_exec($command);
        if (empty($output)) {
            return [];
        }

        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
