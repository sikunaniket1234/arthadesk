<?php
namespace App\Services;

use App\Core\Logger;

class PdfService {
    public static function stream(string $html, string $filename, bool $attachment = false, ?array $paper = null): void {
        $filename = self::safeFilename($filename);

        if (class_exists('Dompdf\\Dompdf')) {
            try {
                $dompdf = new \Dompdf\Dompdf([
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont' => 'DejaVu Sans',
                ]);
                $dompdf->loadHtml($html, 'UTF-8');
                // $paper: [x, y, width, height] in points (e.g. thermal roll) or null for A4.
                $dompdf->setPaper($paper ?? 'A4', 'portrait');
                $dompdf->render();
                $dompdf->stream($filename, ['Attachment' => $attachment]);
                return;
            } catch (\Throwable $e) {
                Logger::error('pdf_render_failed', ['error' => $e->getMessage()], 'app');
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        echo self::printableHtml($html, $filename);
        exit;
    }

    public static function document(string $title, string $bodyHtml): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $safeTitle . '</title>'
            . '<style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;color:#0f172a;margin:24px;}'
            . 'table{width:100%;border-collapse:collapse;font-size:12px;}'
            . 'th,td{padding:8px;border-bottom:1px solid #e2e8f0;text-align:left;}'
            . 'th{background:#f8fafc;font-weight:700;}'
            . '.text-right{text-align:right;}'
            . '.muted{color:#64748b;}'
            . '.total{font-size:16px;font-weight:700;}'
            . '.no-print{display:none;}'
            . '</style></head><body>' . $bodyHtml . '</body></html>';
    }

    protected static function printableHtml(string $html, string $filename): string {
        return '<!doctype html><html><head><meta charset="utf-8"><title>'
            . htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</title><style>@media print{.no-print{display:none;}}body{font-family:Arial,sans-serif;margin:24px;}'
            . '.notice{background:#fff7ed;border:1px solid #fed7aa;padding:12px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#9a3412;}'
            . '</style></head><body>'
            . '<div class="notice no-print">PDF engine (dompdf) is not installed on this server. '
            . 'This printable page has been generated instead. Use your browser\'s Print → Save as PDF option. '
            . 'Run <code>composer install</code> to enable direct PDF export.</div>'
            . $html
            . '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},350);});</script>'
            . '</body></html>';
    }

    protected static function safeFilename(string $filename): string {
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'document.pdf';
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }
        return $filename;
    }
}
