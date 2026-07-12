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
    let entry: PAXDashboardWidgetEntry

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Image(systemName: "chart.bar.doc.horizontal.fill")
                    .font(.system(size: 13, weight: .medium))
                    .foregroundStyle(.primary)
                Text("PAXDesign")
                    .font(.caption.weight(.bold))
                Spacer()
            }
            HStack {
                metric("Chats", value: entry.snapshot.unreadChats)
                metric("Live", value: entry.snapshot.liveRequests)
            }
            HStack {
                metric("Tasks", value: entry.snapshot.openTasks)
                metric("Events", value: entry.snapshot.upcomingEvents)
            }
        }
        .padding(12)
    }

    private func metric(_ title: String, value: Int) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text("\(value)")
                .font(.headline.weight(.semibold))
                .foregroundStyle(.primary)
            Text(title)
                .font(.caption2)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
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
        .supportedFamilies([.systemSmall, .systemMedium])
    }
}
