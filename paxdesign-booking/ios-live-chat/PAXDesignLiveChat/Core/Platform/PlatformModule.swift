import SwiftUI

enum PlatformModule: String, CaseIterable, Identifiable, Hashable {
    case dashboard
    case calendar
    case tasks
    case files
    case orders
    case chats
    case team
    case live
    case notifications
    case reports
    case activityLog
    case employee
    case administration
    case devices
    case settings
    case profile
    case help
    case about

    var id: String { rawValue }

    var title: String {
        switch self {
        case .dashboard: return L10n.ModuleDashboard
        case .calendar: return L10n.ModuleCalendar
        case .tasks: return L10n.ModuleTasks
        case .files: return L10n.ModuleFiles
        case .orders: return String(localized: "Orders & Requests")
        case .chats: return L10n.TabChats
        case .team: return L10n.TabTeam
        case .live: return L10n.TabLive
        case .notifications: return L10n.PlatformNotifications
        case .reports: return L10n.ModuleReports
        case .activityLog: return L10n.ModuleActivityLog
        case .employee: return L10n.ModuleEmployee
        case .administration: return L10n.PlatformAdministration
        case .devices: return L10n.PlatformDevices
        case .settings: return L10n.AccountSettings
        case .profile: return L10n.ProfileTitle
        case .help: return L10n.AccountHelp
        case .about: return L10n.AccountAbout
        }
    }

    var subtitle: String {
        switch self {
        case .dashboard: return L10n.ModuleDashboardSubtitle
        case .calendar: return L10n.ModuleCalendarSubtitle
        case .tasks: return L10n.ModuleTasksSubtitle
        case .files: return L10n.ModuleFilesSubtitle
        case .orders: return String(localized: "Customer service requests and order tracking")
        case .chats: return L10n.SessionNoChatsHint
        case .team: return L10n.TeamHubSubtitle
        case .live: return L10n.LiveEmptyHint
        case .notifications: return L10n.PlatformNotificationsSubtitle
        case .reports: return L10n.ModuleReportsSubtitle
        case .activityLog: return L10n.ModuleActivityLogSubtitle
        case .employee: return L10n.ModuleEmployeeSubtitle
        case .administration: return L10n.PlatformAdministrationSubtitle
        case .devices: return L10n.PlatformDevicesSubtitle
        case .settings: return L10n.PlatformSettingsSubtitle
        case .profile: return L10n.ProfileAccountInfo
        case .help: return L10n.PlatformHelpSubtitle
        case .about: return PAXAppInfo.fullVersion
        }
    }

    var systemImage: String {
        switch self {
        case .dashboard: return "chart.bar.doc.horizontal.fill"
        case .calendar: return "calendar"
        case .tasks: return "checklist"
        case .files: return "folder.fill"
        case .orders: return "doc.text.fill"
        case .chats: return "bubble.left.and.bubble.right.fill"
        case .team: return "person.3.sequence.fill"
        case .live: return "bell.and.waves.left.and.right.fill"
        case .notifications: return "bell.badge.fill"
        case .reports: return "chart.xyaxis.line"
        case .activityLog: return "clock.arrow.circlepath"
        case .employee: return "person.badge.key.fill"
        case .administration: return "shield.lefthalf.filled"
        case .devices: return "iphone.and.arrow.forward"
        case .settings: return "gearshape.fill"
        case .profile: return "person.crop.circle.fill"
        case .help: return "questionmark.circle.fill"
        case .about: return "info.circle.fill"
        }
    }

    var tint: Color {
        switch self {
        case .dashboard: return .blue
        case .calendar: return .red
        case .tasks: return .green
        case .files: return .indigo
        case .orders: return .cyan
        case .chats: return PAXTheme.accent
        case .team: return PAXBrand.accent
        case .live: return .orange
        case .notifications: return .orange
        case .reports: return .purple
        case .activityLog: return .teal
        case .employee: return .mint
        case .administration: return .purple
        case .devices: return .blue
        case .settings: return PAXTheme.accent
        case .profile: return .pink
        case .help: return .teal
        case .about: return .indigo
        }
    }

