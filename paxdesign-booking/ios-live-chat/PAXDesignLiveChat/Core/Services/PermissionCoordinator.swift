import Foundation
import UserNotifications

@MainActor
final class PermissionCoordinator: ObservableObject {
    static let shared = PermissionCoordinator()

    @Published private(set) var notificationStatus: UNAuthorizationStatus = .notDetermined
    @Published var showNotificationPrompt = false
    @Published private(set) var hasCompletedOnboarding: Bool

    private enum Keys {
        static let onboardingDone = "pax.permissions.onboardingDone"
    }

    private init() {
        hasCompletedOnboarding = UserDefaults.standard.bool(forKey: Keys.onboardingDone)
    }

    func refreshStatuses() async {
        let settings = await UNUserNotificationCenter.current().notificationSettings()
        notificationStatus = settings.authorizationStatus
    }

    func shouldShowNotificationOnboarding(isLoggedIn: Bool) -> Bool {
        guard isLoggedIn else { return false }
        switch notificationStatus {
        case .notDetermined:
            return !hasCompletedOnboarding
        case .denied:
            return false
        default:
            return false
        }
    }

    func presentNotificationPromptIfNeeded(isLoggedIn: Bool) {
        guard shouldShowNotificationOnboarding(isLoggedIn: isLoggedIn) else { return }
        showNotificationPrompt = true
    }

    var notificationStatusLabel: String {
        switch notificationStatus {
        case .authorized: return L10n.SettingsNotificationsStatusAuthorized
        case .denied: return L10n.SettingsNotificationsStatusDenied
        case .provisional: return L10n.SettingsNotificationsStatusProvisional
        case .ephemeral: return L10n.SettingsNotificationsStatusAuthorized
        case .notDetermined: return L10n.SettingsNotificationsStatusNotDetermined
        @unknown default: return L10n.SettingsNotificationsStatusUnknown
        }
    }

    var canRequestNotificationPermission: Bool {
        notificationStatus == .notDetermined
    }

    var shouldOpenSystemSettingsForNotifications: Bool {
        notificationStatus == .denied
    }

    func requestNotifications(push: PushService) async -> Bool {
        let granted = await push.requestAuthorization()
        await refreshStatuses()
        hasCompletedOnboarding = true
        UserDefaults.standard.set(true, forKey: Keys.onboardingDone)
        showNotificationPrompt = false
        return granted || notificationStatus == .authorized || notificationStatus == .provisional
    }

    func skipNotificationOnboarding() {
        hasCompletedOnboarding = true
        UserDefaults.standard.set(true, forKey: Keys.onboardingDone)
        showNotificationPrompt = false
    }

    func openSystemSettings() {
        guard let url = URL(string: UIApplication.openSettingsURLString) else { return }
        UIApplication.shared.open(url)
    }
}

import UIKit
