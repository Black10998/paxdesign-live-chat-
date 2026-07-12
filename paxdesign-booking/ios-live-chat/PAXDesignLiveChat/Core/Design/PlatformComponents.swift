import SwiftUI

struct PlatformModuleCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    let tint: Color
    var badge: Int = 0
    var helpText: String?

    var body: some View {
        PAXFeatureCard(
            title: title,
            subtitle: subtitle,
            systemImage: systemImage,
            tint: tint,
            badge: badge,
            helpText: helpText
        )
        .contentShape(Rectangle())
    }
}

struct PlatformHeroHeader: View {
    let title: String
    let subtitle: String
    let systemImage: String
    var tint: Color = PAXTheme.accent

    var body: some View {
        PAXHeroCard(
            title: title,
            subtitle: subtitle,
            systemImage: systemImage,
            tint: tint
        )
    }
}

struct PermissionOverviewRow: View {
    let title: String
    let enabled: Bool

    var body: some View {
        HStack {
            PAXIcon(enabled ? "checkmark.circle.fill" : "minus.circle", size: .row)
            Text(title)
                .foregroundStyle(PAXTheme.textPrimary)
            Spacer()
            Text(enabled ? L10n.CommonActive : L10n.SettingsDisabled)
                .font(.caption)
                .foregroundStyle(enabled ? PAXTheme.textPrimary : PAXTheme.textSecondary)
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
