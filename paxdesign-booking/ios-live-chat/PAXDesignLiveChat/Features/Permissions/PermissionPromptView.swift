import SwiftUI

struct NotificationPermissionPromptView: View {
    @EnvironmentObject private var push: PushService
    @ObservedObject private var permissions = PermissionCoordinator.shared
    @State private var isRequesting = false

    var body: some View {
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
                    Image(systemName: "bell.badge.fill")
                        .font(.system(size: 32))
                        .foregroundStyle(PAXTheme.accent)
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

                VStack(spacing: 10) {
                    PAXPrimaryButton(
                        title: isRequesting ? L10n.PermissionsRequesting : L10n.PermissionsEnable,
                        isLoading: isRequesting
                    ) {
                        Task {
                            isRequesting = true
                            defer { isRequesting = false }
                            let granted = await permissions.requestNotifications(push: push)
                            if granted {
                                await push.registerTokenWithBackend(auth: AuthStore.shared)
                            }
                        }
                    }

                    Button(L10n.CommonLater) {
                        permissions.skipNotificationOnboarding()
                    }
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            .padding(24)
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
    }
}

struct PAXLoadingOverlay: View {
    let message: String

    var body: some View {
        ZStack {
            Color.black.opacity(0.35).ignoresSafeArea()
            VStack(spacing: 14) {
                PAXTimelineLoaderCard(status: message)
                    .frame(maxWidth: 280)
                Text(message)
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            .padding(28)
            .background(
                RoundedRectangle(cornerRadius: 20, style: .continuous)
                    .fill(.ultraThinMaterial)
                    .overlay(
                        RoundedRectangle(cornerRadius: 20, style: .continuous)
                            .fill(PAXTheme.surface.opacity(0.82))
                    )
                    .overlay(
                        RoundedRectangle(cornerRadius: 20, style: .continuous)
                            .stroke(PAXTheme.border.opacity(0.42), lineWidth: 1)
                    )
            )
        }
    }
}
