import Foundation

enum MessageTimeFormatter {
    private static let formatterLock = NSLock()

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

    private static let relativeFormatter: RelativeDateTimeFormatter = {
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .short
        return formatter
    }()

    private static let isoWithFractionalSeconds: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter
    }()

    private static let isoInternetDateTime: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        return formatter
    }()

    private static let fallbackUpdatedAtFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone(secondsFromGMT: 0)
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter
    }()

    private static let updatedAtCache: NSCache<NSString, NSDate> = {
        let cache = NSCache<NSString, NSDate>()
        cache.countLimit = 512
        return cache
    }()

    static func timeString(from ts: Int?) -> String? {
        guard let ts else { return nil }
        formatterLock.lock()
        defer { formatterLock.unlock() }
        timeFormatter.locale = locale
        return timeFormatter.string(from: Date(timeIntervalSince1970: TimeInterval(ts)))
    }

    static func dayHeader(from ts: Int?) -> String? {
        guard let ts else { return nil }
        let date = Date(timeIntervalSince1970: TimeInterval(ts))
        if Calendar.current.isDateInToday(date) { return L10n.TimeToday }
        if Calendar.current.isDateInYesterday(date) { return L10n.TimeYesterday }
        formatterLock.lock()
        defer { formatterLock.unlock() }
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
        formatterLock.lock()
        defer { formatterLock.unlock() }
        relativeFormatter.locale = locale
        return relativeFormatter.localizedString(for: date, relativeTo: Date())
    }

    static func date(fromUpdatedAt raw: String) -> Date? {
        let trimmed = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return nil }

        if let cached = updatedAtCache.object(forKey: trimmed as NSString) {
            return cached as Date
        }

        formatterLock.lock()
        defer { formatterLock.unlock() }

        if let date = isoWithFractionalSeconds.date(from: trimmed) {
            updatedAtCache.setObject(date as NSDate, forKey: trimmed as NSString)
            return date
        }
        if let date = isoInternetDateTime.date(from: trimmed) {
            updatedAtCache.setObject(date as NSDate, forKey: trimmed as NSString)
            return date
        }

        if let date = fallbackUpdatedAtFormatter.date(from: trimmed) {
            updatedAtCache.setObject(date as NSDate, forKey: trimmed as NSString)
            return date
        }

        if let interval = TimeInterval(trimmed) {
            let date = Date(timeIntervalSince1970: interval)
            updatedAtCache.setObject(date as NSDate, forKey: trimmed as NSString)
            return date
        }
        return nil
    }
}
