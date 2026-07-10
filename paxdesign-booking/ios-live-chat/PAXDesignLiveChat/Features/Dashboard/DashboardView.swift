import SwiftUI
#if !SIDELOAD
import Charts
#endif

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

    private var recentActivityItems: [LiveSession] {
        customerSessions
            .sorted {
                MessageTimeFormatter.date(fromUpdatedAt: $0.updatedAt) ?? .distantPast >
                MessageTimeFormatter.date(fromUpdatedAt: $1.updatedAt) ?? .distantPast
            }
            .prefix(5)
            .map { $0 }
    }

    private var dashboardTourSteps: [DashboardTourStep] {
        [
            DashboardTourStep(
                title: "Suche",
                description: "Über die Suche oben rechts finden Sie Chats, Aufgaben und Kunden sofort.",
                pointerSymbol: "arrow.up.right",
                alignment: .topTrailing,
                edgeInsets: EdgeInsets(top: 88, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: "Live-Chat",
                description: "Hier sehen Sie alle aktiven Gespräche und offene Live-Anfragen in Echtzeit.",
                pointerSymbol: "arrow.up.left",
                alignment: .topLeading,
                edgeInsets: EdgeInsets(top: 160, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: "Aufträge & Requests",
                description: "Die Kennzahlenkarten zeigen aktuelle Requests, offene Tasks und Prioritäten.",
                pointerSymbol: "arrow.up",
                alignment: .top,
                edgeInsets: EdgeInsets(top: 228, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: "Benachrichtigungen",
                description: "Neue Nachrichten und Live-Events landen sofort in Ihren Benachrichtigungen.",
                pointerSymbol: "arrow.up.right",
                alignment: .topTrailing,
                edgeInsets: EdgeInsets(top: 310, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: "Sprachwechsel",
                description: "Über Einstellungen > Sprache wechseln Sie die komplette App-Sprache.",
                pointerSymbol: "arrow.down.right",
                alignment: .trailing,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 0, trailing: 16)
            ),
            DashboardTourStep(
                title: "Profil & Einstellungen",
                description: "Im Profil bearbeiten Sie Konto, Sounds, Sicherheit und Personalisierung.",
                pointerSymbol: "arrow.down.left",
                alignment: .bottomLeading,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 120, trailing: 16)
            ),
            DashboardTourStep(
                title: "Geräteverwaltung",
                description: "Geräte-Freigaben und Status finden Sie im Admin-Bereich unter Sicherheit.",
                pointerSymbol: "arrow.down",
                alignment: .bottom,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 164, trailing: 16)
            ),
            DashboardTourStep(
                title: "Admin- & Team-Tools",
                description: "Teamverwaltung, Rollen, Aufgaben und Kundentools sind zentral im Hub verfügbar.",
                pointerSymbol: "arrow.down.left",
                alignment: .bottomTrailing,
                edgeInsets: EdgeInsets(top: 0, leading: 16, bottom: 94, trailing: 16)
            )
        ]
    }

    var body: some View {
        ScrollView {
            if isInitialLoading {
                PAXScreenLoadingStack(status: "Dashboard wird geladen", rowCount: 3)
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
            } else {
                VStack(alignment: .leading, spacing: 20) {
                    heroHeader
                    metricsGrid
                    if PlatformModuleSettingsStore.shared.dashboardShowChart {
                        activityChart
                    }
                    activityFeed
                    quickModules
                    if PlatformModuleSettingsStore.shared.dashboardShowUpcoming {
                        upcomingSection
                    }
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
            }
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
        .paxPremiumRefreshable(status: "Dashboard wird geladen", rowCount: 3) {
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
                startDashboardTourIfNeeded()
                try? await Task.sleep(nanoseconds: 450_000_000)
                withAnimation(.easeOut(duration: 0.25)) {
                    isInitialLoading = false
                }
            }
        }
        .onChange(of: settings.dashboardTourCompleted) { completed in
            if !completed {
                startDashboardTourIfNeeded()
            }
        }
    }

    private var heroHeader: some View {
        PlatformHeroHeader(
            title: L10n.DashboardWelcome(auth.profile?.displayName ?? L10n.CommonAdministrator),
            subtitle: L10n.DashboardSubtitle,
            systemImage: "chart.bar.doc.horizontal.fill",
            gradient: [.blue, .cyan]
        )
        .transition(PAXMotion.heroReveal)
    }

    private var metricsGrid: some View {
        LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
            PAXMetricCard(
                title: L10n.DashboardMetricSessions,
                value: "\(platform.dashboard?.sessionsTotal ?? customerSessions.count)",
                icon: "bubble.left.and.bubble.right.fill",
                tint: .blue
            )
            PAXMetricCard(
                title: L10n.DashboardMetricUnread,
                value: "\(unreadCount)",
                icon: "envelope.badge.fill",
                tint: .orange
            )
            PAXMetricCard(
                title: L10n.DashboardMetricLive,
                value: "\(platform.dashboard?.liveCount ?? coordinator.liveCount)",
                icon: "bell.and.waves.left.and.right.fill",
                tint: .red
            )
            PAXMetricCard(
                title: L10n.DashboardMetricTasks,
                value: "\(platform.dashboard?.openTasks ?? tasks.openCount)",
                icon: "checklist",
                tint: .green
            )
        }
    }

    private var activityFeed: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Text("Live Activity Feed")
                    .font(.headline)
                Spacer()
                Text("Realtime")
                    .font(.caption2.weight(.bold))
                    .foregroundStyle(.mint)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 4)
                    .background(Capsule().fill(Color.mint.opacity(0.18)))
            }

            if recentActivityItems.isEmpty {
                Text("Noch keine aktuelle Aktivität.")
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .frame(maxWidth: .infinity, alignment: .leading)
            } else {
                VStack(spacing: 10) {
                    ForEach(recentActivityItems, id: \.sessionId) { session in
                        PAXListCard(highlighted: session.isLiveRequest, accent: session.isLiveRequest ? .red : PAXTheme.accent) {
                            HStack(spacing: 10) {
                                Circle()
                                    .fill(session.isLiveRequest ? Color.red : PAXTheme.accent)
                                    .frame(width: 9, height: 9)
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(session.displayName)
                                        .font(.subheadline.weight(.semibold))
                                    Text(session.lastPreview.isEmpty ? session.detectedService : session.lastPreview)
                                        .font(.caption)
                                        .foregroundStyle(PAXTheme.textSecondary)
                                        .lineLimit(1)
                                }
                                Spacer()
                                Text(MessageTimeFormatter.relativeUpdatedLabel(from: MessageTimeFormatter.date(fromUpdatedAt: session.updatedAt) ?? Date()))
                                    .font(.caption2)
                                    .foregroundStyle(PAXTheme.textTertiary)
                            }
                        }
                    }
                }
            }
        }
        .padding(16)
        .paxPremiumGlass(tier: .premium, cornerRadius: 18, accent: .mint)
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
            Image(systemName: chip.icon)
                .font(.caption.weight(.semibold))
                .foregroundStyle(chip.tint)
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
                    Image(systemName: step.pointerSymbol)
                        .font(.headline.weight(.bold))
                        .foregroundStyle(PAXTheme.accent)
                    Text("Schritt \(stepIndex + 1) von \(totalSteps)")
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
                        Button("Back", action: onBack)
                            .buttonStyle(.bordered)
                    }
                    Button("Skip", action: onSkip)
                        .buttonStyle(.bordered)
                    Spacer()
                    Button(isLast ? "Finish" : "Next") {
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