    var category: PlatformModuleCategory {
        switch self {
        case .dashboard, .reports, .activityLog: return .insights
        case .calendar, .tasks, .files, .orders: return .workspace
        case .chats, .team, .live, .notifications: return .communication
        case .employee, .administration, .devices: return .management
        case .settings, .profile, .help, .about: return .system
        }
    }

    static var hubModules: [PlatformModule] {
        [.dashboard, .calendar, .tasks, .files, .orders, .notifications, .reports, .activityLog, .employee, .settings]
    }

    static var adminModules: [PlatformModule] {
        [.administration, .devices]
    }

    var helpDescription: String {
        switch self {
        case .dashboard: return L10n.DashboardMetricSessionsHelp
        case .calendar: return L10n.ModuleCalendarSubtitle
        case .tasks: return L10n.ModuleTasksSubtitle
        case .files: return L10n.ModuleFilesSubtitle
        case .orders: return String(localized: "Customer service requests and order tracking")
        case .chats: return L10n.SessionNoChatsHint
        case .team: return L10n.TeamHubSubtitle
        case .live: return L10n.LiveEmptyHint
        case .notifications: return L10n.PlatformNotificationsSubtitle
        case .reports: return L10n.ModuleReportsSubtitle
        case .activityLog: return L10n.ModuleActivityLogSubtitle
        case .employee: return L10n.ModuleEmployeeSubtitle
        case .administration: return L10n.PlatformAdministrationSubtitle
        case .devices: return L10n.PlatformDevicesSubtitle
        case .settings: return L10n.PlatformSettingsSubtitle
        case .profile: return L10n.ProfileAccountInfo
        case .help: return L10n.PlatformHelpSubtitle
        case .about: return L10n.AccountAbout
        }
    }
}

enum PlatformModuleCategory: String, CaseIterable, Identifiable {
    case insights
    case workspace
    case communication
    case management
    case system

    var id: String { rawValue }

    var title: String {
        switch self {
        case .insights: return L10n.ModuleCategoryInsights
        case .workspace: return L10n.ModuleCategoryWorkspace
        case .communication: return L10n.ModuleCategoryCommunication
        case .management: return L10n.ModuleCategoryManagement
        case .system: return L10n.ModuleCategorySystem
        }
    }
}

enum PlatformShellSection: String, CaseIterable, Identifiable, Hashable {
    case dashboard
    case chats
    case team
    case live
    case platform

    var id: String { rawValue }

    var title: String {
        switch self {
        case .dashboard: return L10n.TabDashboard
        case .chats: return L10n.TabChats
        case .team: return L10n.TabTeam
        case .live: return L10n.TabLive
        case .platform: return L10n.TabPlatform
        }
    }

    var systemImage: String {
        switch self {
        case .dashboard: return "chart.bar.doc.horizontal.fill"
        case .chats: return "bubble.left.and.bubble.right.fill"
        case .team: return "person.3.sequence.fill"
        case .live: return "bell.and.waves.left.and.right.fill"
        case .platform: return "square.grid.2x2.fill"
        }
    }
}

@MainActor
enum PlatformModuleAccess {
    static func isAvailable(_ module: PlatformModule, auth: AuthStore) -> Bool {
        switch module {
        case .dashboard: return auth.canViewDashboard
        case .calendar: return auth.canViewCalendar
        case .tasks: return auth.canViewTasks
        case .files: return auth.canViewFiles
        case .orders: return auth.canViewChats || auth.canManageUsers
        case .chats: return auth.canViewChats
        case .team: return auth.canViewChats
        case .live: return true
        case .notifications: return true
        case .reports: return auth.canViewReports
        case .activityLog: return auth.canViewActivityLog
        case .employee: return auth.canViewEmployeeDashboard
        case .administration: return auth.canManageUsers
        case .devices: return auth.canManageUsers
        case .settings: return true
        case .profile: return true
        case .help, .about: return true
        }
    }

    static func availableHubModules(auth: AuthStore) -> [PlatformModule] {
        PlatformModule.hubModules.filter { isAvailable($0, auth: auth) }
    }
}
