import SwiftUI

#if DEBUG
@MainActor
enum PAXLayoutVerification {
    enum Mode: String {
        case customer
        case staff
    }

    static var mode: Mode? {
        let args = ProcessInfo.processInfo.arguments
        if args.contains("-PAXLayoutVerifyCustomer") { return .customer }
        if args.contains("-PAXLayoutVerifyStaff") { return .staff }
        if ProcessInfo.processInfo.environment["PAX_LAYOUT_VERIFY"] == "customer" { return .customer }
        if ProcessInfo.processInfo.environment["PAX_LAYOUT_VERIFY"] == "staff" { return .staff }
        return nil
    }

    static var isActive: Bool { mode != nil }

    static func configureForLaunch(auth: AuthStore, launchSplash: LaunchSplashController) {
        guard let mode else { return }
        auth.configureLayoutVerification(mode: mode)
        launchSplash.markAnimationFinished()
        AppSettingsStore.shared.firstLaunchOnboardingCompleted = true
        AppSettingsStore.shared.onboardingCompleted = true
        if mode == .customer {
            CustomerNavigationCoordinator.shared.selectedTab = .chat
        }
    }
}

extension AdminProfile {
    static var layoutVerificationStub: AdminProfile {
        let json = """
        {
          "user_id": 1,
          "name": "Layout Verify",
          "email": "verify@paxdesign.test",
          "site_url": "https://paxdesign.at",
          "rest_base": "https://paxdesign.at/wp-json/paxdesign/v1",
          "plugin_ver": "3.158.0",
          "is_super_admin": true,
          "permissions": {
            "view_chats": true,
            "reply_chats": true,
            "use_ai": true,
            "send_images": true,
            "manage_settings": true,
            "view_ratings": true,
            "manage_users": true,
            "access_security": true,
            "manage_team_permissions": true,
            "manage_customer_profiles": true,
            "assign_team_tasks": true,
            "customize_hub_profile": true,
            "takeover_chats": true
          },
          "onboarding_completed": true,
          "terms_accepted": true,
          "terms_accepted_at": 0,
          "spoken_languages": ["en"]
        }
        """
        return try! JSONDecoder().decode(AdminProfile.self, from: Data(json.utf8))
    }
}
#endif
