import Foundation

enum MessageTimeFormatter {
    private static let timeFormatter: DateFormatter = {
        let f = DateFormatter()
        f.locale = Locale(identifier: "de_AT")
        f.dateStyle = .none
        f.timeStyle = .short
        return f
    }()

    private static let dayFormatter: DateFormatter = {
        let f = DateFormatter()
        f.locale = Locale(identifier: "de_AT")
        f.dateStyle = .medium
        f.timeStyle = .none
        return f
    }()

    static func timeString(from ts: Int?) -> String? {
        guard let ts else { return nil }
        return timeFormatter.string(from: Date(timeIntervalSince1970: TimeInterval(ts)))
    }

    static func dayHeader(from ts: Int?) -> String? {
        guard let ts else { return nil }
        let date = Date(timeIntervalSince1970: TimeInterval(ts))
        if Calendar.current.isDateInToday(date) { return "Heute" }
        if Calendar.current.isDateInYesterday(date) { return "Gestern" }
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
}
