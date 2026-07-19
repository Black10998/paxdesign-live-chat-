import WidgetKit
import SwiftUI

struct PAXDashboardWidgetEntry: TimelineEntry {
    let date: Date
    let snapshot: WidgetSnapshotReader.Snapshot
}

struct PAXDashboardWidgetProvider: TimelineProvider {
    func placeholder(in context: Context) -> PAXDashboardWidgetEntry {
        PAXDashboardWidgetEntry(date: Date(), snapshot: .preview)
    }

    func getSnapshot(in context: Context, completion: @escaping (PAXDashboardWidgetEntry) -> Void) {
        completion(PAXDashboardWidgetEntry(date: Date(), snapshot: WidgetSnapshotReader.load()))
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<PAXDashboardWidgetEntry>) -> Void) {
        let snapshot = WidgetSnapshotReader.load()
        let entry = PAXDashboardWidgetEntry(date: Date(), snapshot: snapshot)
        let refreshMinutes = snapshot.liveRequests > 0 ? 2.0 : 10.0
        completion(Timeline(entries: [entry], policy: .after(Date().addingTimeInterval(refreshMinutes * 60))))
    }
}

private enum WidgetBrand {
    static let accent = Color(red: 194 / 255, green: 1, blue: 0)
    static let accentBlue = Color(red: 0.0, green: 0.48, blue: 1.0)

    static func accent(for colorScheme: ColorScheme) -> Color {
        colorScheme == .dark ? accent : accentBlue
    }
}

private struct WidgetPalette {
    let backgroundTop: Color
    let backgroundBottom: Color
    let primaryText: Color
    let secondaryText: Color
    let tileFill: Color
    let tileStroke: Color
    let accent: Color

    init(colorScheme: ColorScheme) {
        accent = WidgetBrand.accent(for: colorScheme)
        switch colorScheme {
        case .dark:
            backgroundTop = Color(red: 0.10, green: 0.12, blue: 0.16)
            backgroundBottom = Color(red: 0.04, green: 0.05, blue: 0.08)
            primaryText = .white
            secondaryText = Color.white.opacity(0.58)
            tileFill = Color.white.opacity(0.10)
            tileStroke = Color.white.opacity(0.14)
        default:
            backgroundTop = Color(red: 0.98, green: 0.99, blue: 1.0)
            backgroundBottom = Color(red: 0.92, green: 0.94, blue: 0.97)
            primaryText = Color(red: 0.08, green: 0.10, blue: 0.14)
            secondaryText = primaryText.opacity(0.52)
            tileFill = Color.white.opacity(0.94)
            tileStroke = Color.black.opacity(0.06)
        }
    }
}

struct PAXDashboardWidgetView: View {
    @Environment(\.widgetFamily) private var family
    @Environment(\.colorScheme) private var colorScheme
    let entry: PAXDashboardWidgetEntry

    private var palette: WidgetPalette { WidgetPalette(colorScheme: colorScheme) }
    private var snapshot: WidgetSnapshotReader.Snapshot { entry.snapshot }

    var body: some View {
        Group {
            if !snapshot.isSignedIn {
                signedOutLayout
            } else {
                switch family {
                case .systemLarge:
                    largeLayout
                case .systemMedium:
                    mediumLayout
                default:
                    smallLayout
                }
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .widgetURL(URL(string: "paxlivechat://dashboard"))
        .modifier(WidgetSurfaceBackground(palette: palette))
    }
}

private struct WidgetSurfaceBackground: ViewModifier {
    let palette: WidgetPalette

    func body(content: Content) -> some View {
        if #available(iOSApplicationExtension 17.0, *) {
            content.containerBackground(for: .widget) {
                WidgetSurfaceBackground.gradient(for: palette)
            }
        } else {
            content.background(WidgetSurfaceBackground.gradient(for: palette))
        }
    }

    static func gradient(for palette: WidgetPalette) -> LinearGradient {
        LinearGradient(
            colors: [palette.backgroundTop, palette.backgroundBottom],
            startPoint: .topLeading,
            endPoint: .bottomTrailing
        )
    }
}

private extension PAXDashboardWidgetView {
    private var header: some View {
        HStack(spacing: 8) {
            brandMark
            VStack(alignment: .leading, spacing: 1) {
                Text("Business pulse")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(palette.primaryText)
                Text(snapshot.updatedAt, style: .time)
                    .font(.caption2)
                    .foregroundStyle(palette.secondaryText)
                    .monospacedDigit()
            }
            Spacer(minLength: 0)
            if snapshot.liveRequests > 0 {
                liveBadge
            }
        }
    }

    private var brandMark: some View {
        RoundedRectangle(cornerRadius: 7, style: .continuous)
            .fill(palette.accent)
            .frame(width: 22, height: 22)
            .overlay {
                Text("P")
                    .font(.system(size: 12, weight: .black, design: .rounded))
                    .foregroundStyle(colorScheme == .dark ? Color.black.opacity(0.82) : .white)
            }
            .accessibilityHidden(true)
    }

    private var liveBadge: some View {
        Text("LIVE")
            .font(.system(size: 9, weight: .heavy, design: .rounded))
            .foregroundStyle(colorScheme == .dark ? .black : .white)
            .padding(.horizontal, 6)
            .padding(.vertical, 3)
            .background(Capsule().fill(Color.orange))
    }

