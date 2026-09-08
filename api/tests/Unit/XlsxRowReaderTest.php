<?php

namespace Tests\Unit;

use App\Modules\Account\Support\XlsxRowReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class XlsxRowReaderTest extends TestCase
{
    public function test_reads_base_de_dados_and_stops_after_empty_rows(): void
    {
        $spreadsheet = new Spreadsheet;
        $types = $spreadsheet->getActiveSheet();
        $types->setTitle('LISTA DE TIPOS DE DESPESAS');
        $types->fromArray([['TIPO DE DESPESA'], ['Condominio'], ['Areia']]);

        $data = $spreadsheet->createSheet();
        $data->setTitle('BASE DE DADOS');
        $data->fromArray([
            ['EMPRESA', 'GRUPO', 'TIPO DE DESPESA', 'VENCIMENTO', 'STATUS'],
            ['ATMOSPHERE 35', 'DESPESAS FIXAS', 'Condominio', '10/01/2026', 'Pago'],
            ['ATMOSPHERE 35', 'MATERIAIS', 'Cimento', '10/02/2026', 'A Vencer'],
        ]);

        for ($row = 4; $row <= 80; $row++) {
            $data->setCellValue("A{$row}", null);
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx_reader_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $rows = (new XlsxRowReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(3, $rows);
        $this->assertSame('EMPRESA', $rows[0][0]);
        $this->assertSame('ATMOSPHERE 35', $rows[1][0]);
        $this->assertSame('Cimento', $rows[2][2]);
    }

    public function test_reads_atm_spreadsheet_without_loading_million_empty_rows(): void
    {
        $path = '/mnt/c/users/kaiut/Downloads/apolar/2_ATM 35.xlsx';

        if (! is_readable($path)) {
            $this->markTestSkipped('Planilha ATM 35 não está disponível neste ambiente.');
        }

        $rows = (new XlsxRowReader)->read($path);

        $this->assertGreaterThan(100, count($rows));
        $this->assertLessThan(400, count($rows));
        $this->assertSame('EMPRESA', $rows[0][0]);
        $this->assertSame('GRUPO', $rows[0][1]);
    }
}
