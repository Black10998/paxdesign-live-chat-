import Foundation

@MainActor
final class CustomerAPIClient: ObservableObject {
    private var baseURL = AppServerConfig.customerAPIBaseURL
    private weak var auth: CustomerAuthStore?

    func configure(baseURL: String, auth: CustomerAuthStore) {
        if let url = URL(string: baseURL.trimmingCharacters(in: .whitespacesAndNewlines).trimmingSuffix("/") + "/wp-json/pdx/v1") {
            self.baseURL = url
        } else {
            self.baseURL = AppServerConfig.customerAPIBaseURL
        }
        self.auth = auth
    }

    func useDefaultServer() {
        baseURL = AppServerConfig.customerAPIBaseURL
    }

    private var authBaseURL: URL {
        baseURL
    }

    /// Join REST path segments without treating `/customer/...` as site-root absolute paths.
    private func endpointURL(_ path: String) -> URL? {
        let raw = path.trimmingCharacters(in: .whitespacesAndNewlines)
        let pathPart: String
        let queryPart: String?
        if let qIndex = raw.firstIndex(of: "?") {
            pathPart = String(raw[..<qIndex]).trimmingCharacters(in: CharacterSet(charactersIn: "/"))
            queryPart = String(raw[raw.index(after: qIndex)...])
        } else {
            pathPart = raw.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
            queryPart = nil
        }
        var url = baseURL
        if !pathPart.isEmpty {
            for component in pathPart.split(separator: "/") {
                url = url.appendingPathComponent(String(component), isDirectory: false)
            }
        }
        guard let queryPart, !queryPart.isEmpty else { return url }
        guard var components = URLComponents(url: url, resolvingAgainstBaseURL: false) else { return url }
        components.percentEncodedQuery = queryPart
        return components.url
    }

    func authRegister(name: String, email: String, password: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/register", json: ["name": name, "email": email, "password": password], as: CustomerAuthMessageResponse.self)
    }

    func authMobileLogin(login: String, password: String, deviceLabel: String = "PAXDesign iOS") async throws -> MobileLoginResponse {
        try await publicPost(
            "/auth/mobile-login",
            json: ["login": login, "password": password, "device_label": deviceLabel],
            as: MobileLoginResponse.self
        )
    }

    func authMobileLogout(appPasswordUUID: String) async throws {
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL("/auth/mobile-logout") else { throw CustomerAPIError.invalidURL }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: ["app_password_uuid": appPasswordUUID])
        let (_, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
    }

    func authForgotPassword(email: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/forgot-password", json: ["email": email], as: CustomerAuthMessageResponse.self)
    }

    func authVerify(uid: Int, token: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/verify", json: ["uid": String(uid), "token": token], as: CustomerAuthMessageResponse.self)
    }

    func authVerify(email: String, code: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/verify", json: ["email": email, "code": code], as: CustomerAuthMessageResponse.self)
    }

    func authVerify(uid: Int, code: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/verify", json: ["uid": String(uid), "code": code], as: CustomerAuthMessageResponse.self)
    }

    func authResendVerification(email: String) async throws -> CustomerAuthMessageResponse {
        var body: [String: String] = [:]
        if !email.isEmpty { body["email"] = email }
        return try await publicPost("/auth/resend-verification", json: body, as: CustomerAuthMessageResponse.self)
    }

    func registerPush(token: String, deviceID: String, metadata: [String: Any] = [:]) async throws {
        var body: [String: Any] = [
            "token": token,
            "device_id": deviceID,
            "platform": "ios",
            "sandbox": PAXAPNsEnvironment.isSandbox,
        ]
        for (key, value) in metadata where key != "device_id" {
            body[key] = value
        }
        _ = try await requestJSON(path: "/customer/push/register", method: "POST", json: body, as: CustomerEmptyResponse.self)
    }

    func fetchDevices() async throws -> CustomerDevicesResponse {
        let deviceId = PAXDeviceInfo.deviceId.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? PAXDeviceInfo.deviceId
        return try await get("/customer/devices?current_device_id=\(deviceId)", as: CustomerDevicesResponse.self)
    }

    func revokeDevice(deviceId: String) async throws {
        _ = try await requestJSON(path: "/customer/devices/\(deviceId)", method: "DELETE", json: [:], as: CustomerEmptyResponse.self)
    }

    func revokeOtherDevices() async throws {
        _ = try await requestJSON(
            path: "/customer/devices/revoke-others",
            method: "POST",
            json: ["current_device_id": PAXDeviceInfo.deviceId],
            as: CustomerEmptyResponse.self
        )
    }

