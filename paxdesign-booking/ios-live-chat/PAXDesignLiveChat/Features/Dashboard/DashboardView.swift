import SwiftUI
import Charts

struct DashboardView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @StateObject private var settings = AppSettingsStore.shared
    @StateObject private var tasks = TaskStore.shared
    @StateObject private var calendar = CalendarStore.shared
    @State private var showSearch = false

    private var customerSessions: [LiveSession] {
        coordinator.sessions.filter { !$0.isTeamDM }
    }

    private var unreadCount: Int {
        customerSessions.filter { $0.needsReply && !settings.readSessionIds.contains($0.sessionId) }.count
    }

    private var chartData: [DashboardMetric] {
        let calendar = Calendar.current
        return (0..<7).reversed().compactMap { offset -> DashboardMetric? in
            guard let day = calendar.date(byAdding: .day, value: -offset, to: Date()) else { return nil }
            let count = customerSessions.filter { calendar.isDate(Date(timeIntervalSince1970: $0.updatedAt), inSameDayAs: day) }.count
            let label = MessageTimeFormatter.relativeUpdatedLabel(from: day.timeIntervalSince1970) ?? ""
            return DashboardMetric(label: label, value: count)
        }
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                heroHeader
                metricsGrid
                activityChart
                quickModules
                upcomingSection
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
            WidgetDataStore.shared.syncFromApp()
        }
        .onAppear {
            WidgetDataStore.shared.syncFromApp()
            ActivityLogService.shared.log(
                category: L10n.ModuleDashboard,
                title: L10n.ActivityDashboardOpened,
                module: PlatformModule.dashboard.rawValue
            )
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
            DashboardMetricCard(title: L10n.DashboardMetricSessions, value: "\(customerSessions.count)", tint: .blue, icon: "bubble.left.and.bubble.right.fill")
            DashboardMetricCard(title: L10n.DashboardMetricUnread, value: "\(unreadCount)", tint: .orange, icon: "envelope.badge.fill")
            DashboardMetricCard(title: L10n.DashboardMetricLive, value: "\(coordinator.liveCount)", tint: .red, icon: "bell.and.waves.left.and.right.fill")
            DashboardMetricCard(title: L10n.DashboardMetricTasks, value: "\(tasks.openCount)", tint: .green, icon: "checklist")
        }
    }

    private var activityChart: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(L10n.DashboardChartTitle)
                .font(.headline)

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
                            Text(MessageTimeFormatter.relativeUpdatedLabel(from: event.startDate.timeIntervalSince1970) ?? "")
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
