import Foundation

enum MessageTimeFormatter {
    private static var locale: Locale {
        if let raw = UserDefaults.standard.string(forKey: "pax.settings.language"),
           raw != AppSettingsStore.LanguageMode.system.rawValue {
            return Locale(identifier: raw)
        }
        return Locale.autoupdatingCurrent
    }

    private static let timeFormatter: DateFormatter = {
        let f = DateFormatter()
        f.locale = locale
        f.dateStyle = .none
        f.timeStyle = .short
        return f
    }()

    private static let dayFormatter: DateFormatter = {
        let f = DateFormatter()
        f.locale = locale
        f.dateStyle = .medium
        f.timeStyle = .none
        return f
    }()

    static func timeString(from ts: Int?) -> String? {
        guard let ts else { return nil }
        timeFormatter.locale = locale
        return timeFormatter.string(from: Date(timeIntervalSince1970: TimeInterval(ts)))
    }

    static func dayHeader(from ts: Int?) -> String? {
        guard let ts else { return nil }
        let date = Date(timeIntervalSince1970: TimeInterval(ts))
        if Calendar.current.isDateInToday(date) { return L10n.TimeToday }
        if Calendar.current.isDateInYesterday(date) { return L10n.TimeYesterday }
        dayFormatter.locale = locale
        return dayFormatter.string(from: date)
    }

    static func shouldShowDayHeader(current: LiveMessage, previous: LiveMessage?) -> Bool {
        guard let currentTs = current.ts else { return previous == nil }
        guard let previousTs = previous?.ts else { return true }
        let currentDate = Date(timeIntervalSince1970: TimeInterval(currentTs))
        let previousDate = Date(timeIntervalSince1970: TimeInterval(previousTs))
        return !Calendar.current.isDate(currentDate, inSameDayAs: previousDate)
    }

    static func shouldShowTimestamp(current: LiveMessage, next: LiveMessage?) -> Bool {
        guard let currentTs = current.ts else { return next == nil }
        guard let nextTs = next?.ts else { return true }
        return nextTs - currentTs > 300
    }

    static func relativeUpdatedLabel(from raw: String) -> String? {
        guard let date = date(fromUpdatedAt: raw) else { return raw.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? nil : raw }
        return relativeUpdatedLabel(from: date)
    }

    static func relativeUpdatedLabel(from date: Date) -> String {
        let relative = RelativeDateTimeFormatter()
        relative.locale = locale
        relative.unitsStyle = .short
        return relative.localizedString(for: date, relativeTo: Date())
    }

    static func date(fromUpdatedAt raw: String) -> Date? {
        let trimmed = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return nil }

        let iso = ISO8601DateFormatter()
        iso.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        if let date = iso.date(from: trimmed) { return date }
        iso.formatOptions = [.withInternetDateTime]
        if let date = iso.date(from: trimmed) { return date }

        let fallback = DateFormatter()
        fallback.locale = locale
        fallback.dateFormat = "yyyy-MM-dd HH:mm:ss"
        if let date = fallback.date(from: trimmed) { return date }

        if let interval = TimeInterval(trimmed) {
            return Date(timeIntervalSince1970: interval)
        }
        return nil
    }
}
