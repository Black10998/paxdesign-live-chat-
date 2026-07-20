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
        return nil
    }

    static var isActive: Bool { mode != nil }

    @MainActor
    static func configureForLaunch(auth: AuthStore, launchSplash: LaunchSplashController) {
        guard let mode else { return }
        auth.configureLayoutVerification(mode: mode)
        launchSplash.markAnimationFinished()
        settingsForVerification()
    }

    @MainActor
    private static func settingsForVerification() {
        AppSettingsStore.shared.firstLaunchOnboardingCompleted = true
        AppSettingsStore.shared.onboardingCompleted = true
    }

    @ViewBuilder
    static var customerShell: some View {
        CustomerLayoutVerificationShell()
    }
}

@MainActor
private struct CustomerLayoutVerificationShell: View {
    @StateObject private var auth = CustomerAuthStore()
    @StateObject private var api = CustomerAPIClient()
    @ObservedObject private var navigation = CustomerNavigationCoordinator.shared

    var body: some View {
        CustomerTabView()
            .environmentObject(auth)
            .environmentObject(api)
            .environmentObject(navigation)
            .environmentObject(AppSettingsStore.shared)
            .onAppear {
                auth.isAuthenticated = true
                auth.profile = CustomerProfileResponse.Profile(
                    id: 1,
                    display_name: "Layout Verify",
                    email: "verify@paxdesign.test",
                    verified: true,
                    role: "customer",
                    avatar_url: nil
                )
                navigation.selectedTab = .chat
            }
    }
}

extension AdminProfile {
    fileprivate static var layoutVerificationStub: AdminProfile {
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
