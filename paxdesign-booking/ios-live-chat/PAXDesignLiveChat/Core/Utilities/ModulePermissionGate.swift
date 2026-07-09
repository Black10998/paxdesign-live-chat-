import Foundation

extension AuthStore {
    var canViewDashboard: Bool { canViewChats || canManageUsers }
    var canViewCalendar: Bool { canViewChats }
    var canViewTasks: Bool { canViewChats }
    var canViewFiles: Bool { canViewChats || canManageSettings }
    var canViewReports: Bool { canViewRatings || canManageUsers }
    var canViewActivityLog: Bool { canViewChats }
    var canViewEmployeeDashboard: Bool { canViewChats && !canManageUsers }
    var canManageTasks: Bool { canReplyChats || canManageUsers }
    var canManageCalendar: Bool { canReplyChats || canManageUsers }
    var canManageFiles: Bool { canManageSettings || canManageUsers }
    var canExportReports: Bool { canManageUsers || canViewRatings }

    var roleLabel: String {
        if profile?.isSuperAdmin == true { return L10n.AccountSuperAdmin }
        if canManageUsers { return L10n.ProfileRoleManager }
        return L10n.ProfileRoleStaff
    }
}
