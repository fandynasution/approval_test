<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConvertController extends Controller
{
    public function export($bg = '', $dept_cd = '')
    {
        $results = DB::connection('BTID')->select("
            select * from mgr.v_gl_budget_export
            where ver_cd = ? and dept_cd = ?
            order by entity_cd, dept_cd
        ", [$bg, $dept_cd]);

        if (empty($results)) {
            return response()->json(['error' => 'No data found'], 404);
        }

        $entityCd = $results[0]->entity_cd ?? 'UNKNOWN';

        $dept_descs = $results[0]->dept_descs ?? 'UNKNOWN';

        $initial_company = $results[0]->initial_company ?? 'COMBINE';

        $grouped = collect($results)->groupBy('entity_cd');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(); // disini
        

        // $row = 3;
        $row = 1;

        foreach ($grouped as $entityCd => $items) {
           $startRow = $row;

            // Header
            $sheet->fromArray([
                'Version Code', 'Acct Code', 'Acct Name', 'Div Name', 'Dept Name',
                'Budget Amount', 'Commit Amount', 'Actual Amount', 'Balance Amount', 'Progress %'
            ], null, "A{$row}");
            $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
            $row++;

            // Group by dept_cd per entity
            $deptGroups = collect($items)->groupBy('dept_cd');

            foreach ($deptGroups as $dept => $records) {
                // $totals = ['base_budget' => 0, 'commit_amt' => 0, 'todate_amt' => 0, 'balance' => 0,'progress' => 0,];
                $totals = ['base_budget' => 0, 'commit_amt' => 0, 'todate_amt' => 0, 'balance' => 0];

                foreach ($records as $item) {

                    // tambah
                    $base_budget = (float) $item->base_budget;
                    $commit_amt  = (float) $item->commit_amt;
                    $todate_amt  = (float) $item->todate_amt;
                    $balance     = (float) $item->balance;
                    // tambah

                    $progress = 0;
                    if ($base_budget > 0) {
                        $progress = ($commit_amt + $todate_amt) / $base_budget;
                    }

                    $sheet->setCellValue("A{$row}", $item->ver_cd);
                    $sheet->setCellValue("B{$row}", $item->acct_cd);
                    $sheet->setCellValue("C{$row}", $item->acct_descs);
                    $sheet->setCellValue("D{$row}", $item->div_descs);
                    $sheet->setCellValue("E{$row}", $item->dept_descs);

                    // angka tetap dipaksa 0.00 kalau nol
                    $sheet->setCellValue("F{$row}", $base_budget);
                    $sheet->setCellValue("G{$row}", $commit_amt);
                    $sheet->setCellValue("H{$row}", $todate_amt);
                    $sheet->setCellValue("I{$row}", $balance);

                    $sheet->setCellValue("J{$row}", $progress);

                    $sheet->getStyle("F{$row}:I{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');

                    // Format persentase
                    $sheet->getStyle("J{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('0.00%');

                    // Totals
                    $totals['base_budget'] += $base_budget;
                    $totals['commit_amt'] += $commit_amt;
                    $totals['todate_amt'] += $todate_amt;
                    $totals['balance'] += $balance;

                    $row++;
                }

                // Hitung total progress dari totals
                $totalProgress = 0;
                if ($totals['base_budget'] > 0) {
                    $totalProgress = ($totals['commit_amt'] + $totals['todate_amt']) / $totals['base_budget'];
                }

                // Tambahkan baris total progress
                $sheet->fromArray([
                    '', '', 'TOTAL', '', '',
                    number_format($totals['base_budget'], 2, '.', ','),
                    number_format($totals['commit_amt'], 2, '.', ','),
                    number_format($totals['todate_amt'], 2, '.', ','),
                    number_format($totals['balance'], 2, '.', ','),
		    number_format($totalProgress, 2, '.', ','),
                ], null, "A{$row}");

                $sheet->getStyle("F{$row}:I{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
                $sheet->getStyle("J{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
                
                $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);

                $row++;

                //Border & stripe Style
                $endRow = $row - 1; // gunakan -1 agar baris akhir mencakup semua data
                $tableRange = "A{$startRow}:J{$endRow}";

                // Tambahkan border
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Tambahkan tabel (opsional, ini hanya kosmetik di Excel)
                $table = new \PhpOffice\PhpSpreadsheet\Worksheet\Table($tableRange);
                $tableStyle = new \PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle();
                $tableStyle->setShowRowStripes(true);
                $table->setStyle($tableStyle);
                $sheet->addTable($table);
            }
        }

        // Auto size
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Generate file name
        $filename = "{$initial_company}_{$dept_cd}.xlsx";
        $filepath = storage_path("app/{$filename}");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        // Ambil data FTP dari database
        $ftpConfig = DB::connection('BTID')->table('mgr.ftp_spec')->first();

        if (!$ftpConfig) {
            return response()->json(['error' => 'FTP configuration not found.'], 500);
        }

        // Koneksi ke FTP
        $ftpConn = ftp_connect($ftpConfig->FTPServer, $ftpConfig->FTPPort, 30);
        if (!$ftpConn) {
            return response()->json(['error' => 'Could not connect to FTP server.'], 500);
        }

        $login = ftp_login($ftpConn, 'ifca_one', '1fc41fc4');
        if (!$login) {
            ftp_close($ftpConn);
            return response()->json(['error' => 'FTP login failed.'], 500);
        }

        ftp_pasv($ftpConn, true);

        // === Deteksi root folder user FTP ===
        $rootDir = ftp_pwd($ftpConn);
        if ($rootDir === false) {
            ftp_close($ftpConn);
            return response()->json(['error' => 'Could not detect FTP root directory.'], 500);
        }

        // Pastikan kita berada di root
        if (!ftp_chdir($ftpConn, $rootDir)) {
            ftp_close($ftpConn);
            return response()->json(['error' => 'Could not change to FTP root directory.'], 500);
        }

        // === Cek file lokal sebelum upload ===
        if (!file_exists($filepath)) {
            ftp_close($ftpConn);
            return response()->json(['error' => "Local file not found: $filepath"], 500);
        }

        if (!is_readable($filepath)) {
            ftp_close($ftpConn);
            return response()->json(['error' => "Local file is not readable: $filepath"], 500);
        }

        // Nama file di FTP root
        $remoteFile = $filename ?? basename($filepath);

        // === Upload file ke root folder ===
        if (!ftp_put($ftpConn, $remoteFile, $filepath, FTP_BINARY)) {
            ftp_close($ftpConn);
            return response()->json(['error' => 'File upload failed.'], 500);
        }

        // Jika berhasil upload, hapus file lokal
        if (!unlink($filepath)) {
            ftp_close($ftpConn);
            return response()->json(['error' => "File uploaded but failed to delete local file: $filepath"], 500);
        }

        // Tutup koneksi FTP
        ftp_close($ftpConn);

        $existing = DB::connection('BTID')->table('mgr.export_log')
            ->where('ver_cd', $bg)
            ->where('file_name', $filename)
            ->where('dept_cd', $dept_cd)
            ->where('entity_cd', $entityCd)
            ->first();

        $data = [
            'file_url' => $filename.'-Backup to One Drive',
            'audit_date' => DB::raw('GETDATE()'),
        ];

        if ($existing) {
            // Update
            DB::connection('BTID')->table('mgr.export_log')
                ->where('ver_cd', $bg)
                ->where('file_name', $filename)
                ->where('dept_cd', $dept_cd)
                ->where('entity_cd', $entityCd)
                ->update($data);
        } else {
            // Insert
            DB::connection('BTID')->table('mgr.export_log')
                ->insert(array_merge([
                    'ver_cd' => $bg, 
                    'file_name' => $filename, 
                    'dept_cd' => $dept_cd, 
                    'entity_cd' => $entityCd
                ], $data));
        }

        return response()->json([
            'success' => 'File uploaded and local file deleted successfully.',
            'ftp_file' => $remoteFile
        ]);
    }
}
