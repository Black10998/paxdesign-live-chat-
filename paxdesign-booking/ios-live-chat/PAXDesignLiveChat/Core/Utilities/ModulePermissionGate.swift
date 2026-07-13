import Foundation

extension AuthStore {
    private var modulePerms: ModulePermissions? {
        profile?.modulePermissions
    }

    var canViewDashboard: Bool { modulePerms?.viewDashboard ?? (canViewChats || canManageUsers) }
    var canViewCalendar: Bool { modulePerms?.viewCalendar ?? canViewChats }
    var canViewTasks: Bool { modulePerms?.viewTasks ?? canViewChats }
    var canViewFiles: Bool { modulePerms?.viewFiles ?? (canViewChats || canManageSettings) }
    var canViewReports: Bool { modulePerms?.viewReports ?? (canViewRatings || canManageUsers) }
    var canViewActivityLog: Bool { modulePerms?.viewActivityLog ?? canViewChats }
    var canViewEmployeeDashboard: Bool { modulePerms?.viewEmployeeDashboard ?? (canViewChats && !canManageUsers) }
    var canManageTasks: Bool { modulePerms?.manageTasks ?? (canReplyChats || canManageUsers) }
    var canManageCalendar: Bool { modulePerms?.manageCalendar ?? (canReplyChats || canManageUsers) }
    var canManageFiles: Bool { modulePerms?.manageFiles ?? (canManageSettings || canManageUsers) }
    var canExportReports: Bool { modulePerms?.exportReports ?? (canManageUsers || canViewRatings) }

    var roleLabel: String {
        if profile?.isSuperAdmin == true { return L10n.RoleExecutiveDirector }
        if let employeeRole = PlatformSyncService.shared.employee?.roleLabel {
            switch employeeRole {
            case "super_admin": return L10n.RoleExecutiveDirector
            case "manager": return L10n.ProfileRoleManager
            default: return L10n.ProfileRoleStaff
            }
        }
        if canManageUsers { return L10n.ProfileRoleManager }
        return L10n.ProfileRoleStaff
    }
}
