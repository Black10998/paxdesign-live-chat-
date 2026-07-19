import WidgetKit
import SwiftUI

struct PAXDashboardWidgetEntry: TimelineEntry {
    let date: Date
    let snapshot: WidgetSnapshotReader.Snapshot
}

struct PAXDashboardWidgetProvider: TimelineProvider {
    func placeholder(in context: Context) -> PAXDashboardWidgetEntry {
        PAXDashboardWidgetEntry(
            date: Date(),
            snapshot: .preview
        )
    }

    func getSnapshot(in context: Context, completion: @escaping (PAXDashboardWidgetEntry) -> Void) {
        completion(PAXDashboardWidgetEntry(date: Date(), snapshot: WidgetSnapshotReader.load()))
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<PAXDashboardWidgetEntry>) -> Void) {
        let entry = PAXDashboardWidgetEntry(date: Date(), snapshot: WidgetSnapshotReader.load())
        completion(Timeline(entries: [entry], policy: .after(Date().addingTimeInterval(5 * 60))))
    }
}

private struct WidgetPalette {
    let backgroundTop: Color
    let backgroundBottom: Color
    let primaryText: Color
    let secondaryText: Color
    let tileFill: Color
    let tileStroke: Color

    init(colorScheme: ColorScheme) {
        switch colorScheme {
        case .dark:
            backgroundTop = Color(red: 0.11, green: 0.13, blue: 0.17)
            backgroundBottom = Color(red: 0.06, green: 0.07, blue: 0.10)
            primaryText = .white
            secondaryText = Color.white.opacity(0.58)
            tileFill = Color.white.opacity(0.10)
            tileStroke = Color.white.opacity(0.14)
        default:
            backgroundTop = Color(red: 0.98, green: 0.98, blue: 0.99)
            backgroundBottom = Color(red: 0.93, green: 0.94, blue: 0.96)
            primaryText = Color(red: 0.09, green: 0.11, blue: 0.15)
            secondaryText = primaryText.opacity(0.52)
            tileFill = Color.white.opacity(0.92)
            tileStroke = Color.black.opacity(0.07)
        }
    }
}

struct PAXDashboardWidgetView: View {
    @Environment(\.widgetFamily) private var family
    @Environment(\.colorScheme) private var colorScheme
    let entry: PAXDashboardWidgetEntry

    private var palette: WidgetPalette { WidgetPalette(colorScheme: colorScheme) }

    var body: some View {
        Group {
            switch family {
            case .systemLarge:
                largeLayout
            case .systemMedium:
                mediumLayout
            default:
                smallLayout
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .containerBackground(for: .widget) {
            LinearGradient(
                colors: [palette.backgroundTop, palette.backgroundBottom],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        }
    }

    private var header: some View {
        HStack(spacing: 8) {
            brandMark
            Text("Dashboard")
                .font(.caption.weight(.semibold))
                .foregroundStyle(palette.primaryText)
            Spacer(minLength: 4)
            Text(entry.snapshot.updatedAt, style: .time)
                .font(.caption2.weight(.medium))
                .foregroundStyle(palette.secondaryText)
                .monospacedDigit()
        }
    }

    private var brandMark: some View {
        RoundedRectangle(cornerRadius: 6, style: .continuous)
            .fill(Color(red: 0.98, green: 0.78, blue: 0.08))
            .frame(width: 20, height: 20)
            .overlay {
                Text("P")
                    .font(.system(size: 11, weight: .black, design: .rounded))
                    .foregroundStyle(Color.black.opacity(0.82))
            }
            .accessibilityHidden(true)
    }

    private var smallLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            header
            HStack(spacing: 8) {
                metricTile(.chats, value: entry.snapshot.unreadChats)
                metricTile(.live, value: entry.snapshot.liveRequests)
            }
        }
        .padding(12)
    }

    private var mediumLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            header
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 8), GridItem(.flexible(), spacing: 8)], spacing: 8) {
                metricTile(.chats, value: entry.snapshot.unreadChats)
                metricTile(.live, value: entry.snapshot.liveRequests)
                metricTile(.tasks, value: entry.snapshot.openTasks)
                metricTile(.events, value: entry.snapshot.upcomingEvents)
            }
        }
        .padding(12)
    }

    private var largeLayout: some View {
        VStack(alignment: .leading, spacing: 12) {
            header
            Text("Business pulse")
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(palette.primaryText)
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 10), GridItem(.flexible(), spacing: 10)], spacing: 10) {
                metricTile(.chats, value: entry.snapshot.unreadChats, expanded: true)
                metricTile(.live, value: entry.snapshot.liveRequests, expanded: true)
                metricTile(.tasks, value: entry.snapshot.openTasks, expanded: true)
                metricTile(.events, value: entry.snapshot.upcomingEvents, expanded: true)
            }
            Spacer(minLength: 0)
            Text("Open the app for full dashboard details.")
                .font(.caption2)
                .foregroundStyle(palette.secondaryText)
        }
        .padding(14)
    }

    private enum MetricKind: String {
        case chats = "Chats"
        case live = "Live"
        case tasks = "Tasks"
        case events = "Events"

        var accent: Color {
            switch self {
            case .chats: return .cyan
            case .live: return .orange
            case .tasks: return .mint
            case .events: return .purple
            }
        }
    }

    private func metricTile(_ kind: MetricKind, value: Int, expanded: Bool = false) -> some View {
        VStack(alignment: .leading, spacing: expanded ? 6 : 4) {
            Text("\(value)")
                .font((expanded ? Font.title2 : Font.title3).weight(.bold))
                .foregroundStyle(palette.primaryText)
                .minimumScaleFactor(0.65)
                .lineLimit(1)
                .monospacedDigit()
            Text(kind.rawValue)
                .font(.caption2.weight(.medium))
                .foregroundStyle(palette.secondaryText)
                .lineLimit(1)
        }
        .frame(maxWidth: .infinity, minHeight: expanded ? 72 : 56, alignment: .leading)
        .padding(expanded ? 12 : 10)
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
        .description("Unread chats, live requests, tasks, and upcoming events.")
        .supportedFamilies([.systemSmall, .systemMedium, .systemLarge])
    }
}
