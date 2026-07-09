import SwiftUI
#if !SIDELOAD
import Charts
#endif

struct DashboardView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @ObservedObject private var tasks = TaskStore.shared
    @ObservedObject private var calendar = CalendarStore.shared
    @ObservedObject private var platform = PlatformSyncService.shared
    @State private var showSearch = false

    private var customerSessions: [LiveSession] {
        coordinator.sessions.filter { !$0.isTeamDM }
    }

    private var unreadCount: Int {
        customerSessions.filter { $0.needsReply && !settings.readSessionIds.contains($0.sessionId) }.count
    }

    private var chartData: [DashboardMetric] {
        if let serverChart = platform.dashboard?.activityChart, !serverChart.isEmpty {
            return serverChart.map { DashboardMetric(label: formatChartLabel($0.label), value: $0.value) }
        }
        let calendar = Calendar.current
        return (0..<7).reversed().compactMap { offset -> DashboardMetric? in
            guard let day = calendar.date(byAdding: .day, value: -offset, to: Date()) else { return nil }
            let count = customerSessions.filter {
                guard let updated = MessageTimeFormatter.date(fromUpdatedAt: $0.updatedAt) else { return false }
                return calendar.isDate(updated, inSameDayAs: day)
            }.count
            let label = MessageTimeFormatter.relativeUpdatedLabel(from: day)
            return DashboardMetric(label: label, value: count)
        }
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                heroHeader
                metricsGrid
                if PlatformModuleSettingsStore.shared.dashboardShowChart {
                    activityChart
                }
                quickModules
                if PlatformModuleSettingsStore.shared.dashboardShowUpcoming {
                    upcomingSection
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
        }
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleDashboard)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Button {
                    showSearch = true
                    PAXHaptics.light()
                } label: {
                    Image(systemName: "magnifyingglass")
                }
                .accessibilityLabel(L10n.GlobalSearchTitle)
            }
        }
        .sheet(isPresented: $showSearch) {
            NavigationStack { GlobalSearchView() }
                .environmentObject(auth)
                .environmentObject(coordinator)
        }
        .refreshable {
            await coordinator.refreshSessions(auth: auth)
            await platform.sync(auth: auth)
            WidgetDataStore.shared.syncFromApp()
        }
        .onAppear {
            Task {
                await ActivityLogService.shared.log(
                    category: L10n.ModuleDashboard,
                    title: L10n.ActivityDashboardOpened,
                    module: PlatformModule.dashboard.rawValue,
                    auth: auth
                )
            }
        }
    }

    private var heroHeader: some View {
        PlatformHeroHeader(
            title: L10n.DashboardWelcome(auth.profile?.name ?? L10n.CommonAdministrator),
            subtitle: L10n.DashboardSubtitle,
            systemImage: "chart.bar.doc.horizontal.fill",
            gradient: [.blue, .cyan]
        )
        .transition(PAXMotion.heroReveal)
    }

    private var metricsGrid: some View {
        LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
            DashboardMetricCard(
                title: L10n.DashboardMetricSessions,
                value: "\(platform.dashboard?.sessionsTotal ?? customerSessions.count)",
                tint: .blue,
                icon: "bubble.left.and.bubble.right.fill"
            )
            DashboardMetricCard(
                title: L10n.DashboardMetricUnread,
                value: "\(unreadCount)",
                tint: .orange,
                icon: "envelope.badge.fill"
            )
            DashboardMetricCard(
                title: L10n.DashboardMetricLive,
                value: "\(platform.dashboard?.liveCount ?? coordinator.liveCount)",
                tint: .red,
                icon: "bell.and.waves.left.and.right.fill"
            )
            DashboardMetricCard(
                title: L10n.DashboardMetricTasks,
                value: "\(platform.dashboard?.openTasks ?? tasks.openCount)",
                tint: .green,
                icon: "checklist"
            )
        }
    }

    private var activityChart: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(L10n.DashboardChartTitle)
                .font(.headline)
            #if SIDELOAD
            // Keep sideload startup conservative by avoiding extra framework links.
            VStack(spacing: 8) {
                ForEach(chartData) { item in
                    VStack(alignment: .leading, spacing: 4) {
                        HStack {
                            Text(item.label)
                                .font(.caption)
                                .foregroundStyle(PAXTheme.textSecondary)
                            Spacer()
                            Text("\(item.value)")
                                .font(.caption.weight(.semibold))
                        }
                        GeometryReader { geo in
                            let maxValue = max(chartData.map(\.value).max() ?? 1, 1)
                            let ratio = CGFloat(item.value) / CGFloat(maxValue)
                            RoundedRectangle(cornerRadius: 6, style: .continuous)
                                .fill(PAXTheme.accent.gradient)
                                .frame(width: max(6, geo.size.width * ratio), height: 10)
                        }
                        .frame(height: 10)
                    }
                }
            }
            #else
            Chart(chartData) { item in
                BarMark(
                    x: .value("Day", item.label),
                    y: .value("Sessions", item.value)
                )
                .foregroundStyle(PAXTheme.accent.gradient)
                .cornerRadius(6)
            }
            .frame(height: 180)
            .chartYAxis {
                AxisMarks(position: .leading)
            }
            #endif
        }
        .paxNativeCard()
    }

    private var quickModules: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(L10n.DashboardQuickAccess)
                .font(.headline)

            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                ForEach(Array(PlatformModuleAccess.availableHubModules(auth: auth).filter { $0 != .dashboard }.prefix(6))) { module in
                    NavigationLink(value: module) {
                        PlatformModuleCard(
                            title: module.title,
                            subtitle: module.subtitle,
                            systemImage: module.systemImage,
                            tint: module.tint
                        )
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }

    private var upcomingSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(L10n.DashboardUpcoming)
                    .font(.headline)
                Spacer()
                NavigationLink(L10n.CommonOpen) { CalendarModuleView() }
                    .font(.caption.weight(.semibold))
            }

            if calendar.upcoming().isEmpty {
                Text(L10n.CalendarNoUpcoming)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .paxNativeCard()
            } else {
                ForEach(calendar.upcoming()) { event in
                    HStack(spacing: 12) {
                        Image(systemName: event.category.systemImage)
                            .foregroundStyle(.red)
                        VStack(alignment: .leading, spacing: 2) {
                            Text(event.title).font(.subheadline.weight(.semibold))
                            Text(MessageTimeFormatter.relativeUpdatedLabel(from: event.startDate))
                                .font(.caption)
                                .foregroundStyle(PAXTheme.textSecondary)
                        }
                        Spacer()
                    }
                    .paxNativeCard()
                }
            }
        }
    }

    private func formatChartLabel(_ raw: String) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        if let date = formatter.date(from: raw) {
            return MessageTimeFormatter.relativeUpdatedLabel(from: date)
        }
        return raw
    }
}

private struct DashboardMetric: Identifiable {
    let id = UUID()
    let label: String
    let value: Int
}

private struct DashboardMetricCard: View {
    let title: String
    let value: String
    let tint: Color
    let icon: String

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Image(systemName: icon)
                .foregroundStyle(tint)
            Text(value)
                .font(.title.weight(.bold))
            Text(title)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .paxNativeCard()
    }
}

private extension L10n {
    static func DashboardWelcome(_ name: String) -> String {
        String(format: String(localized: "dashboard.welcome"), name)
    }
}
