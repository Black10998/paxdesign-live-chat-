<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Export {
    public static function send($type, $format) {
        $type = sanitize_key($type);
        $format = sanitize_key($format);
        $rows = self::rows($type);
        if (is_wp_error($rows)) {
            return $rows;
        }
        $filename = 'albatros-' . $type . '-' . gmdate('Ymd-His');
        if ($format === 'xlsx') {
            self::send_excel($filename . '.xls', $rows['headers'], $rows['rows']);
            return true;
        }
        if ($format === 'pdf') {
            self::send_pdf($rows['title'], $rows['headers'], $rows['rows']);
            return true;
        }
        self::send_csv($filename . '.csv', $rows['headers'], $rows['rows']);
        return true;
    }

    private static function rows($type) {
        if ($type === 'drivers') {
            $data = Alb_Drivers::query(array('per_page' => 200, 'page' => 1));
            $headers = array('ID', Alb_I18n::t('driver.first_name'), Alb_I18n::t('driver.last_name'), Alb_I18n::t('driver.phone'), Alb_I18n::t('driver.email'), Alb_I18n::t('driver.employee_code'), Alb_I18n::t('branch.label'), Alb_I18n::t('common.status'));
            $rows = array();
            foreach ($data['items'] as $item) {
                $rows[] = array($item['id'], $item['first_name'], $item['last_name'], $item['phone'], $item['email'], $item['employee_code'], $item['branch_label'], $item['status']);
            }
            return array('title' => Alb_I18n::t('reports.drivers'), 'headers' => $headers, 'rows' => $rows);
        }
        if ($type === 'handovers') {
            $items = Alb_Scanners::recent_handovers(200);
            $headers = array(Alb_I18n::t('common.date'), Alb_I18n::t('scanner.code'), Alb_I18n::t('scanner.serial'), Alb_I18n::t('scanner.driver'), Alb_I18n::t('common.action'));
            $rows = array();
            foreach ($items as $item) {
                $rows[] = array($item['at_display'], $item['scanner_code'], $item['serial_number'], $item['driver_name'], $item['action']);
            }
            return array('title' => Alb_I18n::t('reports.handovers'), 'headers' => $headers, 'rows' => $rows);
        }
        $args = array('per_page' => 200, 'page' => 1);
        if ($type === 'lost') {
            $args['status'] = 'lost';
        }
        $data = Alb_Scanners::query($args);
        $headers = array(
            'ID',
            Alb_I18n::t('scanner.code'),
            Alb_I18n::t('scanner.brand'),
            Alb_I18n::t('scanner.model'),
            Alb_I18n::t('scanner.serial'),
            Alb_I18n::t('scanner.phone'),
            Alb_I18n::t('branch.label'),
            Alb_I18n::t('scanner.driver'),
            Alb_I18n::t('scanner.handover_date'),
            Alb_I18n::t('common.status'),
        );
        $rows = array();
        foreach ($data['items'] as $item) {
            $rows[] = array(
                $item['id'],
                $item['scanner_code'],
                $item['brand'],
                $item['model'],
                $item['serial_number'],
                $item['phone_number'],
                $item['branch_label'],
                $item['driver_name'],
                $item['handover_date_display'],
                $item['status'],
            );
        }
        $title = $type === 'lost' ? Alb_I18n::t('reports.lost') : Alb_I18n::t('reports.scanners');
        return array('title' => $title, 'headers' => $headers, 'rows' => $rows);
    }

    private static function send_csv($filename, $headers, $rows) {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    private static function send_excel($filename, $headers, $rows) {
        nocache_headers();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo '<table border="1"><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . esc_html((string) $cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        exit;
    }

    private static function send_pdf($title, $headers, $rows) {
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html($title) . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;font-size:12px;color:#222;margin:24px}h1{font-size:16px;margin:0 0 12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}th{background:#f3f3f3}@media print{button{display:none}}</style></head><body>';
        echo '<button onclick="window.print()">' . esc_html(Alb_I18n::t('reports.pdf')) . '</button>';
        echo '<h1>' . esc_html($title) . '</h1><table><thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . esc_html((string) $cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }
}