    private var signedOutLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            brandMark
            Text("Sign in to PAXDesign")
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(palette.primaryText)
            Text("Open the app to refresh your business dashboard.")
                .font(.caption)
                .foregroundStyle(palette.secondaryText)
        }
        .padding(14)
    }

    private var smallLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            header
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 6), GridItem(.flexible(), spacing: 6)], spacing: 6) {
                linkedMetricTile(.chats, value: snapshot.unreadChats, compact: true)
                linkedMetricTile(.live, value: snapshot.liveRequests, compact: true)
                linkedMetricTile(.tasks, value: snapshot.openTasks, compact: true)
                linkedMetricTile(.events, value: snapshot.upcomingEvents, compact: true)
            }
        }
        .padding(12)
    }

    private var mediumLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            header
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 8), GridItem(.flexible(), spacing: 8)], spacing: 8) {
                linkedMetricTile(.chats, value: snapshot.unreadChats)
                linkedMetricTile(.live, value: snapshot.liveRequests)
                linkedMetricTile(.tasks, value: snapshot.openTasks)
                linkedMetricTile(.events, value: snapshot.upcomingEvents)
            }
        }
        .padding(12)
    }

    private var largeLayout: some View {
        VStack(alignment: .leading, spacing: 12) {
            header
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 10), GridItem(.flexible(), spacing: 10)], spacing: 10) {
                linkedMetricTile(.chats, value: snapshot.unreadChats, expanded: true)
                linkedMetricTile(.live, value: snapshot.liveRequests, expanded: true)
                linkedMetricTile(.tasks, value: snapshot.openTasks, expanded: true)
                linkedMetricTile(.events, value: snapshot.upcomingEvents, expanded: true)
            }
            if !snapshot.liveHighlight.isEmpty || !snapshot.nextEventTitle.isEmpty {
                VStack(alignment: .leading, spacing: 6) {
                    if !snapshot.liveHighlight.isEmpty {
                        insightRow(title: "Top live request", value: snapshot.liveHighlight, tint: .orange)
                    }
                    if !snapshot.nextEventTitle.isEmpty {
                        insightRow(title: "Next event", value: snapshot.nextEventTitle, tint: .purple)
                    }
                }
            }
            Spacer(minLength: 0)
        }
        .padding(14)
    }

    private enum MetricKind: String {
        case chats = "Chats"
        case live = "Live"
        case tasks = "Tasks"
        case events = "Events"

        var deepLink: URL? {
            switch self {
            case .chats: return URL(string: "paxlivechat://chats")
            case .live: return URL(string: "paxlivechat://live")
            case .tasks: return URL(string: "paxlivechat://dashboard")
            case .events: return URL(string: "paxlivechat://dashboard")
            }
        }

        var accent: Color {
            switch self {
            case .chats: return .cyan
            case .live: return .orange
            case .tasks: return .mint
            case .events: return .purple
            }
        }
    }

    private func linkedMetricTile(_ kind: MetricKind, value: Int, compact: Bool = false, expanded: Bool = false) -> some View {
        Link(destination: kind.deepLink ?? URL(string: "paxlivechat://dashboard")!) {
            metricTile(kind, value: value, compact: compact, expanded: expanded)
        }
    }

    private func metricTile(_ kind: MetricKind, value: Int, compact: Bool, expanded: Bool = false) -> some View {
        VStack(alignment: .leading, spacing: compact ? 2 : (expanded ? 6 : 4)) {
            Text("\(value)")
                .font((expanded ? Font.title2 : (compact ? Font.headline : Font.title3)).weight(.bold))
                .foregroundStyle(value > 0 && kind == .live ? Color.orange : palette.primaryText)
                .minimumScaleFactor(0.65)
                .lineLimit(1)
                .monospacedDigit()
            Text(kind.rawValue)
                .font(compact ? .system(size: 10, weight: .medium) : .caption2.weight(.medium))
                .foregroundStyle(palette.secondaryText)
                .lineLimit(1)
        }
        .frame(maxWidth: .infinity, minHeight: expanded ? 74 : (compact ? 46 : 56), alignment: .leading)
        .padding(compact ? 8 : (expanded ? 12 : 10))
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(palette.tileFill)
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(kind.accent.opacity(colorScheme == .dark ? 0.42 : 0.28), lineWidth: 0.8)
                )
        )
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(kind.rawValue), \(value)")
    }

    private func insightRow(title: String, value: String, tint: Color) -> some View {
        HStack(spacing: 8) {
            Circle()
                .fill(tint.opacity(0.85))
                .frame(width: 6, height: 6)
            Text(title)
                .font(.caption2.weight(.semibold))
                .foregroundStyle(palette.secondaryText)
            Text(value)
                .font(.caption.weight(.medium))
                .foregroundStyle(palette.primaryText)
                .lineLimit(1)
        }
    }
}

@main
struct PAXWidgetsBundle: WidgetBundle {
    var body: some Widget {
        PAXDashboardWidget()
    }
}

struct PAXDashboardWidget: Widget {
    static let kind = WidgetSnapshotReader.widgetKind

    var body: some WidgetConfiguration {
        StaticConfiguration(kind: Self.kind, provider: PAXDashboardWidgetProvider()) { entry in
            PAXDashboardWidgetView(entry: entry)
        }
        .configurationDisplayName("Business Dashboard")
        .description("Unread chats, live requests, open tasks, and upcoming events with live refresh.")
        .supportedFamilies([.systemSmall, .systemMedium, .systemLarge])
    }
}
