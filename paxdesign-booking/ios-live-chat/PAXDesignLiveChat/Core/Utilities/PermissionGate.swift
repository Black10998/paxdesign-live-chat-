import Foundation

extension AuthStore {
    func hasPermission(_ keyPath: KeyPath<AdminPermissions, Bool>) -> Bool {
        guard let profile else { return false }
        if profile.isSuperAdmin { return true }
        return profile.perms[keyPath: keyPath]
    }

    var canViewChats: Bool { hasPermission(\.viewChats) }
    var canReplyChats: Bool { hasPermission(\.replyChats) }
    var canTakeOverChats: Bool {
        guard let profile else { return false }
        if profile.isSuperAdmin { return true }
        return profile.perms.takeoverChats
    }
    var canUseAI: Bool { hasPermission(\.useAI) }
    var canSendImages: Bool { hasPermission(\.sendImages) }
    var canManageSettings: Bool { hasPermission(\.manageSettings) }
    var canViewRatings: Bool { hasPermission(\.viewRatings) }
    var canManageUsers: Bool { hasPermission(\.manageUsers) }
    var canAccessSecurity: Bool { hasPermission(\.accessSecurity) }
    var canManageTeamPermissions: Bool { canManageUsers || hasPermission(\.manageTeamPermissions) }
    var canManageCustomerProfiles: Bool { canManageUsers || hasPermission(\.manageCustomerProfiles) }
    var canAssignTeamTasks: Bool { canManageUsers || hasPermission(\.assignTeamTasks) }
    var canCustomizeHubProfile: Bool { canManageUsers || canManageSettings || hasPermission(\.customizeHubProfile) }
}
