import Foundation

enum PlatformISO {
    private static let fractional: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter
    }()

    private static let standard: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        return formatter
    }()

    static func date(from value: String?) -> Date? {
        guard let value, !value.isEmpty else { return nil }
        return fractional.date(from: value) ?? standard.date(from: value)
    }

    static func string(from date: Date) -> String {
        standard.string(from: date)
    }
}

struct ModulePermissions: Codable, Equatable {
    let viewDashboard: Bool
    let viewCalendar: Bool
    let viewTasks: Bool
    let viewFiles: Bool
    let viewReports: Bool
    let viewActivityLog: Bool
    let viewEmployeeDashboard: Bool
    let manageTasks: Bool
    let manageCalendar: Bool
    let manageFiles: Bool
    let exportReports: Bool

    enum CodingKeys: String, CodingKey {
        case viewDashboard = "view_dashboard"
        case viewCalendar = "view_calendar"
        case viewTasks = "view_tasks"
        case viewFiles = "view_files"
        case viewReports = "view_reports"
        case viewActivityLog = "view_activity_log"
        case viewEmployeeDashboard = "view_employee_dashboard"
        case manageTasks = "manage_tasks"
        case manageCalendar = "manage_calendar"
        case manageFiles = "manage_files"
        case exportReports = "export_reports"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        viewDashboard = (try? container.decode(Bool.self, forKey: .viewDashboard)) ?? false
        viewCalendar = (try? container.decode(Bool.self, forKey: .viewCalendar)) ?? false
        viewTasks = (try? container.decode(Bool.self, forKey: .viewTasks)) ?? false
        viewFiles = (try? container.decode(Bool.self, forKey: .viewFiles)) ?? false
        viewReports = (try? container.decode(Bool.self, forKey: .viewReports)) ?? false
        viewActivityLog = (try? container.decode(Bool.self, forKey: .viewActivityLog)) ?? false
        viewEmployeeDashboard = (try? container.decode(Bool.self, forKey: .viewEmployeeDashboard)) ?? false
        manageTasks = (try? container.decode(Bool.self, forKey: .manageTasks)) ?? false
        manageCalendar = (try? container.decode(Bool.self, forKey: .manageCalendar)) ?? false
        manageFiles = (try? container.decode(Bool.self, forKey: .manageFiles)) ?? false
        exportReports = (try? container.decode(Bool.self, forKey: .exportReports)) ?? false
    }
}

struct APITaskRecord: Codable {
    let id: String
    let title: String
    let notes: String
    let dueDate: String?
    let isCompleted: Bool
    let priority: String
    let createdAt: String?
    let updatedAt: String?
    let createdBy: Int?
    let assignedTo: Int?
    let assignedName: String?

    enum CodingKeys: String, CodingKey {
        case id, title, notes, priority
        case dueDate = "due_date"
        case isCompleted = "is_completed"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case createdBy = "created_by"
        case assignedTo = "assigned_to"
        case assignedName = "assigned_name"
    }
}

struct TeamMemberRecord: Codable, Identifiable, Hashable {
    var id: Int { userId }
    let userId: Int
    let name: String
    let email: String
    let role: String

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name, email, role
    }
}

struct CustomerVisibleDetails: Codable, Hashable {
    let showEmail: Bool
    let showPhone: Bool
    let showCompany: Bool
    let showNotes: Bool

    enum CodingKeys: String, CodingKey {
        case showEmail = "show_email"
        case showPhone = "show_phone"
        case showCompany = "show_company"
        case showNotes = "show_notes"
    }
}

struct CustomerProfileRecord: Codable, Identifiable, Hashable {
    var id: String { sessionId }
    let sessionId: String
    let displayName: String
    let avatarUrl: String
    let email: String
    let phone: String
    let company: String
    let notes: String
    let visibleDetails: CustomerVisibleDetails
    let updatedAt: String?

    enum CodingKeys: String, CodingKey {
        case sessionId = "session_id"
        case displayName = "display_name"
        case avatarUrl = "avatar_url"
        case email
        case phone
        case company
        case notes
        case visibleDetails = "visible_details"
        case updatedAt = "updated_at"
    }
}

struct APICalendarRecord: Codable {
    let id: String
    let title: String
    let notes: String
    let startDate: String
    let endDate: String
    let category: String

    enum CodingKeys: String, CodingKey {
        case id, title, notes, category
        case startDate = "start_date"
        case endDate = "end_date"
    }
}

struct APIFileRecord: Codable {
    let id: String
    let name: String
    let category: String
    let sizeLabel: String
    let detail: String
    let modifiedAt: String?
    let url: String?

    enum CodingKeys: String, CodingKey {
        case id, name, category, detail, url
        case sizeLabel = "size_label"
        case modifiedAt = "modified_at"
    }
}

