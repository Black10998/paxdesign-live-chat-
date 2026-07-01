<?php
/**
 * HTML e-mail templates for PAXdesign Booking.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Email_Templates {

    /**
     * Resolve logo URL dynamically from settings, theme, or site icon.
     */
    public static function get_logo_url() {
        $override = trim((string) get_option('paxdesign_booking_logo_url', ''));
        if ($override !== '') {
            return esc_url($override);
        }

        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $image = wp_get_attachment_image_url($custom_logo_id, 'medium');
            if ($image) {
                return esc_url(apply_filters('paxdesign_booking_logo_url', $image));
            }
        }

        if (function_exists('get_site_icon_url')) {
            $site_icon = get_site_icon_url(256);
            if ($site_icon) {
                return esc_url(apply_filters('paxdesign_booking_logo_url', $site_icon));
            }
        }

        $fallback = apply_filters('paxdesign_booking_logo_url', '');
        if ($fallback) {
            return esc_url($fallback);
        }

        return esc_url(home_url('/'));
    }

    /**
     * Brand, contact, and link configuration for e-mails.
     */
    public static function get_brand_config() {
        $site_url = home_url('/');

        return array(
            'logo_url'      => self::get_logo_url(),
            'site_url'      => $site_url,
            'services_url'  => get_option('paxdesign_booking_services_url', $site_url),
            'contact_url'   => get_option('paxdesign_booking_contact_url', $site_url),
            'phone'         => get_option('paxdesign_booking_phone', '+43 681 20543638'),
            'email'         => get_option('paxdesign_booking_notification_email', 'info@paxdesign.at'),
            'company_name'  => 'PAXDesign',
            'legal_name'    => 'PAXdesign (PrimoJob GmbH)',
            'address'       => 'Franzensbrückenstraße 14, 1020 Wien',
            'company_intro' => 'PAXDesign entwickelt maßgeschneiderte digitale Lösungen — von Websites und Apps bis zu sicheren Enterprise-Systemen. Als inhabergeführtes Studio verbinden wir Design, Technologie und Strategie in einem klaren Prozess.',
            'social'        => array(
                'instagram' => trim((string) get_option('paxdesign_booking_social_instagram', '')),
                'linkedin'  => trim((string) get_option('paxdesign_booking_social_linkedin', '')),
                'facebook'  => trim((string) get_option('paxdesign_booking_social_facebook', '')),
            ),
        );
    }

    /**
     * Render service details as an HTML block.
     */
    public static function render_service_details_html($details) {
        if (empty($details) || empty($details['name'])) {
            return '';
        }

        $html  = self::section_title('Gewählter Service');
        $html .= '<p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#ffffff;line-height:1.35;">' . esc_html($details['name']) . '</p>';

        if (!empty($details['category'])) {
            $html .= '<p style="margin:0 0 10px;font-size:12px;color:#c2ff00;letter-spacing:0.04em;text-transform:uppercase;">' . esc_html($details['category']) . '</p>';
        }

        if (!empty($details['description'])) {
            $html .= '<p style="margin:0 0 12px;font-size:14px;line-height:1.65;color:#bdbdbd;">' . esc_html($details['description']) . '</p>';
        }

        if (!empty($details['features']) && is_array($details['features'])) {
            $html .= '<p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#ffffff;letter-spacing:0.04em;text-transform:uppercase;">Leistungsumfang</p>';
            $html .= '<ul style="margin:0;padding:0 0 0 18px;color:#e5e5e5;font-size:14px;line-height:1.6;">';
            foreach ($details['features'] as $feature) {
                $html .= '<li style="margin:0 0 6px;">' . esc_html($feature) . '</li>';
            }
            $html .= '</ul>';
        }

        return self::panel($html);
    }

    /**
     * Admin notification HTML.
     */
    public static function render_admin_booking($booking_data, $member_info, $parts) {
        $brand = self::get_brand_config();
        $details = isset($parts['service_details']) ? $parts['service_details'] : null;

        $body  = self::hero('Neue Terminbuchung', 'Eine neue Anfrage ist über das Booking-System eingegangen.');
        $body .= self::panel(
            self::kv('Kunde', $booking_data['name']) .
            self::kv('E-Mail', $booking_data['email'], 'mailto:' . $booking_data['email']) .
            self::kv('Telefon', !empty($booking_data['phone']) ? $booking_data['phone'] : '—') .
            self::kv('Zweck', $parts['purpose_label'])
        );
        $body .= self::section_title('Ansprechpartner');
        $body .= self::member_card($member_info);
        $body .= self::render_service_details_html($details);
        $body .= self::section_title('Termin');
        $body .= self::panel(
            self::kv('Datum', $parts['date']) .
            self::kv('Uhrzeit', $booking_data['time'] . ' Uhr')
        );

        if (!empty($booking_data['message'])) {
            $body .= self::section_title('Nachricht des Kunden');
            $body .= self::panel('<p style="margin:0;font-size:14px;line-height:1.7;color:#e5e5e5;white-space:pre-wrap;">' . esc_html($booking_data['message']) . '</p>');
        }

        $body .= self::cta_row($brand, true);

        return self::layout('Neue Terminbuchung: ' . $booking_data['name'], $body, $brand, false);
    }

    /**
     * Customer confirmation HTML.
     */
    public static function render_customer_confirmation($booking_data, $member_info, $parts) {
        $brand = self::get_brand_config();
        $details = isset($parts['service_details']) ? $parts['service_details'] : null;
        $first_name = trim(explode(' ', $booking_data['name'])[0]);

        $body  = self::hero(
            'Vielen Dank, ' . $first_name . '!',
            'Wir haben Ihre Terminanfrage erhalten und melden uns in Kürze mit einer Bestätigung.'
        );
        $body .= self::panel(
            '<p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#e5e5e5;">'
            . 'Ihre Buchungsanfrage bei <strong style="color:#ffffff;">PAXDesign</strong> wurde erfolgreich übermittelt. '
            . 'Nachfolgend finden Sie alle Details zu Ihrem gewünschten Termin.'
            . '</p>'
        );
        $body .= self::section_title('Ihr Ansprechpartner');
        $body .= self::member_card($member_info);
        $body .= self::render_service_details_html($details);
        $body .= self::section_title('Termin');
        $body .= self::panel(
            self::kv('Datum', $parts['date']) .
            self::kv('Uhrzeit', $booking_data['time'] . ' Uhr') .
            self::kv('Zweck', $parts['purpose_label'])
        );
        $body .= self::section_title('Kontakt');
        $body .= self::panel(
            self::kv('Telefon', $brand['phone'], 'tel:' . preg_replace('/\s+/', '', $brand['phone'])) .
            self::kv('E-Mail', $brand['email'], 'mailto:' . $brand['email'])
        );
        $body .= self::intro_block($brand);
        $body .= self::cta_row($brand, false);

        return self::layout('Ihre Terminanfrage bei PAXDesign – ' . $parts['date'], $body, $brand, true);
    }

    private static function layout($title, $body_html, $brand, $customer_email) {
        $logo = $brand['logo_url'];
        $footer_note = $customer_email
            ? 'Diese E-Mail bestätigt den Eingang Ihrer Terminanfrage.'
            : 'Interne Benachrichtigung aus dem PAXDesign Booking-System.';

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
            . esc_html($title)
            . '</title></head><body style="margin:0;padding:0;background:#0a0a0a;font-family:Inter,Arial,Helvetica,sans-serif;color:#e8e8e8;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0a0a0a;padding:24px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#111111;border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;">'
            . '<tr><td style="padding:28px 28px 18px;background:linear-gradient(180deg,#151515 0%,#0f0f0f 100%);border-bottom:1px solid rgba(194,255,0,0.18);text-align:center;">'
            . '<a href="' . esc_url($brand['site_url']) . '" style="text-decoration:none;display:inline-block;">'
            . (self::is_image_url($logo)
                ? '<img src="' . esc_url($logo) . '" alt="PAXDesign" style="max-width:180px;max-height:56px;height:auto;display:block;margin:0 auto 12px;">'
                : '<div style="font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.03em;margin-bottom:12px;">PAXDesign</div>')
            . '</a>'
            . '<div style="font-size:11px;color:#c2ff00;letter-spacing:0.14em;text-transform:uppercase;font-weight:700;">Booking System</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px;">' . $body_html . '</td></tr>'
            . self::footer_html($brand, $footer_note)
            . '</table></td></tr></table></body></html>';
    }

    private static function hero($title, $subtitle) {
        return '<h1 style="margin:0 0 10px;font-size:26px;line-height:1.25;color:#ffffff;font-weight:800;letter-spacing:-0.03em;">'
            . esc_html($title)
            . '</h1><p style="margin:0 0 24px;font-size:15px;line-height:1.65;color:#a8a8a8;">'
            . esc_html($subtitle)
            . '</p>';
    }

    private static function section_title($title) {
        return '<h2 style="margin:24px 0 12px;font-size:12px;line-height:1.4;color:#c2ff00;letter-spacing:0.12em;text-transform:uppercase;font-weight:700;">'
            . esc_html($title)
            . '</h2>';
    }

    private static function panel($inner_html) {
        return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 8px;background:#171717;border:1px solid rgba(255,255,255,0.07);border-radius:14px;"><tr><td style="padding:18px 20px;">'
            . $inner_html
            . '</td></tr></table>';
    }

    private static function kv($label, $value, $href = '') {
        $value_html = $href
            ? '<a href="' . esc_url($href) . '" style="color:#ffffff;text-decoration:none;">' . esc_html($value) . '</a>'
            : esc_html($value);

        return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 10px;"><tr>'
            . '<td style="width:34%;padding:0 12px 0 0;font-size:12px;color:#8f8f8f;vertical-align:top;">' . esc_html($label) . '</td>'
            . '<td style="padding:0;font-size:14px;color:#ffffff;line-height:1.5;vertical-align:top;">' . $value_html . '</td>'
            . '</tr></table>';
    }

    private static function member_card($member_info) {
        $name = isset($member_info['name']) ? $member_info['name'] : '';
        $role = isset($member_info['role']) ? $member_info['role'] : '';
        $role_en = isset($member_info['role_en']) ? $member_info['role_en'] : '';
        $image = isset($member_info['image']) ? $member_info['image'] : '';
        $badge = !empty($member_info['is_founder']) ? 'Gründer & Inhaber' : '';

        $avatar = $image
            ? '<img src="' . esc_url($image) . '" alt="' . esc_attr($name) . '" width="64" height="64" style="width:64px;height:64px;border-radius:50%;object-fit:cover;display:block;border:2px solid rgba(194,255,0,0.35);">'
            : '';

        return self::panel(
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
            . ($avatar ? '<td style="width:76px;vertical-align:top;padding-right:14px;">' . $avatar . '</td>' : '')
            . '<td style="vertical-align:top;">'
            . ($badge ? '<div style="display:inline-block;margin:0 0 8px;padding:4px 10px;border-radius:999px;background:rgba(194,255,0,0.12);border:1px solid rgba(194,255,0,0.28);font-size:10px;font-weight:700;color:#c2ff00;letter-spacing:0.08em;text-transform:uppercase;">' . esc_html($badge) . '</div><br>' : '')
            . '<div style="font-size:18px;font-weight:700;color:#ffffff;line-height:1.3;margin:0 0 4px;">' . esc_html($name) . '</div>'
            . ($role ? '<div style="font-size:14px;color:#e5e5e5;line-height:1.45;margin:0 0 2px;">' . esc_html($role) . '</div>' : '')
            . ($role_en ? '<div style="font-size:12px;color:#9a9a9a;line-height:1.45;">' . esc_html($role_en) . '</div>' : '')
            . '</td></tr></table>'
        );
    }

    private static function intro_block($brand) {
        return self::section_title('Über PAXDesign') . self::panel(
            '<p style="margin:0;font-size:14px;line-height:1.7;color:#bdbdbd;">' . esc_html($brand['company_intro']) . '</p>'
        );
    }

    private static function cta_row($brand, $admin_email) {
        $buttons = array(
            array('Unsere Website besuchen', $brand['site_url']),
            array('Weitere Leistungen ansehen', $brand['services_url']),
            array('Kontakt aufnehmen', $brand['contact_url']),
        );

        if ($admin_email) {
            $buttons = array(
                array('Booking-System öffnen', admin_url('admin.php?page=paxdesign-booking')),
                array('Website öffnen', $brand['site_url']),
            );
        }

        $html = self::section_title($admin_email ? 'Schnellzugriff' : 'Nächste Schritte');
        $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:0 0 8px;">';

        foreach ($buttons as $button) {
            $html .= '<a href="' . esc_url($button[1]) . '" style="display:inline-block;margin:0 8px 10px;padding:12px 18px;background:#c2ff00;color:#000000;text-decoration:none;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:0.02em;">'
                . esc_html($button[0])
                . '</a>';
        }

        $html .= '</td></tr></table>';

        $social_links = array();
        foreach ($brand['social'] as $network => $url) {
            if ($url !== '') {
                $social_links[] = array(ucfirst($network), $url);
            }
        }

        if (!empty($social_links)) {
            $html .= '<p style="margin:8px 0 0;text-align:center;font-size:12px;color:#8f8f8f;">Folgen Sie uns:</p><p style="margin:6px 0 0;text-align:center;">';
            foreach ($social_links as $link) {
                $html .= '<a href="' . esc_url($link[1]) . '" style="color:#c2ff00;text-decoration:none;font-size:12px;font-weight:600;margin:0 10px;">' . esc_html($link[0]) . '</a>';
            }
            $html .= '</p>';
        }

        return $html;
    }

    private static function footer_html($brand, $note) {
        return '<tr><td style="padding:22px 28px 28px;background:#0d0d0d;border-top:1px solid rgba(255,255,255,0.06);text-align:center;">'
            . '<p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#8f8f8f;">' . esc_html($note) . '</p>'
            . '<p style="margin:0 0 4px;font-size:12px;line-height:1.6;color:#bdbdbd;"><strong style="color:#ffffff;">' . esc_html($brand['legal_name']) . '</strong></p>'
            . '<p style="margin:0 0 4px;font-size:12px;line-height:1.6;color:#8f8f8f;">' . esc_html($brand['address']) . '</p>'
            . '<p style="margin:0;font-size:12px;line-height:1.6;color:#8f8f8f;"><a href="' . esc_url($brand['site_url']) . '" style="color:#c2ff00;text-decoration:none;">'
            . esc_html(untrailingslashit(str_replace(array('https://', 'http://'), '', $brand['site_url'])))
            . '</a></p>'
            . '</td></tr>';
    }

    private static function is_image_url($url) {
        if (empty($url)) {
            return false;
        }
        return (bool) preg_match('/\.(png|jpe?g|gif|webp|avif|svg)(\?.*)?$/i', $url);
    }
}
