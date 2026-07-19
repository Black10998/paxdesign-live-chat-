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
            snapshot: .init(unreadChats: 2, liveRequests: 1, openTasks: 3, upcomingEvents: 1, updatedAt: Date())
        )
    }

    func getSnapshot(in context: Context, completion: @escaping (PAXDashboardWidgetEntry) -> Void) {
        completion(PAXDashboardWidgetEntry(date: Date(), snapshot: WidgetSnapshotReader.load()))
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<PAXDashboardWidgetEntry>) -> Void) {
        let entry = PAXDashboardWidgetEntry(date: Date(), snapshot: WidgetSnapshotReader.load())
        completion(Timeline(entries: [entry], policy: .after(Date().addingTimeInterval(15 * 60))))
    }
}

struct PAXDashboardWidgetView: View {
    @Environment(\.widgetFamily) private var family
    let entry: PAXDashboardWidgetEntry

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
        .containerBackground(for: .widget) {
            LinearGradient(
                colors: [Color(red: 0.08, green: 0.10, blue: 0.14), Color(red: 0.04, green: 0.05, blue: 0.08)],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        }
    }

    private var header: some View {
        HStack(spacing: 8) {
            RoundedRectangle(cornerRadius: 6, style: .continuous)
                .fill(Color(red: 0.98, green: 0.78, blue: 0.08))
                .frame(width: 22, height: 22)
                .overlay {
                    Text("P")
                        .font(.system(size: 12, weight: .black, design: .rounded))
                        .foregroundStyle(Color.black.opacity(0.82))
                }
            VStack(alignment: .leading, spacing: 1) {
                Text("PAXDesign")
                    .font(.caption.weight(.bold))
                    .foregroundStyle(.white)
                Text(entry.snapshot.updatedAt, style: .time)
                    .font(.caption2)
                    .foregroundStyle(.white.opacity(0.55))
            }
            Spacer()
        }
    }

    private var smallLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            header
            HStack(spacing: 8) {
                metricTile("Chats", value: entry.snapshot.unreadChats, accent: .cyan)
                metricTile("Live", value: entry.snapshot.liveRequests, accent: .orange)
            }
        }
        .padding(12)
    }

    private var mediumLayout: some View {
        VStack(alignment: .leading, spacing: 10) {
            header
            HStack(spacing: 8) {
                metricTile("Chats", value: entry.snapshot.unreadChats, accent: .cyan)
                metricTile("Live", value: entry.snapshot.liveRequests, accent: .orange)
                metricTile("Tasks", value: entry.snapshot.openTasks, accent: .mint)
                metricTile("Events", value: entry.snapshot.upcomingEvents, accent: .purple)
            }
        }
        .padding(12)
    }

    private var largeLayout: some View {
        VStack(alignment: .leading, spacing: 12) {
            header
            Text("Business pulse")
                .font(.headline.weight(.semibold))
                .foregroundStyle(.white)
            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 10) {
                metricTile("Unread chats", value: entry.snapshot.unreadChats, accent: .cyan)
                metricTile("Live requests", value: entry.snapshot.liveRequests, accent: .orange)
                metricTile("Open tasks", value: entry.snapshot.openTasks, accent: .mint)
                metricTile("Upcoming events", value: entry.snapshot.upcomingEvents, accent: .purple)
            }
            Spacer(minLength: 0)
            Text("Tap to open PAXDesign Live Chat")
                .font(.caption2.weight(.medium))
                .foregroundStyle(.white.opacity(0.6))
        }
        .padding(14)
    }

    private func metricTile(_ title: String, value: Int, accent: Color) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("\(value)")
                .font(.title3.weight(.bold))
                .foregroundStyle(.white)
                .minimumScaleFactor(0.7)
                .lineLimit(1)
            Text(title)
                .font(.caption2.weight(.medium))
                .foregroundStyle(.white.opacity(0.68))
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(10)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(.white.opacity(0.08))
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(accent.opacity(0.45), lineWidth: 0.8)
                )
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
    let kind = "PAXDashboardWidget"

    var body: some WidgetConfiguration {
        StaticConfiguration(kind: kind, provider: PAXDashboardWidgetProvider()) { entry in
            PAXDashboardWidgetView(entry: entry)
        }
        .configurationDisplayName("Business Dashboard")
        .description("Unread chats, live requests, tasks, and upcoming events.")
        .supportedFamilies([.systemSmall, .systemMedium, .systemLarge])
    }
}
