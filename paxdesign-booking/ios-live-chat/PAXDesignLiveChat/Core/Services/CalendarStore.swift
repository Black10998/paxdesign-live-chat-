import Foundation

struct PAXCalendarEvent: Codable, Identifiable, Hashable {
    let id: String
    var title: String
    var notes: String
    var startDate: Date
    var endDate: Date
    var category: EventCategory

    enum EventCategory: String, Codable, CaseIterable, Identifiable {
        case meeting, appointment, reminder, liveSession

        var id: String { rawValue }

        var title: String {
            switch self {
            case .meeting: return L10n.CalendarCategoryMeeting
            case .appointment: return L10n.CalendarCategoryAppointment
            case .reminder: return L10n.CalendarCategoryReminder
            case .liveSession: return L10n.CalendarCategoryLive
            }
        }

        var systemImage: String {
            switch self {
            case .meeting: return "person.2.fill"
            case .appointment: return "calendar.badge.clock"
            case .reminder: return "bell.fill"
            case .liveSession: return "video.fill"
            }
        }
    }

    init(title: String, notes: String = "", startDate: Date, endDate: Date, category: EventCategory = .appointment) {
        self.id = UUID().uuidString
        self.title = title
        self.notes = notes
        self.startDate = startDate
        self.endDate = endDate
        self.category = category
    }
}

@MainActor
final class CalendarStore: ObservableObject {
    static let shared = CalendarStore()

    @Published private(set) var events: [PAXCalendarEvent] = []
    @Published private(set) var upcomingCache: [PAXCalendarEvent] = []

    private let storageKey = "pax.calendar.events"

    private init() {
        load()
    }

    func applyServerEvents(_ items: [PAXCalendarEvent], upcoming: [PAXCalendarEvent]) {
        events = items.sorted { $0.startDate < $1.startDate }
        upcomingCache = upcoming
        persist()
    }

    func resetForLogout() {
        events = []
        upcomingCache = []
        UserDefaults.standard.removeObject(forKey: storageKey)
    }

    func events(on day: Date) -> [PAXCalendarEvent] {
        let calendar = Calendar.current
        return events.filter { calendar.isDate($0.startDate, inSameDayAs: day) }
            .sorted { $0.startDate < $1.startDate }
    }

    func upcoming(limit: Int = 5) -> [PAXCalendarEvent] {
        if !upcomingCache.isEmpty {
            return Array(upcomingCache.prefix(limit))
        }
        let now = Date()
        return events.filter { $0.endDate >= now }.sorted { $0.startDate < $1.startDate }.prefix(limit).map { $0 }
    }

    func add(title: String, notes: String = "", startDate: Date, endDate: Date, category: PAXCalendarEvent.EventCategory = .appointment, auth: AuthStore) async {
        var event = PAXCalendarEvent(title: title, notes: notes, startDate: startDate, endDate: endDate, category: category)
        if let api = auth.api {
            if let saved = try? await api.savePlatformEvent(event.apiPayload()) {
                event = PAXCalendarEvent(api: saved)
            } else {
                return
            }
        }
        events.append(event)
        events.sort { $0.startDate < $1.startDate }
        upcomingCache = upcoming(limit: 8)
        persist()
        await ActivityLogService.shared.log(
            category: L10n.ModuleCalendar,
            title: L10n.ActivityEventCreated,
            detail: title,
            module: PlatformModule.calendar.rawValue,
            severity: .action,
            auth: auth
        )
    }

    func delete(_ event: PAXCalendarEvent, auth: AuthStore) async {
        if let api = auth.api {
            _ = try? await api.deletePlatformEvent(id: event.id)
        }
        events.removeAll { $0.id == event.id }
        upcomingCache = upcoming(limit: 8)
        persist()
    }

    private func load() {
        guard let data = UserDefaults.standard.data(forKey: storageKey),
              let decoded = try? JSONDecoder().decode([PAXCalendarEvent].self, from: data) else { return }
        events = decoded
    }

    private func persist() {
        guard let data = try? JSONEncoder().encode(events) else { return }
        DeferredUserDefaults.setData(data, forKey: storageKey)
    }
}
