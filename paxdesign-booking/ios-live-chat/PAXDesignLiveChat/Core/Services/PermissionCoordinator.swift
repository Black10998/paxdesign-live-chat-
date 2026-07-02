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
        return notificationStatus == .notDetermined && !hasCompletedOnboarding
    }

    func presentNotificationPromptIfNeeded(isLoggedIn: Bool) {
        guard shouldShowNotificationOnboarding(isLoggedIn: isLoggedIn) else { return }
        showNotificationPrompt = true
    }

    func requestNotifications(push: PushService) async -> Bool {
        await push.requestAuthorization()
        await refreshStatuses()
        hasCompletedOnboarding = true
        UserDefaults.standard.set(true, forKey: Keys.onboardingDone)
        showNotificationPrompt = false
        return notificationStatus == .authorized || notificationStatus == .provisional
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
