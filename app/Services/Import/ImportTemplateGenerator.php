<?php

namespace App\Services\Import;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateGenerator
{
    /**
     * Define template schemas and sample rows.
     *
     * @return array{headers: array<string>, sample_rows: array<int, array<string, mixed>>}
     */
    public function getTemplateData(string $target): array
    {
        return match ($target) {
            'items' => [
                'headers' => [
                    'sku',
                    'name',
                    'description',
                    'category',
                    'unit',
                    'unit_cost',
                    'reorder_level',
                    'safety_stock',
                    'critical_level',
                    'expiry_alert_days',
                    'default_location',
                    'requires_cold_chain',
                    'is_dangerous_drug',
                ],
                'sample_rows' => [
                    [
                        'sku' => 'MED-PARA-500',
                        'name' => 'Paracetamol 500mg Tablet',
                        'description' => 'Analgesic and antipyretic oral tablet',
                        'category' => 'Medicines & Pharmaceuticals',
                        'unit' => 'tablet',
                        'unit_cost' => '1.50',
                        'reorder_level' => '500',
                        'safety_stock' => '200',
                        'critical_level' => '100',
                        'expiry_alert_days' => '30',
                        'default_location' => 'Main Store',
                        'requires_cold_chain' => 'no',
                        'is_dangerous_drug' => 'no',
                    ],
                    [
                        'sku' => 'BIO-RAB-01',
                        'name' => 'Rabies Vaccine 2.5 IU / mL',
                        'description' => 'Purified Vero cell rabies biological vaccine',
                        'category' => 'Vaccines & Biologicals',
                        'unit' => 'vial',
                        'unit_cost' => '850.00',
                        'reorder_level' => '50',
                        'safety_stock' => '20',
                        'critical_level' => '10',
                        'expiry_alert_days' => '60',
                        'default_location' => 'Cold Chain Storage',
                        'requires_cold_chain' => 'yes',
                        'is_dangerous_drug' => 'no',
                    ],
                ],
            ],
            'locations' => [
                'headers' => [
                    'code',
                    'name',
                    'type',
                    'zone',
                    'capacity',
                    'storage_classification',
                    'temperature_classification',
                    'status',
                ],
                'sample_rows' => [
                    [
                        'code' => 'MAIN-ZONE-A',
                        'name' => 'Main Warehouse Zone A - Fast Movers',
                        'type' => 'warehouse',
                        'zone' => 'Zone A',
                        'capacity' => '5000',
                        'storage_classification' => 'ambient',
                        'temperature_classification' => 'ambient',
                        'status' => 'active',
                    ],
                    [
                        'code' => 'COLD-VAULT-01',
                        'name' => 'Pharmacy Cold Vault Room 1 (2°C - 8°C)',
                        'type' => 'cold_vault',
                        'zone' => 'Cold Chain',
                        'capacity' => '1200',
                        'storage_classification' => 'cold_chain',
                        'temperature_classification' => 'refrigerated',
                        'status' => 'active',
                    ],
                ],
            ],
            'suppliers' => [
                'headers' => [
                    'name',
                    'contact_person',
                    'email',
                    'phone',
                    'address',
                    'tax_number',
                    'payment_terms',
                    'standard_lead_time_days',
                ],
                'sample_rows' => [
                    [
                        'name' => 'Zuellig Pharma Corporation',
                        'contact_person' => 'Maria Santos (Account Exec)',
                        'email' => 'hospital.sales@zuelligpharma.com',
                        'phone' => '+63 2 8900 1234',
                        'address' => 'Km 14 West Service Rd, Taguig, Metro Manila',
                        'tax_number' => '000-123-456-000',
                        'payment_terms' => '30 Days Net',
                        'standard_lead_time_days' => '7',
                    ],
                    [
                        'name' => 'Metro Drug Distribution Inc.',
                        'contact_person' => 'Juan Dela Cruz',
                        'email' => 'orders@metrodrug.com.ph',
                        'phone' => '+63 2 8845 6789',
                        'address' => 'Mañalac Ave, Bagumbayan, Taguig City',
                        'tax_number' => '001-987-654-000',
                        'payment_terms' => '45 Days Net',
                        'standard_lead_time_days' => '5',
                    ],
                ],
            ],
            default => throw new \InvalidArgumentException("Unknown target [{$target}]."),
        };
    }

