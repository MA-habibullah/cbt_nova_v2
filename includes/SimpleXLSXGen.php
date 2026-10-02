<?php
/**
 * SimpleXLSXGen class
 * Lightweight, zero-dependency OpenXML XLSX generator for PHP 7.4 - 8.x
 * Generates genuine Microsoft Excel .xlsx files with formatting, types, and styles.
 *
 * @author Sergey Shuchkin <sergey.shuchkin@gmail.com>
 * @license MIT
 */

namespace Shuchkin;

class SimpleXLSXGen {

    public array $sheets = [];
    public array $template = [];
    protected array $NF = [];
    protected array $NF_KEYS = [];
    protected array $XF = [];
    protected array $XF_KEYS = [];
    protected array $BR = [];
    protected array $BR_KEYS = [];
    protected array $SI = [];
    protected array $SI_KEYS = [];
    protected array $F = [];
    protected array $F_KEYS = [];
    protected array $A = [];
    protected array $A_KEYS = [];
    protected array $C = [];
    protected array $C_KEYS = [];

    public function __construct() {
        $this->template = [
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
<TotalTime>0</TotalTime>
<Application>CBT Nova XLSX Generator</Application>
</Properties>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>CBT Nova</dc:creator>
<cp:lastModifiedBy>CBT Nova</cp:lastModifiedBy>
<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:modified>
</cp:coreProperties>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
{SHEETS}
</Relationships>',
            'xl/worksheets/sheet1.xml' => '',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<fileVersion appName="Calc"/>
<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="16384" windowHeight="8192" tabRatio="500"/></bookViews>
<sheets>{SHEETS}</sheets>
<calcPr iterateCount="100" refMode="A1"/>
</workbook>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
{NUMFMT}
{FONTS}
{FILLS}
{BORDERS}
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
{XFS}
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>',
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Override PartName="/_rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/xl/_rels/workbook.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
{SHEETS}
</Types>'
        ];

        // Default styles
        $this->addFont(['name' => 'Calibri', 'size' => 11, 'color' => '000000']);
        $this->addFill('none');
        $this->addFill('gray125');
        $this->addBorder([]);
        $this->addXF(['fontId' => 0, 'fillId' => 0, 'borderId' => 0]);
    }

    public static function fromArray(array $rows, ?string $sheetName = null): self {
        $xlsx = new static();
        $xlsx->addSheet($rows, $sheetName);
        return $xlsx;
    }

    public function addSheet(array $rows, ?string $name = null, array $mergeCells = []): self {
        $this->sheets[] = [
            'name' => $name ?: 'Sheet' . (count($this->sheets) + 1),
            'rows' => $rows,
            'mergeCells' => $mergeCells
        ];
        return $this;
    }

    public function downloadAs(string $filename): void {
        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        echo $this->generate();
        exit;
    }

    public function saveAs(string $filename): bool {
        return (bool)file_put_contents($filename, $this->generate());
    }

