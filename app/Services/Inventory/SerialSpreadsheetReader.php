<?php

namespace App\Services\Inventory;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

/**
 * Reads ONLY the serial column of an XLSX file in bounded row windows.
 *
 * - Xlsx reader with setReadDataOnly(true): no styles, no calculation.
 *   Formula cells expose their formula string (never evaluated).
 * - A read filter restricts loaded cells to the requested window, and the
 *   workbook is disconnected after every window to bound memory.
 * - toArray() over a whole sheet is deliberately never used.
 */
final class SerialSpreadsheetReader
{
    public function __construct(private readonly string $path)
    {
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CHECKCONS) !== true) {
            throw SerialImportException::fileError('فایل XLSX خراب یا رمزگذاری‌شده است.');
        }
        try {
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false || ($entry['encryption_method'] ?? 0) !== 0) {
                    throw SerialImportException::fileError('فایل XLSX خراب یا رمزگذاری‌شده است.');
                }
                $expanded += $entry['size'];
                if ($expanded > (int) config('serial_inventory.max_expanded_bytes', 100 * 1024 * 1024)) {
                    throw SerialImportException::fileError('حجم بازشده فایل از سقف مجاز بیشتر است.');
                }
            }
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) {
                throw SerialImportException::fileError('ساختار فایل XLSX معتبر نیست.');
            }
        } finally {
            $zip->close();
        }
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return (new Xlsx)->listWorksheetNames($this->path);
    }

    /**
     * Lightweight sheet dimensions keyed by sheet name (no full load).
     *
     * @return array<string, array{totalRows: int, totalColumns: int}>
     */
    public function sheetInfo(): array
    {
        $info = [];

        foreach ((new Xlsx)->listWorksheetInfo($this->path) as $sheet) {
            $info[$sheet['worksheetName']] = [
                'totalRows' => (int) $sheet['totalRows'],
                'totalColumns' => (int) $sheet['totalColumns'],
            ];
        }

        return $info;
    }

    public function headerValue(string $sheet, string $column, int $row): ?string
    {
        $values = $this->readColumnRange($sheet, $column, $row, $row);

        return $values[$row] ?? null;
    }

    /**
     * Raw string values (null when the cell is empty) keyed by row number.
     *
     * @return array<int, ?string>
     */
    public function readColumnRange(string $sheet, string $column, int $startRow, int $endRow): array
    {
        $column = strtoupper($column);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly($sheet);
        $reader->setReadFilter(new ColumnWindowFilter($column, $startRow, $endRow));

        $spreadsheet = $reader->load($this->path);

        try {
            $worksheet = $spreadsheet->getSheetByName($sheet);

            if ($worksheet === null) {
                return [];
            }

            $values = [];

            for ($row = $startRow; $row <= $endRow; $row++) {
                $cell = $worksheet->getCell($column.$row);
                $values[$row] = self::cellToString($cell->getValue());
            }

            return $values;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public static function cellToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if (is_bool($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            // Guard against float exponent formatting leaking in.
            if (is_float($value) && ! str_contains((string) $value, '.') && ! str_contains(strtolower((string) $value), 'e')) {
                return (string) (int) $value;
            }

            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return $value;
    }
}

/**
 * Loads only one column within [startRow, endRow].
 */
final class ColumnWindowFilter implements IReadFilter
{
    public function __construct(
        private readonly string $column,
        private readonly int $startRow,
        private readonly int $endRow,
    ) {}

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $columnAddress === $this->column && $row >= $this->startRow && $row <= $this->endRow;
    }
}
