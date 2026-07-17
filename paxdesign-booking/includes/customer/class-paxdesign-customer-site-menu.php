<?php
/**
 * Top-level native app navigation (marketing + portal destinations).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Site_Menu {

    /**
     * @param string $lang
     * @return array<string, mixed>
     */
    public static function payload($lang = 'de') {
        $lang = PAXdesign_Customer_Homepage::normalize_language($lang);
        $dir = ($lang === 'ar') ? 'rtl' : 'ltr';

        $labels = self::labels($lang);

        return array(
            'lang'  => $lang,
            'dir'   => $dir,
            'tabs'  => array(
                array('id' => 'home', 'title' => $labels['home'], 'icon' => 'house.fill'),
                array('id' => 'services', 'title' => $labels['services'], 'icon' => 'square.grid.2x2.fill'),
                array('id' => 'portfolio', 'title' => $labels['portfolio'], 'icon' => 'photo.on.rectangle.angled'),
                array('id' => 'chat', 'title' => $labels['chat'], 'icon' => 'message.fill'),
                array('id' => 'account', 'title' => $labels['account'], 'icon' => 'person.crop.circle.fill'),
            ),
            'pages' => array(
                array('id' => 'about', 'slug' => 'ueber-uns', 'title' => $labels['about'], 'type' => 'page'),
                array('id' => 'contact', 'slug' => 'kontakt', 'title' => $labels['contact'], 'type' => 'page'),
                array('id' => 'privacy', 'slug' => 'datenschutz', 'title' => $labels['privacy'], 'type' => 'page'),
                array('id' => 'terms', 'slug' => 'agb', 'title' => $labels['terms'], 'type' => 'page'),
            ),
            'portal' => array(
                array('id' => 'dashboard', 'title' => $labels['dashboard'], 'route' => '/portal/dashboard'),
                array('id' => 'projects', 'title' => $labels['projects'], 'route' => '/projects'),
                array('id' => 'requests', 'title' => $labels['requests'], 'route' => '/orders'),
                array('id' => 'files', 'title' => $labels['files'], 'route' => '/files'),
                array('id' => 'news', 'title' => $labels['news'], 'route' => '/news'),
            ),
        );
    }

    /**
     * @param string $lang
     * @return array<string, string>
     */
    private static function labels($lang) {
        $map = array(
            'de' => array(
                'home'      => 'Start',
                'services'  => 'Leistungen',
                'portfolio' => 'Referenzen',
                'chat'      => 'Chat',
                'account'   => 'Konto',
                'about'     => 'Über uns',
                'contact'   => 'Kontakt',
                'privacy'   => 'Datenschutz',
                'terms'     => 'AGB',
                'dashboard' => 'Mein Bereich',
                'projects'  => 'Projekte',
                'requests'  => 'Anfragen',
                'files'     => 'Dateien',
                'news'      => 'Neuigkeiten',
            ),
            'en' => array(
                'home'      => 'Home',
                'services'  => 'Services',
                'portfolio' => 'Portfolio',
                'chat'      => 'Chat',
                'account'   => 'Account',
                'about'     => 'About',
                'contact'   => 'Contact',
                'privacy'   => 'Privacy',
                'terms'     => 'Terms',
                'dashboard' => 'My workspace',
                'projects'  => 'Projects',
                'requests'  => 'Requests',
                'files'     => 'Files',
                'news'      => 'News',
            ),
            'ar' => array(
                'home'      => 'الرئيسية',
                'services'  => 'الخدمات',
                'portfolio' => 'الأعمال',
                'chat'      => 'الدردشة',
                'account'   => 'الحساب',
                'about'     => 'من نحن',
                'contact'   => 'تواصل',
                'privacy'   => 'الخصوصية',
                'terms'     => 'الشروط',
                'dashboard' => 'مساحتي',
                'projects'  => 'المشاريع',
                'requests'  => 'الطلبات',
                'files'     => 'الملفات',
                'news'      => 'الأخبار',
            ),
        );
        return isset($map[$lang]) ? $map[$lang] : $map['de'];
    }
}
