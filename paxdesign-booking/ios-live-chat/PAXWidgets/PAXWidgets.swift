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
                    .foregroundStyle(.accentColor)
                Text("PAXDesign")
                    .font(.caption.weight(.bold))
                Spacer()
            }
            HStack {
                metric("Chats", value: entry.snapshot.unreadChats, tint: .accentColor)
                metric("Live", value: entry.snapshot.liveRequests, tint: .accentColor)
            }
            HStack {
                metric("Tasks", value: entry.snapshot.openTasks, tint: .accentColor)
                metric("Events", value: entry.snapshot.upcomingEvents, tint: .accentColor)
            }
        }
        .padding(12)
    }

    private func metric(_ title: String, value: Int, tint: Color) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text("\(value)")
                .font(.title3.weight(.bold))
                .foregroundStyle(tint)
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
