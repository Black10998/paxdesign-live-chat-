import SwiftUI
import UIKit

/// Global UIKit appearance — Revolut dark-cockpit structure with PAXDesign accent.
enum PAXRevolutAppearance {
    static func configure(isDark: Bool = true) {
        configureNavigationBar(isDark: isDark)
        configureTabBar(isDark: isDark)
        configureTableView(isDark: isDark)
        configureSearchBar(isDark: isDark)
    }

    private static func canvasUIColor(isDark: Bool) -> UIColor {
        isDark
            ? UIColor(red: 0.039, green: 0.039, blue: 0.059, alpha: 1)
            : UIColor(red: 0.949, green: 0.949, blue: 0.969, alpha: 1)
    }

    private static func surface1UIColor(isDark: Bool) -> UIColor {
        isDark
            ? UIColor(red: 0.086, green: 0.086, blue: 0.122, alpha: 1)
            : UIColor.white
    }

    private static func textPrimaryUIColor(isDark: Bool) -> UIColor {
        isDark ? .white : UIColor(red: 0.08, green: 0.08, blue: 0.10, alpha: 1)
    }

    private static func textSecondaryUIColor(isDark: Bool) -> UIColor {
        isDark
            ? UIColor(red: 0.604, green: 0.604, blue: 0.667, alpha: 1)
            : UIColor(red: 0.38, green: 0.38, blue: 0.42, alpha: 1)
    }

    private static func dividerUIColor(isDark: Bool) -> UIColor {
        isDark
            ? UIColor(red: 0.165, green: 0.165, blue: 0.220, alpha: 1)
            : UIColor(red: 0.88, green: 0.88, blue: 0.90, alpha: 1)
    }

    private static func configureNavigationBar(isDark: Bool) {
        let appearance = UINavigationBarAppearance()
        appearance.configureWithTransparentBackground()
        appearance.backgroundColor = canvasUIColor(isDark: isDark).withAlphaComponent(isDark ? 0.92 : 0.96)
        appearance.backgroundEffect = isDark ? UIBlurEffect(style: .systemMaterialDark) : UIBlurEffect(style: .systemMaterial)
        appearance.shadowColor = dividerUIColor(isDark: isDark)
        appearance.titleTextAttributes = [
            .foregroundColor: textPrimaryUIColor(isDark: isDark),
            .font: UIFont.systemFont(ofSize: 17, weight: .semibold)
        ]
        appearance.largeTitleTextAttributes = [
            .foregroundColor: textPrimaryUIColor(isDark: isDark),
            .font: UIFont.systemFont(ofSize: 28, weight: .bold)
        ]

        UINavigationBar.appearance().standardAppearance = appearance
        UINavigationBar.appearance().scrollEdgeAppearance = appearance
        UINavigationBar.appearance().compactAppearance = appearance
        UINavigationBar.appearance().tintColor = isDark
            ? UIColor(red: 194 / 255, green: 1, blue: 0, alpha: 1)
            : UIColor.systemBlue
    }

    private static func configureTabBar(isDark: Bool) {
        let appearance = UITabBarAppearance()
        appearance.configureWithTransparentBackground()
        appearance.backgroundEffect = isDark ? UIBlurEffect(style: .systemMaterialDark) : UIBlurEffect(style: .systemMaterial)
        appearance.backgroundColor = canvasUIColor(isDark: isDark).withAlphaComponent(0.92)
        appearance.shadowColor = dividerUIColor(isDark: isDark)

        UITabBar.appearance().standardAppearance = appearance
        UITabBar.appearance().scrollEdgeAppearance = appearance
    }

    private static func configureTableView(isDark: Bool) {
        UITableView.appearance().backgroundColor = canvasUIColor(isDark: isDark)
        UITableViewCell.appearance().backgroundColor = .clear
    }

    private static func configureSearchBar(isDark: Bool) {
        let appearance = UISearchBar.appearance()
        appearance.searchBarStyle = .minimal
        appearance.tintColor = isDark
            ? UIColor(red: 194 / 255, green: 1, blue: 0, alpha: 1)
            : UIColor.systemBlue
        appearance.searchTextField.backgroundColor = surface1UIColor(isDark: isDark)
        appearance.searchTextField.textColor = textPrimaryUIColor(isDark: isDark)
        appearance.searchTextField.attributedPlaceholder = NSAttributedString(
            string: appearance.searchTextField.placeholder ?? "",
            attributes: [.foregroundColor: textSecondaryUIColor(isDark: isDark)]
        )
    }
}
