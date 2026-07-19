import Foundation

enum AuthDeepLinkHandler {
    static func handle(_ url: URL) -> Bool {
        guard url.scheme?.lowercased() == "paxlivechat" else { return false }

        if WidgetDeepLinkHandler.handle(url) {
            return true
        }

        if url.host?.lowercased() == "verify" {
            let components = URLComponents(url: url, resolvingAgainstBaseURL: false)
            let query = components?.queryItems ?? []
            let uid = query.first(where: { $0.name == "uid" })?.value
            let token = query.first(where: { $0.name == "token" })?.value

            guard let uid, let token, !uid.isEmpty, !token.isEmpty else { return false }

            Task { @MainActor in
                let api = CustomerAPIClient()
                api.useDefaultServer()
                do {
                    _ = try await api.authVerify(uid: Int(uid) ?? 0, token: token)
                    NotificationCenter.default.post(
                        name: .paxEmailVerificationDeepLink,
                        object: nil,
                        userInfo: ["verified": true]
                    )
                } catch {
                    NotificationCenter.default.post(
                        name: .paxEmailVerificationDeepLink,
                        object: nil,
                        userInfo: ["error": error.localizedDescription]
                    )
                }
            }
            return true
        }

        return false
    }
}