struct APIActivityRecord: Codable {
    let id: String
    let timestamp: String
    let category: String
    let title: String
    let detail: String
    let module: String
    let severity: String
}

struct PlatformChartPoint: Codable, Identifiable {
    var id: String { label }
    let label: String
    let value: Int
}

struct PlatformActivityDay: Codable, Identifiable, Equatable {
    var id: String { label }
    let label: String
    let sessions: Int
    let messages: Int
    let liveRequests: Int
    let teamMessages: Int

    enum CodingKeys: String, CodingKey {
        case label
        case sessions
        case messages
        case liveRequests = "live_requests"
        case teamMessages = "team_messages"
    }

    init(label: String, sessions: Int = 0, messages: Int = 0, liveRequests: Int = 0, teamMessages: Int = 0) {
        self.label = label
        self.sessions = sessions
        self.messages = messages
        self.liveRequests = liveRequests
        self.teamMessages = teamMessages
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        label = try container.decode(String.self, forKey: .label)
        sessions = try container.decodeIfPresent(Int.self, forKey: .sessions) ?? 0
        messages = try container.decodeIfPresent(Int.self, forKey: .messages) ?? 0
        liveRequests = try container.decodeIfPresent(Int.self, forKey: .liveRequests) ?? 0
        teamMessages = try container.decodeIfPresent(Int.self, forKey: .teamMessages) ?? 0
    }
}

struct PlatformDashboardTrends: Codable, Equatable {
    let sessionsPct: Double
    let messagesPct: Double
    let liveRequestsPct: Double

    enum CodingKeys: String, CodingKey {
        case sessionsPct = "sessions_pct"
        case messagesPct = "messages_pct"
        case liveRequestsPct = "live_requests_pct"
    }

    init(sessionsPct: Double = 0, messagesPct: Double = 0, liveRequestsPct: Double = 0) {
        self.sessionsPct = sessionsPct
        self.messagesPct = messagesPct
        self.liveRequestsPct = liveRequestsPct
    }
}

struct PlatformDashboardPayload: Codable {
    let sessionsTotal: Int
    let liveCount: Int
    let openTasks: Int
    let overdueTasks: Int
    let upcomingEvents: Int
    let activityChart: [PlatformChartPoint]
    let activitySeries: [PlatformActivityDay]
    let trends: PlatformDashboardTrends
    let categoryTotals: [PlatformReportSlice]
    let serverTime: String?

    enum CodingKeys: String, CodingKey {
        case sessionsTotal = "sessions_total"
        case liveCount = "live_count"
        case openTasks = "open_tasks"
        case overdueTasks = "overdue_tasks"
        case upcomingEvents = "upcoming_events"
        case activityChart = "activity_chart"
        case activitySeries = "activity_series"
        case trends
        case categoryTotals = "category_totals"
        case serverTime = "server_time"
    }

    init(
        sessionsTotal: Int,
        liveCount: Int,
        openTasks: Int,
        overdueTasks: Int,
        upcomingEvents: Int,
        activityChart: [PlatformChartPoint],
        activitySeries: [PlatformActivityDay] = [],
        trends: PlatformDashboardTrends = PlatformDashboardTrends(),
        categoryTotals: [PlatformReportSlice] = [],
        serverTime: String? = nil
    ) {
        self.sessionsTotal = sessionsTotal
        self.liveCount = liveCount
        self.openTasks = openTasks
        self.overdueTasks = overdueTasks
        self.upcomingEvents = upcomingEvents
        self.activityChart = activityChart
        self.activitySeries = activitySeries
        self.trends = trends
        self.categoryTotals = categoryTotals
        self.serverTime = serverTime
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        sessionsTotal = try container.decodeIfPresent(Int.self, forKey: .sessionsTotal) ?? 0
        liveCount = try container.decodeIfPresent(Int.self, forKey: .liveCount) ?? 0
        openTasks = try container.decodeIfPresent(Int.self, forKey: .openTasks) ?? 0
        overdueTasks = try container.decodeIfPresent(Int.self, forKey: .overdueTasks) ?? 0
        upcomingEvents = try container.decodeIfPresent(Int.self, forKey: .upcomingEvents) ?? 0
        activityChart = try container.decodeIfPresent([PlatformChartPoint].self, forKey: .activityChart) ?? []
        activitySeries = try container.decodeIfPresent([PlatformActivityDay].self, forKey: .activitySeries) ?? []
        trends = try container.decodeIfPresent(PlatformDashboardTrends.self, forKey: .trends) ?? PlatformDashboardTrends()
        categoryTotals = try container.decodeIfPresent([PlatformReportSlice].self, forKey: .categoryTotals) ?? []
        serverTime = try container.decodeIfPresent(String.self, forKey: .serverTime)
    }
}

struct PlatformReportSlice: Codable, Identifiable {
    var id: String { label }
    let label: String
    let value: Int
}

