import SwiftUI

struct NotificationPermissionPromptView: View {
    @EnvironmentObject private var push: PushService
    @ObservedObject private var permissions = PermissionCoordinator.shared
    @State private var isRequesting = false

    var body: some View {
        ScrollView {
            VStack(spacing: 0) {
                Capsule()
                    .fill(PAXTheme.textTertiary.opacity(0.5))
                    .frame(width: 36, height: 5)
                    .padding(.top, 10)

                VStack(spacing: 22) {
                    ZStack {
                        Circle()
                            .fill(PAXTheme.accentSoft)
                            .frame(width: 72, height: 72)
                        PAXIcon("bell.badge.fill", size: .hero)
                    }

                    VStack(spacing: 8) {
                        Text(L10n.PermissionsNotificationsTitle)
                            .font(.title3.weight(.semibold))
                        Text(L10n.PermissionsNotificationsBody)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .multilineTextAlignment(.center)
                            .fixedSize(horizontal: false, vertical: true)
                    }

                    VStack(spacing: 12) {
                        PAXRevolutPrimaryButton(
                            title: isRequesting ? L10n.PermissionsRequesting : L10n.PermissionsEnable,
                            isLoading: isRequesting
                        ) {
                            guard !isRequesting else { return }
                            isRequesting = true
                            Task {
                                defer { isRequesting = false }
                                _ = await permissions.requestNotifications(push: push)
                            }
                        }

                        PAXRevolutGhostButton(title: L10n.PermissionsNotNow) {
                            permissions.skipNotificationOnboarding()
                        }
                    }
                }
                .padding(24)
            }
        }
        .background(
            RoundedRectangle(cornerRadius: 24, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 24, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.8))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 24, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.44), lineWidth: 1)
                )
        )
        .padding(.horizontal, 12)
        .interactiveDismissDisabled(false)
    }
}
