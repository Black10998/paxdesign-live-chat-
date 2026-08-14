import SwiftUI
import UIKit

/// Global UIKit chrome synced to the Revolut canvas so navigation bars, lists,
/// and search fields follow Light/Dark automatically via dynamic UIColors.
enum PAXRevolutAppearance {
    static func apply() {
        applyNavigationBar()
        applyTabBar()
        applyTableView()
        applySearchBar()
        applyTextInputs()
    }

    static func applyNavigationBar() {
        let appearance = UINavigationBarAppearance()
        appearance.configureWithOpaqueBackground()
        appearance.backgroundColor = PAXDynamic.uiColor(PAXDynamic.canvasLight, PAXDynamic.canvasDark)
        appearance.shadowColor = .clear
        appearance.titleTextAttributes = [
            .foregroundColor: PAXDynamic.uiColor(PAXDynamic.textPrimaryLight, PAXDynamic.textPrimaryDark),
            .font: UIFont.systemFont(ofSize: 17, weight: .semibold),
        ]
        appearance.largeTitleTextAttributes = [
            .foregroundColor: PAXDynamic.uiColor(PAXDynamic.textPrimaryLight, PAXDynamic.textPrimaryDark),
            .font: UIFont.systemFont(ofSize: 34, weight: .bold),
        ]

        let button = UIBarButtonItemAppearance()
        button.normal.titleTextAttributes = [
            .foregroundColor: PAXDynamic.uiColor(UIColor.systemBlue, PAXDynamic.lime),
        ]
        appearance.buttonAppearance = button
        appearance.doneButtonAppearance = button

        let nav = UINavigationBar.appearance()
        nav.standardAppearance = appearance
        nav.scrollEdgeAppearance = appearance
        nav.compactAppearance = appearance
        nav.tintColor = PAXDynamic.uiColor(UIColor.systemBlue, PAXDynamic.lime)
        nav.isTranslucent = false
    }

    static func applyTabBar() {
        let appearance = UITabBarAppearance()
        appearance.configureWithTransparentBackground()
        appearance.backgroundEffect = UIBlurEffect(style: .systemMaterial)
        appearance.backgroundColor = PAXDynamic.uiColor(
            UIColor(red: 0.957, green: 0.957, blue: 0.973, alpha: 0.92),
            UIColor(red: 0.039, green: 0.039, blue: 0.059, alpha: 0.92)
        )
        appearance.shadowColor = PAXDynamic.uiColor(
            UIColor.black.withAlphaComponent(0.06),
            UIColor.white.withAlphaComponent(0.08)
        )
        UITabBar.appearance().standardAppearance = appearance
        UITabBar.appearance().scrollEdgeAppearance = appearance
    }

    static func applyTableView() {
        UITableView.appearance().backgroundColor = .clear
        UITableView.appearance().separatorColor = PAXDynamic.uiColor(PAXDynamic.dividerLight, PAXDynamic.dividerDark)
        UITableViewCell.appearance().backgroundColor = PAXDynamic.uiColor(
            PAXDynamic.surface1Light,
            PAXDynamic.surface1Dark
        )
        UICollectionView.appearance().backgroundColor = .clear
    }

    static func applySearchBar() {
        let search = UISearchBar.appearance()
        search.barTintColor = PAXDynamic.uiColor(PAXDynamic.canvasLight, PAXDynamic.canvasDark)
        search.searchTextField.backgroundColor = PAXDynamic.uiColor(PAXDynamic.surface1Light, PAXDynamic.surface1Dark)
        search.searchTextField.textColor = PAXDynamic.uiColor(PAXDynamic.textPrimaryLight, PAXDynamic.textPrimaryDark)
        search.tintColor = PAXDynamic.uiColor(UIColor.systemBlue, PAXDynamic.lime)
    }

    static func applyTextInputs() {
        UITextField.appearance().tintColor = PAXDynamic.uiColor(UIColor.systemBlue, PAXDynamic.lime)
        UITextView.appearance().tintColor = PAXDynamic.uiColor(UIColor.systemBlue, PAXDynamic.lime)
        UISwitch.appearance().onTintColor = PAXDynamic.uiColor(UIColor.systemBlue, PAXDynamic.lime)
    }
}
