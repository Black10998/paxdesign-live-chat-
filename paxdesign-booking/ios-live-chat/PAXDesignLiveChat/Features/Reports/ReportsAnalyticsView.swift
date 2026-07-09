import SwiftUI
import Charts

struct ReportsAnalyticsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @StateObject private var tasks = TaskStore.shared

    private var customerSessions: [LiveSession] {
        coordinator.sessions.filter { !$0.isTeamDM }
    }

    private var statusSlices: [ReportSlice] {
        let live = customerSessions.filter(\.isLiveRequest).count
        let active = customerSessions.filter { $0.isAdmin && !$0.isClosed }.count
        let closed = customerSessions.filter(\.isClosed).count
        return [
            ReportSlice(label: L10n.FilterLive, value: live, color: .orange),
            ReportSlice(label: L10n.FilterActive, value: active, color: .blue),
            ReportSlice(label: L10n.FilterClosed, value: closed, color: .gray)
        ].filter { $0.value > 0 }
    }

    var body: some View {
        List {
            Section {
                PlatformHeroHeader(
                    title: L10n.ModuleReports,
                    subtitle: L10n.ModuleReportsSubtitle,
                    systemImage: "chart.xyaxis.line",
                    gradient: [.purple, .pink]
                )
                .listRowInsets(EdgeInsets())
                .listRowBackground(Color.clear)
            }

            Section(L10n.ReportsOverview) {
                LabeledContent(L10n.ReportsTotalSessions, value: "\(customerSessions.count)")
                LabeledContent(L10n.ReportsLiveQueue, value: "\(coordinator.liveCount)")
                LabeledContent(L10n.ReportsOpenTasks, value: "\(tasks.openCount)")
                LabeledContent(L10n.ReportsOverdueTasks, value: "\(tasks.overdueCount)")
            }

            if !statusSlices.isEmpty {
                Section(L10n.ReportsSessionMix) {
                    Chart(statusSlices) { slice in
                        SectorMark(
                            angle: .value("Count", slice.value),
                            innerRadius: .ratio(0.55),
                            angularInset: 2
                        )
                        .foregroundStyle(slice.color)
                        .annotation(position: .overlay) {
                            if slice.value > 0 {
                                Text("\(slice.value)")
                                    .font(.caption2.weight(.bold))
                                    .foregroundStyle(.white)
                            }
                        }
                    }
                    .frame(height: 220)
                    .listRowBackground(Color.clear)

                    ForEach(statusSlices) { slice in
                        HStack {
                            Circle().fill(slice.color).frame(width: 10, height: 10)
                            Text(slice.label)
                            Spacer()
                            Text("\(slice.value)")
                                .font(.headline)
                        }
                    }
                }
            }

            Section(L10n.ReportsInsights) {
                Text(L10n.ReportsInsightBody)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleReports)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                NavigationLink { ModuleReportsSettingsView() } label: {
                    Image(systemName: "slider.horizontal.3")
                }
            }
        }
    }
}

private struct ReportSlice: Identifiable {
    let id = UUID()
    let label: String
    let value: Int
    let color: Color
}
