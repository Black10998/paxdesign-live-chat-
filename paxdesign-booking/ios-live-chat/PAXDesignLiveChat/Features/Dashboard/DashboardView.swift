import SwiftUI

struct DashboardView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @ObservedObject private var tasks = TaskStore.shared
    @ObservedObject private var calendar = CalendarStore.shared
    @ObservedObject private var platform = PlatformSyncService.shared
    @State private var showSearch = false
    @State private var showDashboardTour = false
    @State private var dashboardTourStepIndex = 0
    @State private var isInitialLoading = true
    @State private var cachedChartData: [DashboardMetric] = []
    @State private var cachedActivitySeries: [PlatformActivityDay] = []
    @State private var cachedTrends = PlatformDashboardTrends()
    @State private var cachedCategories: [PlatformReportSlice] = []
    @State private var cachedRecentActivity: [LiveSession] = []

    private var customerSessions: [LiveSession] {
        coordinator.sessions.filter { !$0.isTeamDM }
    }

    private var unreadCount: Int {
        customerSessions.filter { settings.isSessionUnread($0) }.count
    }

    private var chartData: [DashboardMetric] {
        cachedChartData
    }

    private var recentActivityItems: [LiveSession] {
        cachedRecentActivity
    }

    private func recomputeDashboardMetrics() {
        let sessions = customerSessions
        let chart: [DashboardMetric]
        var series: [PlatformActivityDay] = []
        var trends = PlatformDashboardTrends()
        var categories: [PlatformReportSlice] = []

        if let dashboard = platform.dashboard {
            if !dashboard.activitySeries.isEmpty {
                series = dashboard.activitySeries
                chart = series.map { DashboardMetric(label: formatChartLabel($0.label), value: $0.messages) }
            } else if !dashboard.activityChart.isEmpty {
                chart = dashboard.activityChart.map { DashboardMetric(label: formatChartLabel($0.label), value: $0.value) }
                series = dashboard.activityChart.map {
                    PlatformActivityDay(label: $0.label, sessions: $0.value, messages: $0.value)
                }
            } else {
                chart = []
            }
            trends = dashboard.trends
            categories = dashboard.categoryTotals
        } else {
            let calendar = Calendar.current
            chart = (0..<7).reversed().compactMap { offset -> DashboardMetric? in
                guard let day = calendar.date(byAdding: .day, value: -offset, to: Date()) else { return nil }
                let count = sessions.filter {
                    guard let updated = MessageTimeFormatter.date(fromUpdatedAt: $0.updatedAt) else { return false }
                    return calendar.isDate(updated, inSameDayAs: day)
                }.count
                let label = MessageTimeFormatter.relativeUpdatedLabel(from: day)
                return DashboardMetric(label: label, value: count)
            }
            series = chart.map {
                PlatformActivityDay(label: $0.label, sessions: $0.value, messages: $0.value)
            }
        }

        let recent = sessions
            .sorted {
                MessageTimeFormatter.date(fromUpdatedAt: $0.updatedAt) ?? .distantPast >
                MessageTimeFormatter.date(fromUpdatedAt: $1.updatedAt) ?? .distantPast
            }
            .prefix(5)
            .map { $0 }
        cachedChartData = chart
        cachedActivitySeries = series
        cachedTrends = trends
        cachedCategories = categories
        cachedRecentActivity = recent
    }

    private var dashboardTourSteps: [DashboardTourStep] {
        [
            DashboardTourStep(
                title: L10n.DashboardTourSearchTitle,
                description: L10n.DashboardTourSearchDesc,
                pointerSymbol: "arrow.up.right",
                alignment: .topTrailing,
                edgeInsets: EdgeInsets(top: 88, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: L10n.DashboardTourLiveTitle,
                description: L10n.DashboardTourLiveDesc,
                pointerSymbol: "arrow.up.left",
                alignment: .topLeading,
                edgeInsets: EdgeInsets(top: 160, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: L10n.DashboardTourRequestsTitle,
                description: L10n.DashboardTourRequestsDesc,
                pointerSymbol: "arrow.up",
                alignment: .top,
                edgeInsets: EdgeInsets(top: 228, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: L10n.DashboardTourNotificationsTitle,
                description: L10n.DashboardTourNotificationsDesc,
                pointerSymbol: "arrow.up.right",
                alignment: .topTrailing,
                edgeInsets: EdgeInsets(top: 310, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: L10n.DashboardTourLanguageTitle,
                description: L10n.DashboardTourLanguageDesc,
                pointerSymbol: "arrow.down.right",
                alignment: .trailing,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: L10n.DashboardTourProfileTitle,
                description: L10n.DashboardTourProfileDesc,
                pointerSymbol: "arrow.down.left",
                alignment: .bottomLeading,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 120, trailing: 16)
            ),
            DashboardTourStep(
                title: L10n.DashboardTourDevicesTitle,
                description: L10n.DashboardTourDevicesDesc,
                pointerSymbol: "arrow.down",
                alignment: .bottom,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 164, trailing: 16)
            ),
            DashboardTourStep(
                title: L10n.DashboardTourAdminTitle,
                description: L10n.DashboardTourAdminDesc,
                pointerSymbol: "arrow.down.left",
                alignment: .bottomTrailing,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 94, trailing: 16)
            )
        ]
    }

    var body: some View {
        dashboardList
            .navigationTitle(L10n.ModuleDashboard)
            .navigationBarTitleDisplayMode(.large)
            .toolbarBackground(PAXTheme.background, for: .navigationBar)
            .toolbarBackground(.visible, for: .navigationBar)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    HStack(spacing: 8) {
                        StaffShellNotificationBell()
                        PAXAppearanceQuickSwitch()
                        Button {
                            showSearch = true
                            PAXHaptics.light()
                        } label: {
                            PAXIcon("magnifyingglass")
                        }
                        .accessibilityLabel(L10n.GlobalSearchTitle)
                    }
                }
            }
            .sheet(isPresented: $showSearch) {
                NavigationStack { GlobalSearchView() }
                    .environmentObject(auth)
                    .environmentObject(coordinator)
                    .environmentObject(teamCoordinator)
            }
            .overlay {
                if showDashboardTour, dashboardTourSteps.indices.contains(dashboardTourStepIndex) {
                    DashboardTourOverlay(
                        step: dashboardTourSteps[dashboardTourStepIndex],
                        stepIndex: dashboardTourStepIndex,
                        totalSteps: dashboardTourSteps.count,
                        onBack: { dashboardTourStepIndex = max(dashboardTourStepIndex - 1, 0) },
                        onNext: {
                            guard dashboardTourStepIndex + 1 < dashboardTourSteps.count else {
                                completeDashboardTour()
                                return
                            }
                            dashboardTourStepIndex += 1
                        },
                        onSkip: completeDashboardTour,
                        onFinish: completeDashboardTour
                    )
                }
            }
            .onAppear {
                Task {
                    await ActivityLogService.shared.log(
                        category: L10n.ModuleDashboard,
                        title: L10n.ActivityDashboardOpened,
                        module: PlatformModule.dashboard.rawValue,
                        auth: auth
                    )
                    startDashboardTourIfNeeded()
                    async let sessions: Void = coordinator.refreshSessions(auth: auth)
                    async let platformSync: Void = platform.sync(auth: auth)
                    async let teamSync: Void = teamCoordinator.refresh(auth: auth)
                    _ = await (sessions, platformSync, teamSync)
                    recomputeDashboardMetrics()
                    withAnimation(.easeOut(duration: 0.25)) {
                        isInitialLoading = false
                    }
                }
            }
            .onChange(of: coordinator.sessions) { _ in recomputeDashboardMetrics() }
            .onChange(of: settings.dashboardTourCompleted) { completed in
                if !completed {
                    startDashboardTourIfNeeded()
                }
            }
    }

    private var dashboardList: some View {
        List {
            if isInitialLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.DashboardLoading, rowCount: 3, preset: .dashboard)
                }
            } else {
                Section {
                    heroHeader
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }

                Section {
                    metricsGrid
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }

                if PlatformModuleSettingsStore.shared.dashboardShowChart {
                    Section {
                        activityChart
                            .listRowInsets(EdgeInsets())
                            .listRowBackground(Color.clear)
                            .listRowSeparator(.hidden)
                    }
                }

                Section {
                    activityFeed
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }

                Section {
                    quickModules
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }

                if PlatformModuleSettingsStore.shared.dashboardShowUpcoming {
                    Section {
                        upcomingSection
                            .listRowInsets(EdgeInsets())
                            .listRowBackground(Color.clear)
                            .listRowSeparator(.hidden)
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .paxPremiumRefreshable(status: L10n.DashboardLoading, rowCount: 3) {
            async let sessions: Void = coordinator.refreshSessions(auth: auth)
            async let platformSync: Void = platform.sync(auth: auth)
            async let teamSync: Void = teamCoordinator.refresh(auth: auth)
            _ = await (sessions, platformSync, teamSync)
            WidgetDataStore.shared.syncFromApp()
            recomputeDashboardMetrics()
        }
    }

    private var heroHeader: some View {
        VStack(alignment: .leading, spacing: 20) {
            Text(L10n.DashboardWelcome(auth.profile?.displayName ?? L10n.CommonAdministrator).uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.6)
                .foregroundStyle(PAXTheme.textTertiary)

            Text("\(platform.dashboard?.sessionsTotal ?? customerSessions.count)")
                .font(PAXTypography.balance)
                .monospacedDigit()
                .foregroundStyle(PAXTheme.textPrimary)
                .minimumScaleFactor(0.7)
                .lineLimit(1)

            Text(L10n.DashboardMetricSessions)
                .font(PAXTypography.subsection)
                .foregroundStyle(PAXTheme.textSecondary)

            HStack(alignment: .top, spacing: 8) {
                NavigationLink {
                    TasksModuleView()
                } label: {
                    PAXQuickActionVisual(title: L10n.DashboardMetricTasks, systemImage: "checklist", emphasized: true)
                }
                .buttonStyle(PAXRevolutPressableStyle())

                NavigationLink {
                    NotificationsCenterView()
                } label: {
                    PAXQuickActionVisual(title: L10n.PlatformNotifications, systemImage: "bell.badge.fill")
                }
                .buttonStyle(PAXRevolutPressableStyle())

                NavigationLink {
                    CalendarModuleView()
                } label: {
                    PAXQuickActionVisual(title: L10n.DashboardUpcoming, systemImage: "calendar")
                }
                .buttonStyle(PAXRevolutPressableStyle())

                PAXQuickActionButton(title: L10n.GlobalSearchTitle, systemImage: "magnifyingglass") {
                    showSearch = true
                    PAXHaptics.light()
                }
            }
        }
        .padding(.horizontal, PAXSpacing.screenHorizontal)
        .padding(.top, 8)
        .padding(.bottom, 8)
        .paxStaggeredAppear(index: 0)
        .transition(PAXMotion.heroReveal)
    }

    private var metricsGrid: some View {
        VStack(spacing: 12) {
            PAXRevolutMetricTile(
                title: L10n.DashboardMetricUnread,
                value: "\(unreadCount)",
                systemImage: "envelope.badge.fill",
                tint: unreadCount > 0 ? PAXTheme.danger : PAXTheme.accent
            )
            PAXRevolutMetricTile(
                title: L10n.DashboardMetricLive,
                value: "\(platform.dashboard?.liveCount ?? coordinator.liveCount)",
                systemImage: "bell.badge.fill",
                tint: PAXTheme.danger,
                trend: cachedTrends.liveRequestsPct
            )
            PAXRevolutMetricTile(
                title: L10n.DashboardMetricTasks,
                value: "\(platform.dashboard?.openTasks ?? tasks.openCount)",
                systemImage: "checklist",
                tint: PAXTheme.success,
                trend: cachedTrends.sessionsPct
            )
        }
        .padding(.horizontal, PAXSpacing.screenHorizontal)
    }

    private var activityFeed: some View {
        VStack(alignment: .leading, spacing: 4) {
            PAXRevolutSectionHeader(title: L10n.DashboardActivityFeed)
                .padding(.horizontal, PAXSpacing.screenHorizontal)

            if recentActivityItems.isEmpty {
                Text(L10n.DashboardActivityEmpty)
                    .font(PAXTypography.body)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .padding(.horizontal, PAXSpacing.screenHorizontal)
                    .padding(.top, 8)
            } else {
                VStack(spacing: 0) {
                    ForEach(recentActivityItems, id: \.sessionId) { session in
                        PAXRevolutListRow(
                            title: session.displayName,
                            subtitle: session.lastPreview.isEmpty ? session.detectedService : session.lastPreview,
                            highlighted: session.isLiveRequest,
                            leading: {
                                PAXAvatar(name: session.displayName, size: 40)
                            },
                            trailing: {
                                Text(MessageTimeFormatter.relativeUpdatedLabel(from: MessageTimeFormatter.date(fromUpdatedAt: session.updatedAt) ?? Date()))
                                    .font(PAXTypography.meta)
                                    .foregroundStyle(session.isLiveRequest ? PAXTheme.danger : PAXTheme.textTertiary)
                            }
                        )
                        Rectangle().fill(PAXTheme.divider).frame(height: 1).padding(.leading, 68)
                    }
                }
                .paxRevolutSurface(cornerRadius: 16, elevation: 0)
                .padding(.horizontal, PAXSpacing.screenHorizontal)
            }
        }
        .padding(.top, 8)
    }

    private var activityChart: some View {
        PAXProfessionalAnalyticsDashboard(
            title: L10n.DashboardChartTitle,
            days: cachedActivitySeries,
            trends: cachedTrends,
            categories: cachedCategories.isEmpty ? fallbackCategories : cachedCategories
        )
    }

    private var fallbackCategories: [PlatformReportSlice] {
        let live = customerSessions.filter(\.isLiveRequest).count
        let closed = customerSessions.filter(\.isClosed).count
        let active = max(0, customerSessions.count - live - closed)
        return [
            PlatformReportSlice(label: "live", value: live),
            PlatformReportSlice(label: "active", value: active),
            PlatformReportSlice(label: "closed", value: closed),
            PlatformReportSlice(label: "tasks", value: platform.dashboard?.openTasks ?? tasks.openCount),
        ]
    }

    private var quickModules: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(L10n.DashboardQuickAccess)
                .font(.headline)

            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                ForEach(Array(PlatformModuleAccess.availableHubModules(auth: auth).filter { $0 != .dashboard }.prefix(6).enumerated()), id: \.element.id) { index, module in
                    NavigationLink(value: module) {
                        PlatformModuleCard(
                            title: module.title,
                            subtitle: module.subtitle,
                            systemImage: module.systemImage,
                            tint: module.tint,
                            helpText: module.helpDescription
                        )
                    }
                    .buttonStyle(.plain)
                    .paxStaggeredAppear(index: index + 4)
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
                        PAXIcon(event.category.systemImage)
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

    private func startDashboardTourIfNeeded() {
        guard auth.isLoggedIn, !settings.dashboardTourCompleted else { return }
        guard !showDashboardTour else { return }
        dashboardTourStepIndex = 0
        Task { @MainActor in
            try? await Task.sleep(nanoseconds: 380_000_000)
            if !settings.dashboardTourCompleted {
                showDashboardTour = true
            }
        }
    }

    private func completeDashboardTour() {
        withAnimation(.easeInOut(duration: 0.2)) {
            showDashboardTour = false
        }
        settings.dashboardTourCompleted = true
        PAXHaptics.success()
    }
}

private struct DashboardMetric: Identifiable {
    let id = UUID()
    let label: String
    let value: Int
}

private struct DashboardStatusChip: Identifiable {
    let id = UUID()
    let title: String
    let value: String
    let icon: String
    let tint: Color
}

private struct DashboardStatusChipView: View {
    let chip: DashboardStatusChip

    var body: some View {
        HStack(spacing: 9) {
            PAXIcon(chip.icon, size: .inline)
            VStack(alignment: .leading, spacing: 1) {
                Text(chip.value)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(chip.title)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 9)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(chip.tint.opacity(0.12))
        )
    }
}

private struct DashboardTourStep {
    let title: String
    let description: String
    let pointerSymbol: String
    let alignment: Alignment
    let edgeInsets: EdgeInsets
}

private struct DashboardTourOverlay: View {
    let step: DashboardTourStep
    let stepIndex: Int
    let totalSteps: Int
    let onBack: () -> Void
    let onNext: () -> Void
    let onSkip: () -> Void
    let onFinish: () -> Void

    private var isLast: Bool { stepIndex == totalSteps - 1 }

    var body: some View {
        ZStack {
            Color.black.opacity(0.46)
                .ignoresSafeArea()

            VStack(alignment: .leading, spacing: 12) {
                HStack(spacing: 8) {
                    PAXIcon(step.pointerSymbol, size: .row)
                    Text(L10n.DashboardTourStepOf(stepIndex + 1, totalSteps))
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(PAXTheme.textSecondary)
                }

                Text(step.title)
                    .font(.headline)
                    .foregroundStyle(PAXTheme.textPrimary)

                Text(step.description)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)

                HStack(spacing: 8) {
                    if stepIndex > 0 {
                        Button(L10n.CommonBack, action: onBack)
                            .buttonStyle(.bordered)
                    }
                    Button(L10n.CommonSkip, action: onSkip)
                        .buttonStyle(.bordered)
                    Spacer()
                    Button(isLast ? L10n.CommonFinish : L10n.CommonNext) {
                        if isLast { onFinish() } else { onNext() }
                    }
                    .buttonStyle(.borderedProminent)
                }
            }
            .padding(14)
            .frame(maxWidth: 340, alignment: .leading)
            .paxGlassCardStyle(cornerRadius: 16, fillOpacity: 0.84, borderOpacity: 0.5, shadowOpacity: 0.22)
            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: step.alignment)
            .padding(step.edgeInsets)
        }
        .transition(.opacity)
    }
}

private extension L10n {
    static func DashboardWelcome(_ name: String) -> String {
        String(format: String(localized: "dashboard.welcome"), name)
    }
}
