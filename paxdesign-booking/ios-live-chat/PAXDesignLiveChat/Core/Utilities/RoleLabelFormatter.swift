import Foundation

/// Central role-title localization — maps English API labels and legacy German strings
/// to the correct localized form for the current locale.
enum RoleLabelFormatter {
    static func localized(_ value: String) -> String {
        let trimmed = value.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return trimmed }

        switch canonicalKey(for: trimmed) {
        case "executive_director":
            return L10n.RoleExecutiveDirector
        case "administrator":
            return L10n.RoleAdministrator
        case "senior_staff":
            return L10n.RoleSeniorStaff
        case "staff_member":
            return L10n.RoleStaffMember
        case "team_member":
            return L10n.RoleTeamMember
        case "super_admin":
            return L10n.RoleExecutiveDirector
        case "manager":
            return L10n.ProfileRoleManager
        case "staff":
            return L10n.ProfileRoleStaff
        default:
            return trimmed
        }
    }

    static func localizedOptional(_ value: String?) -> String {
        guard let value, !value.isEmpty else { return "" }
        return localized(value)
    }

    /// Returns the canonical English role key when recognized, otherwise nil.
    static func canonicalKey(for value: String) -> String {
        let normalized = value
            .trimmingCharacters(in: .whitespacesAndNewlines)
            .lowercased()
            .replacingOccurrences(of: "ä", with: "a")
            .replacingOccurrences(of: "ö", with: "o")
            .replacingOccurrences(of: "ü", with: "u")
            .replacingOccurrences(of: "ß", with: "ss")

        switch normalized {
        case "executive director", "executive_director", "ed",
             "geschaftsfuhrer", "geschaftsfuhrerin",
             "geschaftsführer", "geschäftsführer", "geschäftsführerin",
             "super_admin", "super admin", "hauptadministrator":
            return "executive_director"
        case "administrator", "admin":
            return "administrator"
        case "senior staff", "senior_staff", "senior-mitarbeiter":
            return "senior_staff"
        case "staff member", "staff_member", "mitarbeiter":
            return "staff_member"
        case "team member", "team_member", "teammitglied":
            return "team_member"
        case "manager":
            return "manager"
        case "staff":
            return "staff"
        default:
            return normalized
        }
    }

    static func isExecutiveRole(_ value: String) -> Bool {
        canonicalKey(for: value) == "executive_director"
    }
}