struct PlatformReportsPayload: Codable {
    let overview: PlatformDashboardPayload
    let sessionMix: [PlatformReportSlice]

    enum CodingKeys: String, CodingKey {
        case overview
        case sessionMix = "session_mix"
    }
}

struct PlatformEmployeePayload: Codable {
    let userId: Int
    let name: String
    let roleLabel: String
    let assignedChats: Int
    let unreadChats: Int
    let openTasks: Int
    let modulePermissions: ModulePermissions?

    enum CodingKeys: String, CodingKey {
        case name
        case userId = "user_id"
        case roleLabel = "role_label"
        case assignedChats = "assigned_chats"
        case unreadChats = "unread_chats"
        case openTasks = "open_tasks"
        case modulePermissions = "module_permissions"
    }
}

struct PlatformNotificationsSummary: Codable {
    let unreadChats: Int
    let liveRequests: Int
    let openTasks: Int
    let serverTime: String?

    enum CodingKeys: String, CodingKey {
        case unreadChats = "unread_chats"
        case liveRequests = "live_requests"
        case openTasks = "open_tasks"
        case serverTime = "server_time"
    }
}

struct PlatformSearchHit: Codable {
    let type: String
    let id: String
    let title: String
    let subtitle: String
    let module: String
}

struct PlatformSearchResponse: Codable {
    let results: [PlatformSearchHit]
}

struct PlatformSyncResponse: Codable {
    let dashboard: PlatformDashboardPayload
    let reports: PlatformReportsPayload
    let employee: PlatformEmployeePayload
    let notifications: PlatformNotificationsSummary
    let tasks: [APITaskRecord]
    let calendar: [APICalendarRecord]
    let upcoming: [APICalendarRecord]
    let files: [APIFileRecord]
    let activity: [APIActivityRecord]
    let settings: [String: Bool]
    let permissions: PlatformPermissionsEnvelope
}

struct PlatformPermissionsEnvelope: Codable {
    let permissions: AdminPermissions
    let modulePermissions: ModulePermissions

    enum CodingKeys: String, CodingKey {
        case permissions
        case modulePermissions = "module_permissions"
    }
}

extension PAXTaskItem {
    init(api record: APITaskRecord) {
        id = record.id
        title = record.title
        notes = record.notes
        dueDate = PlatformISO.date(from: record.dueDate)
        isCompleted = record.isCompleted
        priority = PAXTaskItem.Priority(rawValue: record.priority) ?? .medium
        createdAt = PlatformISO.date(from: record.createdAt) ?? Date()
        createdByUserId = record.createdBy ?? 0
        assignedUserId = record.assignedTo ?? 0
        assignedUserName = record.assignedName ?? ""
    }

    func apiPayload() -> [String: Any] {
        var payload: [String: Any] = [
            "id": id,
            "title": title,
            "notes": notes,
            "is_completed": isCompleted,
            "priority": priority.rawValue,
        ]
        if let dueDate {
            payload["due_date"] = PlatformISO.string(from: dueDate)
        }
        if assignedUserId > 0 {
            payload["assigned_to"] = assignedUserId
        } else {
            payload["assigned_to"] = 0
        }
        return payload
    }
}

extension PAXCalendarEvent {
    init(api record: APICalendarRecord) {
        id = record.id
        title = record.title
        notes = record.notes
        startDate = PlatformISO.date(from: record.startDate) ?? Date()
        endDate = PlatformISO.date(from: record.endDate) ?? Date()
        category = PAXCalendarEvent.EventCategory(rawValue: record.category) ?? .appointment
    }

    func apiPayload() -> [String: Any] {
        [
            "id": id,
            "title": title,
            "notes": notes,
            "start_date": PlatformISO.string(from: startDate),
            "end_date": PlatformISO.string(from: endDate),
            "category": category.rawValue,
        ]
    }
}

extension PAXDocumentItem {
    init(api record: APIFileRecord) {
        id = record.id
        name = record.name
        category = PAXDocumentItem.DocumentCategory(rawValue: record.category) ?? .other
        sizeLabel = record.sizeLabel
        modifiedAt = PlatformISO.date(from: record.modifiedAt) ?? Date()
        detail = record.detail
    }

    func apiPayload() -> [String: Any] {
        [
            "id": id,
            "name": name,
            "category": category.rawValue,
            "size_label": sizeLabel,
            "detail": detail,
            "modified_at": PlatformISO.string(from: modifiedAt),
        ]
    }
}

extension ActivityLogEntry {
    init(api record: APIActivityRecord) {
        id = record.id
        timestamp = PlatformISO.date(from: record.timestamp) ?? Date()
        category = record.category
        title = record.title
        detail = record.detail
        module = record.module
        severity = ActivityLogEntry.Severity(rawValue: record.severity) ?? .info
    }
}
