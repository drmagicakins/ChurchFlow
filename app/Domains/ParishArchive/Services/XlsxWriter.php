<?php

namespace App\Domains\ParishArchive\Services;

use RuntimeException;
use ZipArchive;

/**
 * A minimal, dependency-free .xlsx writer.
 *
 * WHY THIS EXISTS
 * ---------------
 * The owner must be able to download a departing parish pastor's records as
 * spreadsheets. The project has no spreadsheet library in composer.json, and
 * adding one to satisfy a single export would mean a new third-party
 * dependency and a lockfile change for every deployment.
 *
 * An .xlsx file is a ZIP of XML documents, and PHP's ZipArchive is already
 * available here, so this class builds the smallest correct one: one sheet,
 * a header row, string and number cells. That is exactly what an export
 * needs — no formulas, styles, charts or pivot tables.
 *
 * DELIBERATELY LIMITED, ON PURPOSE
 * --------------------------------
 *   - Strings are written as INLINE strings rather than a shared-string
 *     table. A shared table is smaller, but inline strings mean the file can
 *     be written in one pass without buffering every distinct value, and
 *     Excel/LibreOffice/Sheets all read them.
 *   - Numbers are written as real numeric cells so a spreadsheet can SUM and
 *     sort them. Anything that is not a finite number is written as text,
 *     because a numeric cell containing "abc" makes Excel report a repair.
 *   - Every string is XML-escaped and stripped of the control characters XML
 *     1.0 forbids. A member's notes field containing a stray 0x0B would
 *     otherwise produce a file that fails to open — which is worse than an
 *     export that is merely ugly.
 */
class XlsxWriter
{
    /** Excel's hard limit on rows; a sheet beyond this cannot be written. */
    public const MAX_ROWS = 1048576;

    /** Excel's hard limit on columns (XFD). */
    public const MAX_COLUMNS = 16384;

    /**
     * Build a single-sheet workbook and return it as bytes.
     *
     * @param  string  $sheetName  shown on the tab; must be legal for Excel
     * @param  array<int, string>  $header  column headings
     * @param  iterable<int, array<int, mixed>>  $rows  row values, in header order
     */
    public function make(string $sheetName, array $header, iterable $rows): string
    {
        if (count($header) > self::MAX_COLUMNS) {
            throw new RuntimeException('Too many columns for a spreadsheet sheet.');
        }

        $path = $this->tempPath();

        try {
            $zip = new ZipArchive;

            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the spreadsheet file.');
            }

            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRels());
            $zip->addFromString('xl/workbook.xml', $this->workbook($sheetName));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($header, $rows));

            $zip->close();

            $bytes = file_get_contents($path);

            if ($bytes === false) {
                throw new RuntimeException('Unable to read the written spreadsheet file.');
            }

            return $bytes;
        } finally {
            // The temp file is scratch space only — never a record. Cleaning it
            // up here means a failed export cannot leave anything behind.
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** A filesystem path in the system temp directory, not the tenant's storage. */
    protected function tempPath(): string
    {
        return tempnam(sys_get_temp_dir(), 'cfxlsx').'.xlsx';
    }

    /** @param  iterable<int, array<int, mixed>>  $rows */
    protected function sheet(array $header, iterable $rows): string
    {
        $body = '<row r="1">';

        foreach (array_values($header) as $index => $heading) {
            $body .= $this->stringCell($this->column($index + 1).'1', (string) $heading);
        }

        $body .= '</row>';

        $rowNumber = 2;
        $lastColumn = count($header) > 0 ? $this->column(count($header)).'1' : 'A1';

        foreach ($rows as $row) {
            if ($rowNumber > self::MAX_ROWS) {
                throw new RuntimeException('A spreadsheet sheet cannot hold more than '.self::MAX_ROWS.' rows.');
            }

            $body .= '<row r="'.$rowNumber.'">';

            foreach (array_values($row) as $index => $value) {
                $reference = $this->column($index + 1).$rowNumber;
                $body .= $this->cell($reference, $value);
            }

            $body .= '</row>';
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastColumn.'"/>'
            .'<sheetData>'.$body.'</sheetData>'
            .'</worksheet>';
    }

    /** A cell holding the value the way a reader should see it. */
    protected function cell(string $reference, mixed $value): string
    {
        if ($value === null) {
            return ''; // an empty cell is simply absent
        }

        if (is_bool($value)) {
            return '<c r="'.$reference.'" t="b"><v>'.($value ? '1' : '0').'</v></c>';
        }

        // Numeric only when it is genuinely a number. A leading zero (a phone
        // number, a membership number like "00012") must stay text or Excel
        // silently drops the zeros — which corrupts the record.
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"><v>'.$value.'</v></c>';
        }

        return $this->stringCell($reference, (string) $value);
    }

    protected function stringCell(string $reference, string $value): string
    {
        return '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape($value)
            .'</t></is></c>';
    }

    /**
     * Escape for XML AND drop characters XML 1.0 cannot represent.
     *
     * `htmlspecialchars` alone is not enough: control characters such as
     * 0x00–0x08 are illegal in XML even when escaped, and Excel responds by
     * refusing to open the workbook.
     */
    protected function escape(string $value): string
    {
        // Valid XML 1.0 characters only: tab, newline, carriage return, and
        // everything from 0x20 up (excluding the surrogate range).
        $value = (string) preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** 1 → A, 26 → Z, 27 → AA. */
    protected function column(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    /** A sheet name Excel will accept: ≤31 chars, none of the reserved characters. */
    public function sheetName(string $name): string
    {
        $clean = (string) preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', '-', $name);
        $clean = trim($clean);

        if ($clean === '') {
            $clean = 'Sheet1';
        }

        return mb_substr($clean, 0, 31);
    }

    protected function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    protected function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    protected function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape($this->sheetName($sheetName)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    protected function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }
}
