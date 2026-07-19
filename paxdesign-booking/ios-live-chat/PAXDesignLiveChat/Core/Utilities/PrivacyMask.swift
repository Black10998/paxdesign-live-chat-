import Foundation

enum PrivacyMask {
    static func email(_ email: String, revealFull: Bool) -> String {
        let trimmed = email.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return "—" }
        if revealFull { return trimmed }

        let parts = trimmed.split(separator: "@", maxSplits: 1).map(String.init)
        guard parts.count == 2 else { return trimmed }

        let local = parts[0]
        let domainLabel = parts[1]

        let visible = String(local.prefix(min(3, local.count)))
        let maskedLocal = local.count <= 3 ? "\(visible)*" : "\(visible)***"
        let formattedDomain: String
        if let dot = domainLabel.firstIndex(of: ".") {
            let name = String(domainLabel[..<dot])
            let ext = String(domainLabel[domainLabel.index(after: dot)...])
            let cap = name.prefix(1).uppercased() + name.dropFirst().lowercased()
            formattedDomain = "\(cap).\(ext)"
        } else {
            formattedDomain = domainLabel.prefix(1).uppercased() + domainLabel.dropFirst().lowercased()
        }
        return "\(maskedLocal)@\(formattedDomain)"
    }
}

struct AdminPermissions: Codable, Equatable {
    var viewChats: Bool
    var replyChats: Bool
    var useAI: Bool
    var sendImages: Bool
    var manageSettings: Bool
    var viewRatings: Bool
    var manageUsers: Bool
    var accessSecurity: Bool
    var manageTeamPermissions: Bool
    var manageCustomerProfiles: Bool
    var assignTeamTasks: Bool
    var customizeHubProfile: Bool
    var takeoverChats: Bool

    enum CodingKeys: String, CodingKey {
        case viewChats = "view_chats"
        case replyChats = "reply_chats"
        case useAI = "use_ai"
        case sendImages = "send_images"
        case manageSettings = "manage_settings"
        case viewRatings = "view_ratings"
        case manageUsers = "manage_users"
        case accessSecurity = "access_security"
        case manageTeamPermissions = "manage_team_permissions"
        case manageCustomerProfiles = "manage_customer_profiles"
        case assignTeamTasks = "assign_team_tasks"
        case customizeHubProfile = "customize_hub_profile"
        case takeoverChats = "takeover_chats"
    }

    init(
        viewChats: Bool = true,
        replyChats: Bool = true,
        useAI: Bool = true,
        sendImages: Bool = true,
        manageSettings: Bool = true,
        viewRatings: Bool = true,
        manageUsers: Bool = true,
        accessSecurity: Bool = true,
        manageTeamPermissions: Bool = true,
        manageCustomerProfiles: Bool = true,
        assignTeamTasks: Bool = true,
        customizeHubProfile: Bool = true,
        takeoverChats: Bool = true
    ) {
        self.viewChats = viewChats
        self.replyChats = replyChats
        self.useAI = useAI
        self.sendImages = sendImages
        self.manageSettings = manageSettings
        self.viewRatings = viewRatings
        self.manageUsers = manageUsers
        self.accessSecurity = accessSecurity
        self.manageTeamPermissions = manageTeamPermissions
        self.manageCustomerProfiles = manageCustomerProfiles
        self.assignTeamTasks = assignTeamTasks
        self.customizeHubProfile = customizeHubProfile
        self.takeoverChats = takeoverChats
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        viewChats = (try? c.decode(Bool.self, forKey: .viewChats)) ?? false
        replyChats = (try? c.decode(Bool.self, forKey: .replyChats)) ?? false
        useAI = (try? c.decode(Bool.self, forKey: .useAI)) ?? false
        sendImages = (try? c.decode(Bool.self, forKey: .sendImages)) ?? false
        manageSettings = (try? c.decode(Bool.self, forKey: .manageSettings)) ?? false
        viewRatings = (try? c.decode(Bool.self, forKey: .viewRatings)) ?? false
        manageUsers = (try? c.decode(Bool.self, forKey: .manageUsers)) ?? false
        accessSecurity = (try? c.decode(Bool.self, forKey: .accessSecurity)) ?? false
        manageTeamPermissions = (try? c.decode(Bool.self, forKey: .manageTeamPermissions)) ?? false
        manageCustomerProfiles = (try? c.decode(Bool.self, forKey: .manageCustomerProfiles)) ?? false
        assignTeamTasks = (try? c.decode(Bool.self, forKey: .assignTeamTasks)) ?? false
        customizeHubProfile = (try? c.decode(Bool.self, forKey: .customizeHubProfile)) ?? false
        takeoverChats = (try? c.decode(Bool.self, forKey: .takeoverChats)) ?? false

        if manageUsers {
            manageTeamPermissions = true
            manageCustomerProfiles = true
            assignTeamTasks = true
            customizeHubProfile = true
            takeoverChats = true
        }
        if manageSettings {
            customizeHubProfile = true
        }
    }

    static let full = AdminPermissions()

    var apiDictionary: [String: Bool] {
        [
            CodingKeys.viewChats.rawValue: viewChats,
            CodingKeys.replyChats.rawValue: replyChats,
            CodingKeys.useAI.rawValue: useAI,
            CodingKeys.sendImages.rawValue: sendImages,
            CodingKeys.manageSettings.rawValue: manageSettings,
            CodingKeys.viewRatings.rawValue: viewRatings,
            CodingKeys.manageUsers.rawValue: manageUsers,
            CodingKeys.accessSecurity.rawValue: accessSecurity,
            CodingKeys.manageTeamPermissions.rawValue: manageTeamPermissions,
            CodingKeys.manageCustomerProfiles.rawValue: manageCustomerProfiles,
            CodingKeys.assignTeamTasks.rawValue: assignTeamTasks,
            CodingKeys.customizeHubProfile.rawValue: customizeHubProfile,
        ]
    }
}