    func fetchConversations() async throws -> CustomerConversationsResponse {
        try await get("/customer/chat/conversations", as: CustomerConversationsResponse.self)
    }

    func fetchChatSession() async throws -> CustomerChatSessionResponse {
        try await get("/customer/chat/session", as: CustomerChatSessionResponse.self)
    }

    func renewChatSession(closedSessionID: String? = nil, newConversation: Bool = false) async throws -> CustomerChatSessionResponse {
        var body: [String: String] = [:]
        if let closedSessionID, !closedSessionID.isEmpty {
            body["session_id"] = closedSessionID
        }
        if newConversation {
            body["new_conversation"] = "1"
        }
        return try await post("/customer/chat/session", body: body, as: CustomerChatSessionResponse.self)
    }

    func downloadProjectFile(projectId: Int, fileId: Int) async throws -> URL {
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL("/customer/projects/\(projectId)/files/\(fileId)/download") else {
            throw CustomerAPIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.setValue(header, forHTTPHeaderField: "Authorization")
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
        let temp = FileManager.default.temporaryDirectory.appendingPathComponent("pax-project-\(fileId)-\(UUID().uuidString)")
        try data.write(to: temp)
        return temp
    }

    func downloadOrderFile(orderId: Int, fileId: Int) async throws -> URL {
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL("/customer/orders/\(orderId)/files/\(fileId)/download") else {
            throw CustomerAPIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.setValue(header, forHTTPHeaderField: "Authorization")
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
        let temp = FileManager.default.temporaryDirectory.appendingPathComponent("pax-order-\(fileId)-\(UUID().uuidString)")
        try data.write(to: temp)
        return temp
    }

    func fetchFilesLibrary(limit: Int = 50) async throws -> CustomerFilesResponse {
        try await get("/customer/files?limit=\(limit)", as: CustomerFilesResponse.self)
    }

    func fetchPortfolio(category: String? = nil, limit: Int = 100, lang: String? = nil) async throws -> CustomerPortfolioResponse {
        let language = lang ?? Locale.current.language.languageCode?.identifier ?? "de"
        let normalized = language.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? language
        var path = "/customer/portfolio?limit=\(limit)&lang=\(normalized)"
        if let category, !category.isEmpty {
            path += "&category=\(category.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? category)"
        }
        return try await get(path, as: CustomerPortfolioResponse.self)
    }

    func fetchPortfolioShowcase(lang: String? = nil) async throws -> CustomerPortfolioShowcaseResponse {
        let language = lang ?? Locale.current.language.languageCode?.identifier ?? "de"
        let normalized = language.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? language
        return try await get("/content/portfolio-showcase?lang=\(normalized)", as: CustomerPortfolioShowcaseResponse.self)
    }

    func fetchPortfolioItem(slug: String, lang: String? = nil) async throws -> CustomerPortfolioDetail {
        let language = lang ?? Locale.current.language.languageCode?.identifier ?? "de"
        let normalized = language.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? language
        return try await get("/customer/portfolio/\(slug)?lang=\(normalized)", as: CustomerPortfolioDetail.self)
    }

    func sendChatTyping(sessionID: String?, stop: Bool = false) async throws {
        var body: [String: String] = ["stop": stop ? "1" : "0"]
        if let sessionID, !sessionID.isEmpty { body["session_id"] = sessionID }
        _ = try await post("/customer/chat/typing", body: body, as: CustomerEmptyResponse.self)
    }

    func closeChatSession(sessionID: String?) async throws {
        var body: [String: String] = [:]
        if let sessionID, !sessionID.isEmpty { body["session_id"] = sessionID }
        _ = try await post("/customer/chat/close", body: body, as: CustomerEmptyResponse.self)
    }

    func uploadChatImage(sessionID: String, imageData: Data, filename: String, caption: String = "", clientMsgID: String = UUID().uuidString) async throws -> CustomerSendResponse {
        try await uploadMultipart(path: "/customer/chat/messages/image", field: "image", filename: filename, mime: "image/jpeg", data: imageData, fields: [
            "session_id": sessionID,
            "caption": caption,
            "client_msg_id": clientMsgID,
        ], as: CustomerSendResponse.self)
    }

    func uploadChatVoice(sessionID: String, audioData: Data, duration: Double, clientMsgID: String = UUID().uuidString) async throws -> CustomerSendResponse {
        try await uploadMultipart(path: "/customer/chat/messages/voice", field: "audio", filename: "voice.m4a", mime: "audio/mp4", data: audioData, fields: [
            "session_id": sessionID,
            "duration": String(duration),
            "client_msg_id": clientMsgID,
        ], as: CustomerSendResponse.self)
    }

    func uploadChatFile(sessionID: String, fileData: Data, filename: String, clientMsgID: String = UUID().uuidString) async throws -> CustomerSendResponse {
        let mime = mimeType(for: filename)
        return try await uploadMultipart(path: "/customer/chat/messages/file", field: "file", filename: filename, mime: mime, data: fileData, fields: [
            "session_id": sessionID,
            "client_msg_id": clientMsgID,
        ], as: CustomerSendResponse.self)
    }

    private func mimeType(for filename: String) -> String {
        switch (filename as NSString).pathExtension.lowercased() {
        case "pdf": return "application/pdf"
        case "png": return "image/png"
        case "jpg", "jpeg": return "image/jpeg"
        case "m4a", "mp4": return "audio/mp4"
        default: return "application/octet-stream"
        }
    }

    func sendChatLocation(sessionID: String, lat: Double, lng: Double, label: String) async throws -> CustomerSendResponse {
        try await requestJSON(path: "/customer/chat/messages/location", method: "POST", json: [
            "session_id": sessionID,
            "lat": lat,
            "lng": lng,
            "label": label,
            "client_msg_id": UUID().uuidString,
        ], as: CustomerSendResponse.self)
    }

    func fetchDashboard() async throws -> CustomerDashboard {
        try await get("/customer/dashboard", as: CustomerDashboard.self)
    }

    func fetchServices(search: String? = nil, category: String? = nil) async throws -> CustomerServicesResponse {
        var path = "/customer/services"
        var query: [String] = []
        if let search, !search.isEmpty {
            query.append("search=\(search.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? search)")
        }
        if let category, !category.isEmpty {
            query.append("category=\(category.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? category)")
        }
        if !query.isEmpty {
            path += "?" + query.joined(separator: "&")
        }
        return try await get(path, as: CustomerServicesResponse.self)
    }

    func fetchService(slug: String) async throws -> CustomerServiceDetail {
        try await get("/customer/services/\(slug)", as: CustomerServiceDetail.self)
    }

    func fetchContentNavigation() async throws -> CustomerContentNavigation {
        let lang = Locale.current.language.languageCode?.identifier ?? "en"
        return try await get("/content/navigation?lang=\(lang)", as: CustomerContentNavigation.self)
    }

    func fetchServicesCatalog(lang: String) async throws -> CustomerServicesCatalogResponse {
        let normalized = lang.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? lang
        return try await get("/content/services-catalog?lang=\(normalized)", as: CustomerServicesCatalogResponse.self)
    }

    func fetchHomepage(lang: String) async throws -> CustomerHomepageResponse {
        let normalized = lang.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? lang
        return try await get("/content/homepage?lang=\(normalized)", as: CustomerHomepageResponse.self)
    }

    func fetchSiteMenu(lang: String) async throws -> CustomerSiteMenuResponse {
        let normalized = lang.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? lang
        return try await get("/content/site-menu?lang=\(normalized)", as: CustomerSiteMenuResponse.self)
    }

    func fetchAbout(lang: String) async throws -> CustomerAboutResponse {
        let normalized = lang.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? lang
        return try await get("/content/about?lang=\(normalized)", as: CustomerAboutResponse.self)
    }

    func fetchContact(lang: String) async throws -> CustomerContactResponse {
        let normalized = lang.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? lang
        return try await get("/content/contact?lang=\(normalized)", as: CustomerContactResponse.self)
    }

    func fetchLegalPage(slug: String, lang: String) async throws -> CustomerLegalPageResponse {
        let encoded = slug.addingPercentEncoding(withAllowedCharacters: .urlPathAllowed) ?? slug
        let normalized = lang.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? lang
        return try await get("/content/legal/\(encoded)?lang=\(normalized)", as: CustomerLegalPageResponse.self)
    }

    func uploadProfileAvatar(imageData: Data, filename: String = "avatar.jpg", mime: String = "image/jpeg") async throws -> CustomerProfileResponse {
        struct AvatarUploadResponse: Decodable {
            let success: Bool?
            let profile: CustomerProfileResponse.Profile
        }
        let response = try await uploadMultipart(
            path: "/customer/profile/avatar",
            field: "avatar",
            filename: filename,
            mime: mime,
            data: imageData,
            fields: [:],
            as: AvatarUploadResponse.self
        )
        return CustomerProfileResponse(profile: response.profile)
    }

    func fetchContentPage(slug: String) async throws -> CustomerContentPage {
        let lang = Locale.current.language.languageCode?.identifier ?? "en"
        let encoded = slug.addingPercentEncoding(withAllowedCharacters: .urlPathAllowed) ?? slug
        return try await get("/content/pages/\(encoded)?lang=\(lang)", as: CustomerContentPage.self)
    }

    func fetchProjects(status: String? = nil) async throws -> CustomerProjectsResponse {
        var path = "/customer/projects"
        if let status, !status.isEmpty {
            path += "?status=\(status.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? status)"
        }
        return try await get(path, as: CustomerProjectsResponse.self)
    }

    func fetchProject(id: Int) async throws -> CustomerProjectDetail {
        try await get("/customer/projects/\(id)", as: CustomerProjectDetail.self)
    }

    func fetchOrders(status: String? = nil) async throws -> CustomerOrdersResponse {
        var path = "/customer/orders"
        if let status, !status.isEmpty {
            path += "?status=\(status.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? status)"
        }
        return try await get(path, as: CustomerOrdersResponse.self)
    }

    func fetchOrder(id: Int) async throws -> CustomerOrderDetail {
        try await get("/customer/orders/\(id)", as: CustomerOrderDetail.self)
    }

    func createOrder(serviceSlug: String, description: String, projectId: Int? = nil) async throws -> CustomerOrderDetail {
        var body: [String: String] = [
            "service_slug": serviceSlug,
            "description": description,
        ]
        if let projectId { body["project_id"] = String(projectId) }
        return try await post("/customer/orders", body: body, as: CustomerOrderDetail.self)
    }

    func fetchNews() async throws -> CustomerNewsResponse {
        try await get("/customer/news", as: CustomerNewsResponse.self)
    }

    func fetchNewsItem(slug: String) async throws -> CustomerNewsItem {
        try await get("/customer/news/\(slug)", as: CustomerNewsItem.self)
    }

    func fetchNotifications(unreadOnly: Bool = false) async throws -> CustomerNotificationsResponse {
        var path = "/customer/notifications"
        if unreadOnly { path += "?unread=1" }
        return try await get(path, as: CustomerNotificationsResponse.self)
    }

    func markNotificationsRead(ids: [Int]) async throws {
        _ = try await requestJSON(path: "/customer/notifications", method: "PATCH", json: ["ids": ids], as: CustomerEmptyResponse.self)
    }

    func fetchSettings() async throws -> CustomerSettingsResponse {
        try await get("/customer/settings", as: CustomerSettingsResponse.self)
    }

    func updateSettings(_ prefs: CustomerSettingsResponse.NotificationPrefs) async throws -> CustomerSettingsResponse {
        let payload: [String: Any] = [
            "notifications": [
                "chat": prefs.chat,
                "project": prefs.project,
                "order": prefs.order,
                "news": prefs.news,
                "security": prefs.security,
                "push_enabled": prefs.push_enabled,
            ],
        ]
        return try await requestJSON(path: "/customer/settings", method: "PATCH", json: payload, as: CustomerSettingsResponse.self)
    }

    func updateProfile(displayName: String) async throws -> CustomerProfileResponse {
        try await requestJSON(path: "/customer/profile", method: "PATCH", json: ["display_name": displayName], as: CustomerProfileResponse.self)
    }

    func deleteAccount(password: String) async throws {
        _ = try await post("/customer/account/delete", body: ["password": password], as: CustomerEmptyResponse.self)
    }

    func fetchProfile() async throws -> CustomerProfileResponse {
        try await request("/customer/profile", method: "GET", body: nil, as: CustomerProfileResponse.self, allowPreSession: true)
    }

    func fetchChatMessages(sessionID: String? = nil, since: Int = 0, full: Bool = true) async throws -> CustomerChatPoll {
        var path = "/customer/chat/messages?since=\(since)"
        if full { path += "&full=1" }
        if let sessionID, !sessionID.isEmpty {
            path += "&session_id=\(sessionID.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? sessionID)"
        }
        return try await get(path, as: CustomerChatPoll.self)
    }

    func consumeCustomerChatEventStream(
        sessionID: String?,
        since: Int,
        onEvent: @escaping @MainActor (ChatStreamEvent) async -> Void
    ) async throws {
        try await requireReadySession()
        guard let auth, let header = auth.basicAuthHeader else {
            throw CustomerAPIError.unauthorized
        }
        var path = "/customer/chat/events?since=\(since)"
        if let sessionID, !sessionID.isEmpty {
            let encoded = sessionID.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? sessionID
            path += "&session_id=\(encoded)"
        }
        guard let url = endpointURL(path) else {
            throw CustomerAPIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("text/event-stream", forHTTPHeaderField: "Accept")
        request.timeoutInterval = 30

        let (bytes, response) = try await URLSession.shared.bytes(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }

        var dataBuffer = ""
        for try await line in bytes.lines {
            if Task.isCancelled { break }
            if let event = ChatEventStreamParser.parseLine(line, dataBuffer: &dataBuffer) {
                await onEvent(event)
            }
        }
    }

    func sendChatMessage(
        _ message: String,
        sessionID: String? = nil,
        clientMsgID: String = UUID().uuidString,
        assistantClientMsgID: String = UUID().uuidString
    ) async throws -> CustomerSendResponse {
        var body: [String: String] = [
            "message": message,
            "client_msg_id": clientMsgID,
            "assistant_client_msg_id": assistantClientMsgID,
        ]
        if let sessionID, !sessionID.isEmpty {
            body["session_id"] = sessionID
        }
        return try await post("/customer/chat/messages", body: body, as: CustomerSendResponse.self)
    }

    func streamChatMessage(
        _ message: String,
        sessionID: String?,
        onEvent: @escaping (CustomerStreamEvent) -> Void
    ) async throws {
        try await requireReadySession()
        guard let auth, let header = auth.basicAuthHeader else {
            throw CustomerAPIError.unauthorized
        }
        guard let url = endpointURL("/customer/chat/stream") else {
            throw CustomerAPIError.invalidURL
        }
        var body: [String: String] = ["message": message]
        if let sessionID, !sessionID.isEmpty {
            body["session_id"] = sessionID
        }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("text/event-stream", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (bytes, response) = try await URLSession.shared.bytes(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }

        var buffer = ""
        for try await line in bytes.lines {
            if line.hasPrefix("data: ") {
                let payload = String(line.dropFirst(6))
                if payload == "[DONE]" { break }
                if let data = payload.data(using: .utf8),
                   let event = try? JSONDecoder().decode(CustomerStreamEvent.self, from: data) {
                    onEvent(event)
                }
            }
        }
    }

    func get<T: Decodable>(_ path: String, as type: T.Type) async throws -> T {
        try await request(path, method: "GET", body: nil, as: type)
    }

    func post<T: Decodable>(_ path: String, body: [String: String], as type: T.Type) async throws -> T {
        try await request(path, method: "POST", body: body, as: type)
    }

    private func requireReadySession(allowPreSession: Bool = false) async throws {
        guard auth?.basicAuthHeader != nil else {
            throw CustomerAPIError.unauthorized
        }
        let store = AuthStore.shared
        if store.isBootstrapping || allowPreSession {
            return
        }
        guard store.isLoggedIn, store.isCustomerSession else {
            throw CustomerAPIError.unauthorized
        }
    }

    private func requestJSON<T: Decodable>(path: String, method: String, json: [String: Any], as type: T.Type, allowPreSession: Bool = false) async throws -> T {
        try await requireReadySession(allowPreSession: allowPreSession)
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL(path) else { throw CustomerAPIError.invalidURL }
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: json)
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }

    private func publicPost<T: Decodable>(_ path: String, json: [String: String], as type: T.Type) async throws -> T {
        guard let url = endpointURL(path) else { throw CustomerAPIError.invalidURL }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: json)
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse else { throw CustomerAPIError.network }
        guard (200..<300).contains(http.statusCode) else {
            if let apiError = try? JSONDecoder().decode(CustomerAPIErrorPayload.self, from: data) {
                throw CustomerAPIError.server(apiError.message ?? apiError.code ?? "HTTP \(http.statusCode)")
            }
            throw CustomerAPIError.http(http.statusCode)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }

    private func publicPost<T: Decodable>(_ path: String, json: [String: Any], as type: T.Type) async throws -> T {
        guard let url = endpointURL(path) else { throw CustomerAPIError.invalidURL }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: json)
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse else { throw CustomerAPIError.network }
        guard (200..<300).contains(http.statusCode) else {
            if let apiError = try? JSONDecoder().decode(CustomerAPIErrorPayload.self, from: data) {
                throw CustomerAPIError.server(apiError.message ?? apiError.code ?? "HTTP \(http.statusCode)")
            }
            throw CustomerAPIError.http(http.statusCode)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }

    private func uploadMultipart<T: Decodable>(
        path: String,
        field: String,
        filename: String,
        mime: String,
        data: Data,
        fields: [String: String],
        as type: T.Type
    ) async throws -> T {
        try await requireReadySession()
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL(path) else { throw CustomerAPIError.invalidURL }
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        for (key, value) in fields {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"\(key)\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(value)\r\n".data(using: .utf8)!)
        }
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"\(field)\"; filename=\"\(filename)\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: \(mime)\r\n\r\n".data(using: .utf8)!)
        body.append(data)
        body.append("\r\n--\(boundary)--\r\n".data(using: .utf8)!)

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        let (responseData, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
        return try JSONDecoder().decode(T.self, from: responseData)
    }

    private func request<T: Decodable>(_ path: String, method: String, body: [String: String]?, as type: T.Type, allowPreSession: Bool = false) async throws -> T {
        try await requireReadySession(allowPreSession: allowPreSession)
        guard let auth, let header = auth.basicAuthHeader else {
            throw CustomerAPIError.unauthorized
        }
        guard let url = endpointURL(path) else {
            throw CustomerAPIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let body {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
        }
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse else { throw CustomerAPIError.network }
        guard (200..<300).contains(http.statusCode) else {
            throw Self.parseHTTPError(data: data, statusCode: http.statusCode)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }

    private static func parseHTTPError(data: Data, statusCode: Int) -> CustomerAPIError {
        if let apiError = try? JSONDecoder().decode(CustomerAPIErrorPayload.self, from: data),
           let message = apiError.message, !message.isEmpty {
            if let code = apiError.code, !code.isEmpty {
                return .serverCode(code, message)
            }
            return .server(message)
        }
        return .http(statusCode)
    }
}

enum CustomerAPIError: LocalizedError {
    case unauthorized, invalidURL, network
    case http(Int)
    case server(String)
    case serverCode(String, String)

    var errorDescription: String? {
        switch self {
        case .unauthorized:
            return String(localized: "Please sign in to continue.")
        case .invalidURL:
            return String(localized: "Unable to reach the server.")
        case .network:
            return String(localized: "Network error. Check your connection and try again.")
        case .http(let code):
            return CustomerAPIError.friendlyMessage(forHTTP: code)
        case .server(let message):
            return CustomerAPIError.friendlyMessage(forServerText: message)
        case .serverCode(_, let message):
            return CustomerAPIError.friendlyMessage(forServerText: message)
        }
    }

    static func friendlyMessage(forHTTP code: Int) -> String {
        switch code {
        case 401: return String(localized: "Please sign in again to continue.")
        case 403: return String(localized: "You don't have access to this content.")
        case 404: return String(localized: "This item is no longer available.")
        case 409: return String(localized: "This conversation is closed.")
        case 429: return String(localized: "Too many requests. Please wait a moment.")
        case 500...599: return String(localized: "The server is temporarily unavailable. Please try again.")
        default: return String(localized: "Something went wrong. Please try again.")
        }
    }

    static func friendlyMessage(forServerText message: String) -> String {
        let trimmed = message.trimmingCharacters(in: .whitespacesAndNewlines)
        if trimmed.isEmpty {
            return String(localized: "Something went wrong. Please try again.")
        }
        if trimmed.lowercased().contains("server responded with status") {
            return String(localized: "Something went wrong. Please try again.")
        }
        return trimmed
    }
}

private struct CustomerAPIErrorPayload: Decodable {
    let code: String?
    let message: String?
}

private extension String {
    func trimmingSuffix(_ suffix: String) -> String {
        hasSuffix(suffix) ? String(dropLast(suffix.count)) : self
    }
}

struct CustomerDashboard: Decodable {
    struct ChatSummary: Decodable {
        let session_id: String
        let last_preview: String?
        let handler: String?
        let message_count: Int?
    }
    struct ProjectSummary: Decodable {
        let id: Int
        let title: String
        let progress: Int
        let status: String
    }
    struct OrderSummary: Decodable {
        let id: Int
        let ref: String
        let service_label: String
        let status: String
    }
    struct NewsItem: Decodable {
        let slug: String
        let title: String
        let excerpt: String?
        let image_url: String?
        let published_at: String?
    }
    struct PortfolioPreview: Decodable, Identifiable {
        var id: String { slug }
        let slug: String
        let title: String
        let excerpt: String?
        let image_url: String?
    }
    let projects_active: [ProjectSummary]?
    let orders_recent: [OrderSummary]?
    let news: [NewsItem]?
    let portfolio: [PortfolioPreview]?
    let files_count: Int?
    let services_featured: [CustomerServicesResponse.Service]?
    let unread_count: Int?
    let chat: ChatSummary?
}

struct CustomerServicesResponse: Decodable {
    struct Service: Decodable, Identifiable {
        var id: String { slug }
        let slug: String
        let name: String
        let category: String
        let description: String
        let featured: Bool
        let image_url: String?
        let icon_key: String?
        let order_url: String?
    }
    struct Category: Decodable, Identifiable {
        var id: String { slug }
        let slug: String
        let name: String
    }
    let categories: [Category]
    let services: [Service]
}

struct CustomerChatPoll: Decodable {
    struct ChatMessage: Decodable, Identifiable {
        var id: Int { seq }
        let seq: Int
        let role: String
        let content: String
        let sender_name: String?
        let sender_id: Int?
        let sender_avatar: String?
        let sender_role: String?
        let image_url: String?
        let audio_url: String?
        let attachment_type: String?
        let location_lat: Double?
        let location_lng: Double?
        let location_label: String?
        let file_url: String?
        let file_name: String?

        enum CodingKeys: String, CodingKey {
            case seq, id, role, content
            case sender_name, sender_id, sender_avatar, sender_role
            case image_url, audio_url, attachment_type
            case location_lat, location_lng, location_label
            case file_url, file_name
        }

        init(
            seq: Int,
            role: String,
            content: String,
            sender_name: String? = nil,
            sender_id: Int? = nil,
            sender_avatar: String? = nil,
            sender_role: String? = nil,
            image_url: String? = nil,
            audio_url: String? = nil,
            attachment_type: String? = nil,
            location_lat: Double? = nil,
            location_lng: Double? = nil,
            location_label: String? = nil,
            file_url: String? = nil,
            file_name: String? = nil
        ) {
            self.seq = seq
            self.role = role
            self.content = content
            self.sender_name = sender_name
            self.sender_id = sender_id
            self.sender_avatar = sender_avatar
            self.sender_role = sender_role
            self.image_url = image_url
            self.audio_url = audio_url
            self.attachment_type = attachment_type
            self.location_lat = location_lat
            self.location_lng = location_lng
            self.location_label = location_label
            self.file_url = file_url
            self.file_name = file_name
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            let decodedSeq = CustomerPortalDecode.int(container, .seq)
            let decodedID = CustomerPortalDecode.int(container, .id)
            seq = decodedSeq > 0 ? decodedSeq : decodedID
            role = CustomerPortalDecode.string(container, .role)
            content = CustomerPortalDecode.string(container, .content)
            sender_name = try? container.decodeIfPresent(String.self, forKey: .sender_name)
            sender_id = CustomerPortalDecode.optionalInt(container, .sender_id)
            sender_avatar = try? container.decodeIfPresent(String.self, forKey: .sender_avatar)
            sender_role = try? container.decodeIfPresent(String.self, forKey: .sender_role)
            image_url = try? container.decodeIfPresent(String.self, forKey: .image_url)
            audio_url = try? container.decodeIfPresent(String.self, forKey: .audio_url)
            attachment_type = try? container.decodeIfPresent(String.self, forKey: .attachment_type)
            location_lat = Self.optionalDouble(container, .location_lat)
            location_lng = Self.optionalDouble(container, .location_lng)
            location_label = try? container.decodeIfPresent(String.self, forKey: .location_label)
            file_url = try? container.decodeIfPresent(String.self, forKey: .file_url)
            file_name = try? container.decodeIfPresent(String.self, forKey: .file_name)
        }

        private static func optionalDouble<C: CodingKey>(
            _ container: KeyedDecodingContainer<C>,
            _ key: C
        ) -> Double? {
            if let value = try? container.decodeIfPresent(Double.self, forKey: key) {
                return value
            }
            if let value = try? container.decodeIfPresent(String.self, forKey: key),
               let parsed = Double(value) {
                return parsed
            }
            return nil
        }
    }

    let session_id: String?
    let handler: String?
    let messages: [ChatMessage]?
    let message_count: Int?
    let last_preview: String?
    let notice: String?
    let admin_typing: Bool?
    let user_typing: Bool?
    let other_read_seq: Int?

    enum CodingKeys: String, CodingKey {
        case session_id, handler, messages, message_count, last_preview, notice
        case admin_typing, user_typing, other_read_seq, admin_read_seq
    }

    init(
        session_id: String?,
        handler: String?,
        messages: [ChatMessage]?,
        message_count: Int?,
        last_preview: String?,
        notice: String? = nil,
        admin_typing: Bool? = nil,
        user_typing: Bool? = nil,
        other_read_seq: Int? = nil
    ) {
        self.session_id = session_id
        self.handler = handler
        self.messages = messages
        self.message_count = message_count
        self.last_preview = last_preview
        self.notice = notice
        self.admin_typing = admin_typing
        self.user_typing = user_typing
        self.other_read_seq = other_read_seq
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        session_id = try? container.decodeIfPresent(String.self, forKey: .session_id)
        handler = try? container.decodeIfPresent(String.self, forKey: .handler)
        messages = CustomerPortalDecode.decodeChatMessages(from: container, key: .messages)
        message_count = CustomerPortalDecode.optionalInt(container, .message_count)
        last_preview = try? container.decodeIfPresent(String.self, forKey: .last_preview)
        notice = try? container.decodeIfPresent(String.self, forKey: .notice)
        admin_typing = CustomerPortalDecode.optionalBool(container, .admin_typing)
        user_typing = CustomerPortalDecode.optionalBool(container, .user_typing)
        if let other = CustomerPortalDecode.optionalInt(container, .other_read_seq), other > 0 {
            other_read_seq = other
        } else {
            other_read_seq = CustomerPortalDecode.optionalInt(container, .admin_read_seq)
        }
    }
}

struct CustomerSendResponse: Decodable {
    let session_id: String
    let handler: String?
    let message: CustomerChatPoll.ChatMessage?
    let assistant: CustomerChatPoll.ChatMessage?
    let notice: String?
    let renewed: Bool?
    let handoff: Bool?

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        session_id = CustomerPortalDecode.string(container, .session_id)
        handler = try? container.decodeIfPresent(String.self, forKey: .handler)
        message = try? container.decodeIfPresent(CustomerChatPoll.ChatMessage.self, forKey: .message)
        assistant = try? container.decodeIfPresent(CustomerChatPoll.ChatMessage.self, forKey: .assistant)
        notice = try? container.decodeIfPresent(String.self, forKey: .notice)
        renewed = CustomerPortalDecode.optionalBool(container, .renewed)
        handoff = CustomerPortalDecode.optionalBool(container, .handoff)
    }

    enum CodingKeys: String, CodingKey {
        case session_id, handler, message, assistant, notice, renewed, handoff
    }
}

struct CustomerChatSessionResponse: Decodable {
    let session_id: String
    let handler: String?
    let renewed: Bool?
}

struct CustomerStreamEvent: Decodable {
    let type: String
    let text: String?
    let message: CustomerChatPoll.ChatMessage?
}

struct CustomerEmptyResponse: Decodable {}

struct CustomerAuthMessageResponse: Decodable {
    let success: Bool?
    let message: String?
    let expires_in_hours: Int?
}

struct MobileLoginResponse: Decodable {
    let success: Bool?
    let message: String?
    let session_mode: String?
    let username: String?
    let app_password: String?
    let app_password_uuid: String?
    let role: String?
    let user: MobileLoginUser?
}

struct MobileLoginUser: Decodable {
    let id: Int?
    let display_name: String?
    let email: String?
    let verified: Bool?
}

struct CustomerConversationsResponse: Decodable {
    let conversations: [CustomerConversation]
}

struct CustomerConversation: Decodable, Identifiable {
    var id: String { session_id }
    let session_id: String
    let last_preview: String?
    let handler: String?
    let message_count: Int?
    let updated_at: String?

    enum CodingKeys: String, CodingKey {
        case session_id, last_preview, handler, message_count, updated_at
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        session_id = CustomerPortalDecode.string(container, .session_id)
        last_preview = try container.decodeIfPresent(String.self, forKey: .last_preview)
        handler = try container.decodeIfPresent(String.self, forKey: .handler)
        message_count = CustomerPortalDecode.optionalInt(container, .message_count)
        updated_at = try container.decodeIfPresent(String.self, forKey: .updated_at)
    }
}

extension CustomerConversation {
    var handlerLabel: String {
        switch handler?.lowercased() {
        case "closed":
            return String(localized: "Closed")
        case "admin", "live_request":
            return String(localized: "Support")
        case "ai", .none, "":
            return String(localized: "AI Assistant")
        default:
            return String(localized: "Conversation")
        }
    }
}
