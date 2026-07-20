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
            backgroundTop = Color(red: 0.11, green: 0.13, blue: 0.18)
            backgroundBottom = Color(red: 0.05, green: 0.06, blue: 0.10)
            primaryText = .white
            secondaryText = Color.white.opacity(0.62)
            tileFill = Color.white.opacity(0.11)
            tileStroke = Color.white.opacity(0.16)
        default:
            backgroundTop = Color(red: 0.99, green: 1.0, blue: 1.0)
            backgroundBottom = Color(red: 0.93, green: 0.95, blue: 0.98)
            primaryText = Color(red: 0.07, green: 0.09, blue: 0.13)
            secondaryText = primaryText.opacity(0.55)
            tileFill = Color.white
            tileStroke = Color.black.opacity(0.07)
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
    private var signedOutLayout: some View {
        VStack(alignment: .leading, spacing: 8) {
            brandMark(size: 20)
            Text("Sign in to PAXDesign")
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(palette.primaryText)
                .lineLimit(1)
            Text("Open the app to sync your business dashboard.")
                .font(.caption)
                .foregroundStyle(palette.secondaryText)
                .lineLimit(2)
                .fixedSize(horizontal: false, vertical: true)
            Spacer(minLength: 0)
        }
        .padding(14)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
    }

    private var smallLayout: some View {
        VStack(alignment: .leading, spacing: 6) {
            widgetHeader(compact: true)
            VStack(spacing: 5) {
                HStack(spacing: 5) {
                    metricTile(.chats, value: snapshot.unreadChats, style: .small)
                    metricTile(.live, value: snapshot.liveRequests, style: .small)
                }
                HStack(spacing: 5) {
                    metricTile(.tasks, value: snapshot.openTasks, style: .small)
                    metricTile(.events, value: snapshot.upcomingEvents, style: .small)
                }
            }
        }
        .padding(EdgeInsets(top: 9, leading: 9, bottom: 9, trailing: 9))
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
    }

    private var mediumLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            widgetHeader(compact: false)
            HStack(spacing: 8) {
                metricTile(.chats, value: snapshot.unreadChats, style: .medium)
                metricTile(.live, value: snapshot.liveRequests, style: .medium)
                metricTile(.tasks, value: snapshot.openTasks, style: .medium)
                metricTile(.events, value: snapshot.upcomingEvents, style: .medium)
            }
            if !snapshot.liveHighlight.isEmpty {
                insightRow(title: "Live", value: snapshot.liveHighlight, tint: .orange)
            }
        }
        .padding(12)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
    }

    private var largeLayout: some View {
        VStack(alignment: .leading, spacing: 12) {
            widgetHeader(compact: false)
            LazyVGrid(
                columns: [GridItem(.flexible(), spacing: 10), GridItem(.flexible(), spacing: 10)],
                spacing: 10
            ) {
                metricTile(.chats, value: snapshot.unreadChats, style: .large)
                metricTile(.live, value: snapshot.liveRequests, style: .large)
                metricTile(.tasks, value: snapshot.openTasks, style: .large)
                metricTile(.events, value: snapshot.upcomingEvents, style: .large)
            }
            if !snapshot.liveHighlight.isEmpty {
                insightRow(title: "Top live request", value: snapshot.liveHighlight, tint: .orange)
            } else if !snapshot.nextEventTitle.isEmpty {
                insightRow(title: "Next event", value: snapshot.nextEventTitle, tint: .purple)
            }
            Spacer(minLength: 0)
        }
        .padding(14)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
    }

    private func widgetHeader(compact: Bool) -> some View {
        HStack(spacing: 8) {
            brandMark(size: compact ? 18 : 20)
            VStack(alignment: .leading, spacing: 1) {
                Text("Business pulse")
                    .font(compact ? .caption.weight(.semibold) : .subheadline.weight(.semibold))
                    .foregroundStyle(palette.primaryText)
                    .lineLimit(1)
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

    private func brandMark(size: CGFloat) -> some View {
        RoundedRectangle(cornerRadius: size * 0.32, style: .continuous)
            .fill(palette.accent)
            .frame(width: size, height: size)
            .overlay {
                Text("P")
                    .font(.system(size: size * 0.55, weight: .black, design: .rounded))
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

    private enum MetricKind: String {
        case chats = "Chats"
        case live = "Live"
        case tasks = "Tasks"
        case events = "Events"

        var deepLink: URL? {
            switch self {
            case .chats: return URL(string: "paxlivechat://chats")
            case .live: return URL(string: "paxlivechat://live")
            case .tasks, .events: return URL(string: "paxlivechat://dashboard")
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

    private enum MetricTileStyle {
        case small, medium, large

        var valueFont: Font {
            switch self {
            case .small: return .headline.weight(.bold)
            case .medium: return .title3.weight(.bold)
            case .large: return .title2.weight(.bold)
            }
        }

        var labelFont: Font {
            switch self {
            case .small: return .system(size: 8, weight: .semibold)
            case .medium: return .caption2.weight(.medium)
            case .large: return .caption.weight(.medium)
            }
        }

        var padding: CGFloat {
            switch self {
            case .small: return 6
            case .medium: return 8
            case .large: return 10
            }
        }
    }

    private func metricTile(_ kind: MetricKind, value: Int, style: MetricTileStyle) -> some View {
        Link(destination: kind.deepLink ?? URL(string: "paxlivechat://dashboard")!) {
            VStack(alignment: .leading, spacing: style == .small ? 1 : 3) {
                Text("\(value)")
                    .font(style.valueFont)
                    .foregroundStyle(value > 0 && kind == .live ? Color.orange : palette.primaryText)
                    .minimumScaleFactor(0.7)
                    .lineLimit(1)
                    .monospacedDigit()
                Text(kind.rawValue)
                    .font(style.labelFont)
                    .foregroundStyle(palette.secondaryText)
                    .lineLimit(1)
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .leading)
            .padding(style.padding)
            .background(
                RoundedRectangle(cornerRadius: 11, style: .continuous)
                    .fill(palette.tileFill)
                    .overlay(
                        RoundedRectangle(cornerRadius: 11, style: .continuous)
                            .stroke(kind.accent.opacity(colorScheme == .dark ? 0.38 : 0.22), lineWidth: 0.75)
                    )
            )
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(kind.rawValue), \(value)")
    }

    private func insightRow(title: String, value: String, tint: Color) -> some View {
        HStack(spacing: 6) {
            Circle()
                .fill(tint)
                .frame(width: 5, height: 5)
            Text(title)
                .font(.caption2.weight(.semibold))
                .foregroundStyle(palette.secondaryText)
                .lineLimit(1)
            Text(value)
                .font(.caption.weight(.medium))
                .foregroundStyle(palette.primaryText)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 7)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(palette.tileFill.opacity(0.85))
        )
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
