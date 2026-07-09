import SwiftUI

struct ReportsAnalyticsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @StateObject private var tasks = TaskStore.shared
    @StateObject private var platform = PlatformSyncService.shared

    private var customerSessions: [LiveSession] {
        coordinator.sessions.filter { !$0.isTeamDM }
    }

    private var statusSlices: [ReportSlice] {
        if let mix = platform.reports?.sessionMix, !mix.isEmpty {
            return mix.compactMap { slice in
                guard slice.value > 0 else { return nil }
                return ReportSlice(label: localizedMixLabel(slice.label), value: slice.value, color: colorForMix(slice.label))
            }
        }
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
                LabeledContent(L10n.ReportsTotalSessions, value: "\(platform.reports?.overview.sessionsTotal ?? customerSessions.count)")
                LabeledContent(L10n.ReportsLiveQueue, value: "\(platform.reports?.overview.liveCount ?? coordinator.liveCount)")
                LabeledContent(L10n.ReportsOpenTasks, value: "\(platform.reports?.overview.openTasks ?? tasks.openCount)")
                LabeledContent(L10n.ReportsOverdueTasks, value: "\(platform.reports?.overview.overdueTasks ?? tasks.overdueCount)")
            }

            if !statusSlices.isEmpty {
                Section(L10n.ReportsSessionMix) {
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
        .refreshable {
            await platform.refreshReports(auth: auth)
            await coordinator.refreshSessions(auth: auth)
        }
    }

    private func localizedMixLabel(_ label: String) -> String {
        switch label {
        case "live": return L10n.FilterLive
        case "active": return L10n.FilterActive
        case "closed": return L10n.FilterClosed
        default: return label.capitalized
        }
    }

    private func colorForMix(_ label: String) -> Color {
        switch label {
        case "live": return .orange
        case "active": return .blue
        case "closed": return .gray
        default: return PAXTheme.accent
        }
    }
}

private struct ReportSlice: Identifiable {
    let id = UUID()
    let label: String
    let value: Int
    let color: Color
}
