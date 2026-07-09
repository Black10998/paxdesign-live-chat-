import SwiftUI

struct PlatformModuleCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    let tint: Color
    var badge: Int = 0

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: systemImage)
                .font(.body.weight(.medium))
                .foregroundStyle(tint)
                .frame(width: 28, height: 28)

            VStack(alignment: .leading, spacing: 2) {
                HStack(spacing: 6) {
                    Text(title)
                        .font(.body)
                        .foregroundStyle(.primary)
                        .lineLimit(1)
                    if badge > 0 {
                        Text("\(badge)")
                            .font(.caption2.weight(.semibold))
                            .foregroundStyle(.white)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(Capsule().fill(tint))
                    }
                }
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(2)
            }

            Spacer(minLength: 0)

            Image(systemName: "chevron.right")
                .font(.caption.weight(.semibold))
                .foregroundStyle(.tertiary)
        }
        .padding(.vertical, 4)
        .contentShape(Rectangle())
    }
}

struct PlatformHeroHeader: View {
    let title: String
    let subtitle: String
    let systemImage: String
    let gradient: [Color]

    var body: some View {
        HStack(alignment: .center, spacing: 14) {
            Image(systemName: systemImage)
                .font(.title2)
                .symbolRenderingMode(.hierarchical)
                .foregroundStyle(gradient.first ?? .accentColor)
                .frame(width: 36, height: 36)

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.title3.weight(.semibold))
                Text(subtitle)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
            }

            Spacer(minLength: 0)
        }
        .padding(.vertical, 4)
    }
}

struct PermissionOverviewRow: View {
    let title: String
    let enabled: Bool

    var body: some View {
        HStack {
            Label(title, systemImage: enabled ? "checkmark.circle.fill" : "minus.circle")
                .foregroundStyle(enabled ? .green : .secondary)
            Spacer()
            Text(enabled ? L10n.CommonActive : L10n.SettingsDisabled)
                .font(.caption)
                .foregroundStyle(enabled ? .green : .secondary)
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
            PermissionLabelItem(id: "access_security", title: L10n.LegalSecurity, enabled: permissions.accessSecurity),
            PermissionLabelItem(id: "manage_team_permissions", title: "Team-Berechtigungen", enabled: permissions.manageTeamPermissions),
            PermissionLabelItem(id: "manage_customer_profiles", title: "Kundenprofile", enabled: permissions.manageCustomerProfiles),
            PermissionLabelItem(id: "assign_team_tasks", title: "Aufgaben zuweisen", enabled: permissions.assignTeamTasks),
            PermissionLabelItem(id: "customize_hub_profile", title: "Hub-Profilname", enabled: permissions.customizeHubProfile)
        ]
    }
}
