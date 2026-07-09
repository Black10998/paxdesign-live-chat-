import SwiftUI

struct PlatformModuleCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    let tint: Color
    var badge: Int = 0

    var body: some View {
        HStack(spacing: 14) {
            ZStack {
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .fill(tint.opacity(0.16))
                    .frame(width: 44, height: 44)
                Image(systemName: systemImage)
                    .font(.system(size: 20, weight: .semibold))
                    .foregroundStyle(tint)
            }

            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 6) {
                    Text(title)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .lineLimit(1)
                    if badge > 0 {
                        Text("\(badge)")
                            .font(.caption2.weight(.bold))
                            .foregroundStyle(.white)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(Capsule().fill(tint))
                    }
                }
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(2)
                    .multilineTextAlignment(.leading)
            }

            Spacer(minLength: 0)

            Image(systemName: "chevron.right")
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
        }
        .padding(14)
        .background(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .fill(PAXTheme.surface.opacity(0.92))
                .overlay(
                    RoundedRectangle(cornerRadius: 18, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.55), lineWidth: 0.5)
                )
        )
        .contentShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
    }
}

struct PlatformHeroHeader: View {
    let title: String
    let subtitle: String
    let systemImage: String
    let gradient: [Color]

    var body: some View {
        HStack(alignment: .center, spacing: 16) {
            ZStack {
                Circle()
                    .fill(
                        LinearGradient(colors: gradient, startPoint: .topLeading, endPoint: .bottomTrailing)
                    )
                    .frame(width: 56, height: 56)
                Image(systemName: systemImage)
                    .font(.system(size: 24, weight: .semibold))
                    .foregroundStyle(.white)
            }

            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.title3.weight(.bold))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(subtitle)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
            }

            Spacer(minLength: 0)
        }
        .padding(18)
        .background(
            RoundedRectangle(cornerRadius: 20, style: .continuous)
                .fill(PAXTheme.surface.opacity(0.94))
                .overlay(
                    RoundedRectangle(cornerRadius: 20, style: .continuous)
                        .stroke(
                            LinearGradient(colors: gradient.map { $0.opacity(0.35) }, startPoint: .topLeading, endPoint: .bottomTrailing),
                            lineWidth: 1
                        )
                )
        )
        .transition(PAXMotion.cardAppear)
    }
}

struct PermissionOverviewRow: View {
    let title: String
    let enabled: Bool

    var body: some View {
        HStack {
            Label(title, systemImage: enabled ? "checkmark.circle.fill" : "minus.circle")
                .foregroundStyle(enabled ? PAXTheme.success : PAXTheme.textTertiary)
            Spacer()
            Text(enabled ? L10n.CommonActive : L10n.SettingsDisabled)
                .font(.caption.weight(.semibold))
                .foregroundStyle(enabled ? PAXTheme.success : PAXTheme.textTertiary)
        }
    }
}

struct PermissionLabelItem: Identifiable {
    let id: String
    let title: String
    let enabled: Bool
}

enum PermissionLabels {
    static func items(for permissions: AdminPermissions) -> [PermissionLabelItem] {
        [
            PermissionLabelItem(id: "view_chats", title: L10n.StaffPermissionViewChats, enabled: permissions.viewChats),
            PermissionLabelItem(id: "reply_chats", title: L10n.StaffPermissionReplyChats, enabled: permissions.replyChats),
            PermissionLabelItem(id: "use_ai", title: L10n.SettingsSectionAI, enabled: permissions.useAI),
            PermissionLabelItem(id: "send_images", title: L10n.ChatImage, enabled: permissions.sendImages),
            PermissionLabelItem(id: "manage_settings", title: L10n.StaffPermissionManageSettings, enabled: permissions.manageSettings),
            PermissionLabelItem(id: "view_ratings", title: L10n.StaffPermissionViewRatings, enabled: permissions.viewRatings),
            PermissionLabelItem(id: "manage_users", title: L10n.StaffPermissionManageUsers, enabled: permissions.manageUsers),
            PermissionLabelItem(id: "access_security", title: L10n.LegalSecurity, enabled: permissions.accessSecurity)
        ]
    }
}
