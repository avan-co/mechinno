<?php

declare(strict_types=1);

final class XlsxReader
{
    private ZipArchive $zip;

    /** @var array<int, string> */
    private array $sharedStrings = [];

    /** @var array<string, array{path:string,cells:array<string,string>,merged:array<string,string>,maxRow:int}> */
    private array $sheets = [];

    public function __construct(private readonly string $path)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP Zip extension is required to read XLSX files.');
        }
        if (!is_file($path)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Cannot open workbook: {$path}");
        }

        $this->loadSharedStrings();
        $this->loadWorkbook();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /**
     * @return list<string>
     */
    public function sheetNames(): array
    {
        return array_map('strval', array_keys($this->sheets));
    }

    public function maxRow(string $sheetName): int
    {
        $this->loadSheetIfNeeded($sheetName);
        return $this->sheets[$sheetName]['maxRow'];
    }

    public function value(string $sheetName, string $coordinate): string
    {
        $this->loadSheetIfNeeded($sheetName);
        $sheet = $this->sheets[$sheetName];
        $anchor = $sheet['merged'][$coordinate] ?? $coordinate;

        return $sheet['cells'][$anchor] ?? '';
    }

    private function loadSharedStrings(): void
    {
        $xml = $this->readXml('xl/sharedStrings.xml');
        if ($xml === null) {
            return;
        }

        foreach ($this->xpath($xml, 'si') as $si) {
            $parts = [];
            foreach ($this->xpath($si, 't') as $textNode) {
                $parts[] = (string) $textNode;
            }
            $this->sharedStrings[] = implode('', $parts);
        }
    }

    private function loadWorkbook(): void
    {
        $workbook = $this->readXml('xl/workbook.xml');
        $rels = $this->readXml('xl/_rels/workbook.xml.rels');
        if ($workbook === null || $rels === null) {
            throw new RuntimeException('Invalid XLSX workbook structure.');
        }

        $relationshipTargets = [];
        foreach ($this->xpath($rels, 'Relationship') as $relationship) {
            $attributes = $relationship->attributes();
            $id = (string) ($attributes['Id'] ?? '');
            $target = (string) ($attributes['Target'] ?? '');
            if ($id !== '' && $target !== '') {
                $relationshipTargets[$id] = $this->normalizeWorkbookTarget($target);
            }
        }

        foreach ($this->xpath($workbook, 'sheet') as $sheet) {
            $attributes = $sheet->attributes();
            $name = (string) ($attributes['name'] ?? '');
            $ridAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string) ($ridAttributes['id'] ?? '');
            if ($name !== '' && isset($relationshipTargets[$rid])) {
                $this->sheets[$name] = [
                    'path' => $relationshipTargets[$rid],
                    'cells' => [],
                    'merged' => [],
                    'maxRow' => 0,
                ];
            }
        }
    }

    private function loadSheetIfNeeded(string $sheetName): void
    {
        if (!isset($this->sheets[$sheetName])) {
            throw new RuntimeException("Sheet not found: {$sheetName}");
        }
        if ($this->sheets[$sheetName]['cells'] !== [] || $this->sheets[$sheetName]['maxRow'] > 0) {
            return;
        }

        $sheetXml = $this->readXml($this->sheets[$sheetName]['path']);
        if ($sheetXml === null) {
            throw new RuntimeException("Cannot read sheet XML: {$sheetName}");
        }

        $cells = [];
        $maxRow = 0;
        foreach ($this->xpath($sheetXml, 'c') as $cell) {
            $attributes = $cell->attributes();
            $coordinate = (string) ($attributes['r'] ?? '');
            if ($coordinate === '') {
                continue;
            }
            $row = self::rowNumber($coordinate);
            $maxRow = max($maxRow, $row);
            $value = $this->cellValue($cell);
            if ($value !== '') {
                $cells[$coordinate] = $value;
            }
        }

        $merged = [];
        foreach ($this->xpath($sheetXml, 'mergeCell') as $mergeCell) {
            $ref = (string) ($mergeCell->attributes()['ref'] ?? '');
            if ($ref === '') {
                continue;
            }
            [$start, $end] = array_pad(explode(':', $ref, 2), 2, $ref);
            foreach (self::coordinatesInRange($start, $end) as $coordinate) {
                $merged[$coordinate] = $start;
            }
        }

        $this->sheets[$sheetName]['cells'] = $cells;
        $this->sheets[$sheetName]['merged'] = $merged;
        $this->sheets[$sheetName]['maxRow'] = $maxRow;
    }

    private function cellValue(SimpleXMLElement $cell): string
    {
        $attributes = $cell->attributes();
        $type = (string) ($attributes['t'] ?? '');

        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($this->xpath($cell, 't') as $node) {
                $parts[] = (string) $node;
            }
            return trim(implode('', $parts));
        }

        $valueNodes = $this->xpath($cell, 'v');
        if ($valueNodes === []) {
            return '';
        }

        $raw = trim((string) $valueNodes[0]);
        if ($type === 's') {
            return trim($this->sharedStrings[(int) $raw] ?? $raw);
        }
        if ($type === 'b') {
            return $raw === '1' ? 'TRUE' : 'FALSE';
        }

        return $raw;
    }

    private function readXml(string $path): ?SimpleXMLElement
    {
        $contents = $this->zip->getFromName($path);
        if ($contents === false) {
            return null;
        }
        $xml = simplexml_load_string($contents);
        if (!$xml instanceof SimpleXMLElement) {
            return null;
        }

        return $xml;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function xpath(SimpleXMLElement $xml, string $localName): array
    {
        $result = $xml->xpath('.//*[local-name()="' . $localName . '"]');
        if ($result === false) {
            return [];
        }

        return $result;
    }

    private function normalizeWorkbookTarget(string $target): string
    {
        $target = ltrim($target, '/');
        if (str_starts_with($target, 'xl/')) {
            return $target;
        }

        return 'xl/' . $target;
    }

    /**
     * @return list<string>
     */
    private static function coordinatesInRange(string $start, string $end): array
    {
        [$startColumn, $startRow] = self::splitCoordinate($start);
        [$endColumn, $endRow] = self::splitCoordinate($end);
        $startIndex = self::columnToIndex($startColumn);
        $endIndex = self::columnToIndex($endColumn);
        $coordinates = [];

        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($column = $startIndex; $column <= $endIndex; $column++) {
                $coordinates[] = self::indexToColumn($column) . $row;
            }
        }

        return $coordinates;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function splitCoordinate(string $coordinate): array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/', $coordinate, $matches)) {
            return ['A', 1];
        }

        return [$matches[1], (int) $matches[2]];
    }

    private static function rowNumber(string $coordinate): int
    {
        return self::splitCoordinate($coordinate)[1];
    }

    private static function columnToIndex(string $column): int
    {
        $index = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($column[$i]) - 64);
        }

        return $index;
    }

    private static function indexToColumn(int $index): string
    {
        $column = '';
        while ($index > 0) {
            $index--;
            $column = chr(65 + ($index % 26)) . $column;
            $index = intdiv($index, 26);
        }

        return $column;
    }
}
