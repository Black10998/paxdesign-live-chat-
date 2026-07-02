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
    let viewChats: Bool
    let replyChats: Bool
    let useAI: Bool
    let sendImages: Bool
    let manageSettings: Bool
    let viewRatings: Bool
    let manageUsers: Bool
    let accessSecurity: Bool

    enum CodingKeys: String, CodingKey {
        case viewChats = "view_chats"
        case replyChats = "reply_chats"
        case useAI = "use_ai"
        case sendImages = "send_images"
        case manageSettings = "manage_settings"
        case viewRatings = "view_ratings"
        case manageUsers = "manage_users"
        case accessSecurity = "access_security"
    }

    init(
        viewChats: Bool = true,
        replyChats: Bool = true,
        useAI: Bool = true,
        sendImages: Bool = true,
        manageSettings: Bool = true,
        viewRatings: Bool = true,
        manageUsers: Bool = true,
        accessSecurity: Bool = true
    ) {
        self.viewChats = viewChats
        self.replyChats = replyChats
        self.useAI = useAI
        self.sendImages = sendImages
        self.manageSettings = manageSettings
        self.viewRatings = viewRatings
        self.manageUsers = manageUsers
        self.accessSecurity = accessSecurity
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
    }

    static let full = AdminPermissions()
}