    /**
     * Download CSV template.
     */
    public function downloadCsv(string $target): StreamedResponse
    {
        $data = $this->getTemplateData($target);
        $filename = "hims-{$target}-template.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, $data['headers']);
            foreach ($data['sample_rows'] as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download JSON template.
     */
    public function downloadJson(string $target): StreamedResponse
    {
        $data = $this->getTemplateData($target);
        $filename = "hims-{$target}-template.json";

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data['sample_rows'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download an Excel-compatible SpreadsheetML template.
     */
    public function downloadXls(string $target): StreamedResponse
    {
        $data = $this->getTemplateData($target);
        $filename = "hims-{$target}-template.xls";

        return response()->streamDownload(function () use ($data) {
            $numericColumns = [
                'unit_cost' => 'Decimal',
                'reorder_level' => 'Integer',
                'safety_stock' => 'Integer',
                'critical_level' => 'Integer',
                'expiry_alert_days' => 'Integer',
                'capacity' => 'Integer',
                'standard_lead_time_days' => 'Integer',
            ];
            $centeredColumns = ['requires_cold_chain', 'is_dangerous_drug', 'status'];
            $wrappedColumns = ['name', 'description', 'address', 'storage_classification', 'temperature_classification'];

            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles>';
            echo '<Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11"/><Alignment ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1F4E78" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="Text"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/></Borders></Style>';
            echo '<Style ss:ID="Wrapped"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/></Borders></Style>';
            echo '<Style ss:ID="Centered"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/></Borders></Style>';
            echo '<Style ss:ID="Integer"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><NumberFormat ss:Format="0"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/></Borders></Style>';
            echo '<Style ss:ID="Decimal"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><NumberFormat ss:Format="0.00"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/></Borders></Style>';
            echo '</Styles><Worksheet ss:Name="Template"><Table x:FullColumns="1" x:FullRows="1">';

            foreach ($data['headers'] as $header) {
                $values = array_column($data['sample_rows'], $header);
                $maxLength = max(array_map(fn ($value) => mb_strlen((string) $value), [$header, ...$values]));
                $width = min(240, max(72, ($maxLength * 6.5) + 18));
                echo '<Column ss:AutoFitWidth="0" ss:Width="'.number_format($width, 1, '.', '').'"/>';
            }

            echo '<Row ss:StyleID="Header" ss:Height="30">';
            foreach ($data['headers'] as $header) {
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($header, ENT_XML1, 'UTF-8').'</Data></Cell>';
            }
            echo '</Row>';

            foreach ($data['sample_rows'] as $row) {
                echo '<Row ss:AutoFitHeight="1">';
                foreach ($data['headers'] as $header) {
                    $value = $row[$header] ?? '';
                    $style = $numericColumns[$header]
                        ?? (in_array($header, $centeredColumns, true) ? 'Centered'
                            : (in_array($header, $wrappedColumns, true) ? 'Wrapped' : 'Text'));
                    $type = isset($numericColumns[$header]) && is_numeric($value) ? 'Number' : 'String';
                    echo '<Cell ss:StyleID="'.$style.'"><Data ss:Type="'.$type.'">'.htmlspecialchars((string) $value, ENT_XML1, 'UTF-8').'</Data></Cell>';
                }
                echo '</Row>';
            }

            $lastRow = count($data['sample_rows']) + 1;
            $lastColumn = count($data['headers']);
            echo '</Table><AutoFilter x:Range="R1C1:R'.$lastRow.'C'.$lastColumn.'" xmlns="urn:schemas-microsoft-com:office:excel"/>';
            echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><Selected/><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions>';
            echo '</Worksheet></Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