    public function generate(): string {
        $temp_file = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new \ZipArchive();
        if ($zip->open($temp_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create temporary zip archive');
        }

        $sheet_rels = '';
        $sheets_meta = '';
        $content_types = '';

        foreach ($this->sheets as $idx => $sheetData) {
            $sheet_idx = $idx + 1;
            $rId = 'rId' . ($sheet_idx + 1);
            $sheet_path = 'xl/worksheets/sheet' . $sheet_idx . '.xml';

            $sheet_rels .= '<Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheet_idx . '.xml"/>' . "\n";
            $sheets_meta .= '<sheet name="' . htmlspecialchars($sheetData['name'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '" sheetId="' . $sheet_idx . '" r:id="' . $rId . '"/>' . "\n";
            $content_types .= '<Override PartName="/' . $sheet_path . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n";

            $sheet_xml = $this->buildSheetXML($sheetData['rows'], $sheetData['mergeCells'] ?? []);
            $zip->addFromString($sheet_path, $sheet_xml);
        }

        $template = $this->template;
        $template['xl/_rels/workbook.xml.rels'] = str_replace('{SHEETS}', trim($sheet_rels), $template['xl/_rels/workbook.xml.rels']);
        $template['xl/workbook.xml'] = str_replace('{SHEETS}', trim($sheets_meta), $template['xl/workbook.xml']);
        $template['[Content_Types].xml'] = str_replace('{SHEETS}', trim($content_types), $template['[Content_Types].xml']);

        $template['xl/styles.xml'] = $this->buildStylesXML($template['xl/styles.xml']);

        foreach ($template as $name => $xml) {
            if ($name !== 'xl/worksheets/sheet1.xml') {
                $zip->addFromString($name, $xml);
            }
        }

        $zip->close();
        $content = file_get_contents($temp_file);
        @unlink($temp_file);
        return $content;
    }

    protected function buildSheetXML(array $rows, array $mergeCells = []): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
        
        // Hitung max columns
        $max_cols = 0;
        foreach ($rows as $r) {
            if (is_array($r)) {
                $max_cols = max($max_cols, count($r));
            }
        }
        
        if ($max_cols > 0) {
            $xml .= '<cols>';
            for ($c = 1; $c <= $max_cols; $c++) {
                $width = 15;
                if ($c === 1) $width = 6;      // No
                if ($c === 2) $width = 18;     // NISN
                if ($c === 3) $width = 32;     // Nama
                if ($c === 4) $width = 16;     // Kelas
                if ($c === 5) $width = 25;     // Mapel
                if ($c === 6) $width = 10;     // Sesi
                if ($c >= 7 && $c <= 8) $width = 14;  // Benar Obj / Esai
                if ($c >= 9 && $c <= 11) $width = 18; // Nilai Obj / Esai / Akhir
                if ($c === 12) $width = 16;    // Status
                $xml .= '<col min="' . $c . '" max="' . $c . '" width="' . $width . '" customWidth="1"/>';
            }
            $xml .= '</cols>' . "\n";
        }

        $xml .= '<sheetData>' . "\n";

        $row_num = 1;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $row = [$row];
            }
            $xml .= '<row r="' . $row_num . '">' . "\n";
            $col_idx = 0;

            foreach ($row as $cell) {
                $col_letter = $this->colNumberToLetter($col_idx + 1);
                $cell_ref = $col_letter . $row_num;
                
                // Parse Cell definition: string, number, or array with style
                $val = $cell;
                $style_xf = 0;
                $is_text_explicit = false;

                if (is_array($cell)) {
                    $val = $cell['v'] ?? ($cell[0] ?? '');
                    $style_xf = $cell['s'] ?? ($cell[1] ?? 0);
                    if (!empty($cell['t']) && $cell['t'] === 's') {
                        $is_text_explicit = true;
                    }
                }

                if ($val === null || $val === '') {
                    $xml .= '<c r="' . $cell_ref . '"' . ($style_xf ? ' s="' . $style_xf . '"' : '') . '/>';
                } elseif (is_numeric($val) && !$is_text_explicit && !preg_match('/^0[0-9]+/', (string)$val)) {
                    // Murni angka (integer/float)
                    $xml .= '<c r="' . $cell_ref . '" t="n"' . ($style_xf ? ' s="' . $style_xf . '"' : '') . '><v>' . $val . '</v></c>';
                } else {
                    // Tipe String eksplisit (termasuk NISN, Kelas "10-1", dll.)
                    $escaped = htmlspecialchars((string)$val, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    $xml .= '<c r="' . $cell_ref . '" t="inlineStr"' . ($style_xf ? ' s="' . $style_xf . '"' : '') . '><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
                }
                $col_idx++;
            }

            $xml .= '</row>' . "\n";
            $row_num++;
        }

        $xml .= '</sheetData>' . "\n";

        if (!empty($mergeCells)) {
            $xml .= '<mergeCells count="' . count($mergeCells) . '">' . "\n";
            foreach ($mergeCells as $ref) {
                $xml .= '<mergeCell ref="' . htmlspecialchars($ref, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"/>' . "\n";
            }
            $xml .= '</mergeCells>' . "\n";
        }

        $xml .= '</worksheet>';
        return $xml;
    }

    protected function buildStylesXML(string $template): string {
        $num_fmt_xml = '<numFmts count="' . count($this->NF) . '">' . "\n";
        foreach ($this->NF as $id => $fmt) {
            $num_fmt_xml .= '<numFmt numFmtId="' . $id . '" formatCode="' . htmlspecialchars($fmt, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
        }
        $num_fmt_xml .= '</numFmts>';

        $fonts_xml = '<fonts count="' . count($this->F) . '">' . "\n";
        foreach ($this->F as $font) {
            $fonts_xml .= '<font>';
            if (!empty($font['bold'])) $fonts_xml .= '<b/>';
            if (!empty($font['italic'])) $fonts_xml .= '<i/>';
            $fonts_xml .= '<sz val="' . ($font['size'] ?? 11) . '"/>';
            $fonts_xml .= '<color rgb="FF' . ($font['color'] ?? '000000') . '"/>';
            $fonts_xml .= '<name val="' . ($font['name'] ?? 'Calibri') . '"/>';
            $fonts_xml .= '</font>' . "\n";
        }
        $fonts_xml .= '</fonts>';

        $fills_xml = '<fills count="' . count($this->SI) . '">' . "\n";
        foreach ($this->SI as $fill) {
            if ($fill === 'none') {
                $fills_xml .= '<fill><patternFill patternType="none"/></fill>' . "\n";
            } elseif ($fill === 'gray125') {
                $fills_xml .= '<fill><patternFill patternType="gray125"/></fill>' . "\n";
            } else {
                $fills_xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . strtoupper($fill) . '"/><bgColor indexed="64"/></patternFill></fill>' . "\n";
            }
        }
        $fills_xml .= '</fills>';

        $borders_xml = '<borders count="' . count($this->BR) . '">' . "\n";
        foreach ($this->BR as $b) {
            $borders_xml .= '<border>';
            $borders_xml .= !empty($b['left'])   ? '<left style="thin"><color rgb="FF' . ($b['left'] === true ? '999999' : $b['left']) . '"/></left>' : '<left/>';
            $borders_xml .= !empty($b['right'])  ? '<right style="thin"><color rgb="FF' . ($b['right'] === true ? '999999' : $b['right']) . '"/></right>' : '<right/>';
            $borders_xml .= !empty($b['top'])    ? '<top style="thin"><color rgb="FF' . ($b['top'] === true ? '999999' : $b['top']) . '"/></top>' : '<top/>';
            $borders_xml .= !empty($b['bottom']) ? '<bottom style="thin"><color rgb="FF' . ($b['bottom'] === true ? '999999' : $b['bottom']) . '"/></bottom>' : '<bottom/>';
            $borders_xml .= '<diagonal/>';
            $borders_xml .= '</border>' . "\n";
        }
        $borders_xml .= '</borders>';

        $xfs_xml = '<cellXfs count="' . count($this->XF) . '">' . "\n";
        foreach ($this->XF as $xf) {
            $xfs_xml .= '<xf numFmtId="' . ($xf['numFmtId'] ?? 0) . '" fontId="' . ($xf['fontId'] ?? 0) . '" fillId="' . ($xf['fillId'] ?? 0) . '" borderId="' . ($xf['borderId'] ?? 0) . '" xfId="0"';
            $align = '';
            if (!empty($xf['align']) || !empty($xf['valign']) || !empty($xf['wrap'])) {
                $align = '<alignment';
                if (!empty($xf['align']))  $align .= ' horizontal="' . $xf['align'] . '"';
                if (!empty($xf['valign'])) $align .= ' vertical="' . $xf['valign'] . '"';
                if (!empty($xf['wrap']))   $align .= ' wrapText="1"';
                $align .= '/>';
            }
            if ($align) {
                $xfs_xml .= ' applyAlignment="1">' . $align . '</xf>' . "\n";
            } else {
                $xfs_xml .= '/>' . "\n";
            }
        }
        $xfs_xml .= '</cellXfs>';

        $template = str_replace('{NUMFMT}', $num_fmt_xml, $template);
        $template = str_replace('{FONTS}', $fonts_xml, $template);
        $template = str_replace('{FILLS}', $fills_xml, $template);
        $template = str_replace('{BORDERS}', $borders_xml, $template);
        $template = str_replace('{XFS}', $xfs_xml, $template);

        return $template;
    }

    public function addFont(array $font): int {
        $key = serialize($font);
        if (isset($this->F_KEYS[$key])) {
            return $this->F_KEYS[$key];
        }
        $idx = count($this->F);
        $this->F[$idx] = $font;
        $this->F_KEYS[$key] = $idx;
        return $idx;
    }

    public function addFill(string $color): int {
        $color = ltrim(strtoupper($color), '#');
        if (isset($this->SI_KEYS[$color])) {
            return $this->SI_KEYS[$color];
        }
        $idx = count($this->SI);
        $this->SI[$idx] = $color;
        $this->SI_KEYS[$color] = $idx;
        return $idx;
    }

    public function addBorder(array $b): int {
        $key = serialize($b);
        if (isset($this->BR_KEYS[$key])) {
            return $this->BR_KEYS[$key];
        }
        $idx = count($this->BR);
        $this->BR[$idx] = $b;
        $this->BR_KEYS[$key] = $idx;
        return $idx;
    }

    public function addXF(array $xf): int {
        $key = serialize($xf);
        if (isset($this->XF_KEYS[$key])) {
            return $this->XF_KEYS[$key];
        }
        $idx = count($this->XF);
        $this->XF[$idx] = $xf;
        $this->XF_KEYS[$key] = $idx;
        return $idx;
    }

    public function createStyle(array $opt): int {
        $fontId = 0;
        if (!empty($opt['bold']) || !empty($opt['size']) || !empty($opt['color']) || !empty($opt['font'])) {
            $fontId = $this->addFont([
                'name' => $opt['font'] ?? 'Calibri',
                'size' => $opt['size'] ?? 11,
                'bold' => !empty($opt['bold']),
                'color' => $opt['color'] ?? '000000'
            ]);
        }
        $fillId = 0;
        if (!empty($opt['bg'])) {
            $fillId = $this->addFill($opt['bg']);
        }
        $borderId = 0;
        if (!empty($opt['border'])) {
            $borderId = $this->addBorder([
                'left' => true, 'right' => true, 'top' => true, 'bottom' => true
            ]);
        }
        return $this->addXF([
            'fontId' => $fontId,
            'fillId' => $fillId,
            'borderId' => $borderId,
            'align' => $opt['align'] ?? null,
            'valign' => $opt['valign'] ?? 'center'
        ]);
    }

    protected function colNumberToLetter(int $n): string {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = (int)(($n - $m) / 26);
        }
        return $s;
    }
}
