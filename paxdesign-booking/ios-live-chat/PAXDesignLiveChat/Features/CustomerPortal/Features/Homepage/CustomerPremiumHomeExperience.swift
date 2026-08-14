import SwiftUI

// MARK: - Premium authenticated home (below hero)

struct CustomerPremiumHomeExperience: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme

    let dashboard: CustomerDashboard
    let profileName: String
    @State private var appeared = false

    var body: some View {
        VStack(alignment: .leading, spacing: 28) {
            CustomerHomeInsightHeader(profileName: profileName, dashboard: dashboard)
                .premiumHomeAppear(appeared, delay: 0)

            CustomerHomeMetricsBoard(dashboard: dashboard)
                .premiumHomeAppear(appeared, delay: 0.03)

            CustomerHomeQuickActionsGrid(dashboard: dashboard)
                .premiumHomeAppear(appeared, delay: 0.07)

            if let preview = dashboard.chat?.last_preview, !preview.isEmpty {
                CustomerHomeConversationSpotlight(
                    preview: preview,
                    sessionID: dashboard.chat?.session_id,
                    messageCount: dashboard.chat?.message_count ?? 0,
                    handler: dashboard.chat?.handler
                )
                .premiumHomeAppear(appeared, delay: 0.11)
            }

            CustomerCybercrimeAccessCard(compact: true) {
                navigation.openCybercrime()
            }
            .premiumHomeAppear(appeared, delay: 0.14)

            if let projects = dashboard.projects_active, !projects.isEmpty {
                CustomerHomeProjectsCarousel(projects: projects)
                    .premiumHomeAppear(appeared, delay: 0.18)
            }

            if let orders = dashboard.orders_recent, !orders.isEmpty {
                CustomerHomeRequestsPanel(orders: orders)
                    .premiumHomeAppear(appeared, delay: 0.22)
            }

            CustomerHomeFilesSpotlight(filesCount: dashboard.files_count ?? 0)
                .premiumHomeAppear(appeared, delay: 0.25)

            if let services = dashboard.services_featured, !services.isEmpty {
                CustomerHomeRecommendedServices(services: services)
                    .premiumHomeAppear(appeared, delay: 0.28)
            }

            if let portfolio = dashboard.portfolio, !portfolio.isEmpty {
                CustomerHomeFeaturedWorkStrip(items: portfolio)
                    .premiumHomeAppear(appeared, delay: 0.31)
            }

            if let news = dashboard.news, !news.isEmpty {
                CustomerHomeNewsDigest(news: news)
                    .premiumHomeAppear(appeared, delay: 0.34)
            }
        }
        .padding(.horizontal, CustomerResponsiveLayout.screenPadding)
        .padding(.vertical, 32)
        .frame(maxWidth: .infinity, alignment: .leading)
        .onAppear {
            withAnimation(.spring(response: 0.55, dampingFraction: 0.84)) {
                appeared = true
            }
        }
    }
}

// MARK: - Workspace loading shimmer

struct CustomerHomeWorkspaceLoadingStrip: View {
    @Environment(\.marketingTheme) private var theme
    @State private var shimmer = false

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            RoundedRectangle(cornerRadius: 8, style: .continuous)
                .fill(theme.panel)
                .frame(width: 180, height: 28)
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .fill(theme.panel)
                .frame(height: 148)
            HStack(spacing: 12) {
                ForEach(0..<4, id: \.self) { _ in
                    VStack(spacing: 8) {
                        Circle().fill(theme.panel).frame(width: 64, height: 64)
                        RoundedRectangle(cornerRadius: 4, style: .continuous)
                            .fill(theme.panel)
                            .frame(width: 44, height: 10)
                    }
                    .frame(maxWidth: .infinity)
                }
            }
        }
        .padding(.horizontal, CustomerResponsiveLayout.screenPadding)
        .padding(.vertical, 32)
        .opacity(shimmer ? 0.55 : 1)
        .animation(.easeInOut(duration: 1.1).repeatForever(autoreverses: true), value: shimmer)
        .onAppear { shimmer = true }
    }
}

// MARK: - Insight header

private struct CustomerHomeInsightHeader: View {
    @ObservedObject private var badgeStore = CustomerNotificationsBadgeStore.shared
    let profileName: String
    let dashboard: CustomerDashboard

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(headerDate.uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.8)
                .foregroundStyle(PAXTheme.textTertiary)
            Text(CustomerHomeGreeting.text(forName: profileName))
                .font(PAXTypography.titleLarge)
                .foregroundStyle(PAXTheme.textPrimary)
                .fixedSize(horizontal: false, vertical: true)
            Text(statusLine)
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textSecondary)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private var headerDate: String {
        Date.now.formatted(.dateTime.weekday(.wide).month(.abbreviated).day())
    }

    private var statusLine: String {
        let projects = dashboard.projects_active?.count ?? 0
        let unread = badgeStore.unreadCount
        if projects == 0 && unread == 0 {
            return String(localized: "Workspace is clear. Start a request or open chat.")
        }
        if unread > 0 {
            return String(localized: "\(projects) active projects · \(unread) unread updates")
        }
        return String(localized: "\(projects) active projects in progress")
    }
}

// MARK: - Metrics board

private struct CustomerHomeMetricsBoard: View {
    @Environment(\.marketingTheme) private var theme
    @ObservedObject private var badgeStore = CustomerNotificationsBadgeStore.shared
    let dashboard: CustomerDashboard

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            primaryCard
            VStack(spacing: 10) {
                compactMetric(
                    value: "\(badgeStore.unreadCount)",
                    label: String(localized: "Unread"),
                    icon: "bell.badge.fill",
                    tint: Color(uiColor: PAXDynamic.spend)
                )
                compactMetric(
                    value: "\(dashboard.files_count ?? 0)",
                    label: String(localized: "Files"),
                    icon: "doc.on.doc.fill",
                    tint: Color(uiColor: PAXDynamic.income)
                )
                compactMetric(
                    value: "\(dashboard.orders_recent?.count ?? 0)",
                    label: String(localized: "Requests"),
                    icon: "doc.text.fill",
                    tint: Color(red: 0.35, green: 0.62, blue: 1)
                )
            }
            .frame(maxWidth: .infinity)
        }
    }

    private var primaryCard: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack {
                Text(String(localized: "Workspace").uppercased())
                    .font(PAXTypography.labelUpper)
                    .tracking(0.6)
                    .foregroundStyle(PAXTheme.textTertiary)
                Spacer()
                PAXIcon("chart.bar.fill", size: .inline, emphasis: .tertiary)
            }
            Text("\(dashboard.projects_active?.count ?? 0)")
                .font(.system(size: 44, weight: .bold, design: .rounded))
                .monospacedDigit()
                .foregroundStyle(PAXTheme.textPrimary)
            Text(String(localized: "Active projects"))
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textSecondary)
            progressTrack
        }
        .padding(18)
        .frame(maxWidth: .infinity, minHeight: 168, alignment: .leading)
        .paxRevolutSurface(cornerRadius: 22, elevation: 1)
    }

    private var progressTrack: some View {
        VStack(alignment: .leading, spacing: 6) {
            GeometryReader { geo in
                ZStack(alignment: .leading) {
                    Capsule().fill(theme.border.opacity(0.35))
                    Capsule()
                        .fill(PAXBrandGradient.linear)
                        .frame(width: max(8, geo.size.width * CGFloat(averageProgress) / 100))
                }
            }
            .frame(height: 6)
            Text(String(localized: "\(averageProgress)% average completion"))
                .font(PAXTypography.caption)
                .foregroundStyle(PAXTheme.textTertiary)
                .monospacedDigit()
        }
    }

    private var averageProgress: Int {
        let projects = dashboard.projects_active ?? []
        guard !projects.isEmpty else { return 0 }
        return max(0, min(100, projects.map(\.progress).reduce(0, +) / projects.count))
    }

    private func compactMetric(value: String, label: String, icon: String, tint: Color) -> some View {
        HStack(spacing: 10) {
            PAXRevolutGlyphAvatar(systemImage: icon, size: 32, tint: tint)
            VStack(alignment: .leading, spacing: 1) {
                Text(value)
                    .font(.headline.weight(.bold))
                    .monospacedDigit()
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(label)
                    .font(PAXTypography.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .paxRevolutSurface(cornerRadius: 14, elevation: 0)
    }
}

// MARK: - Quick actions

private struct CustomerHomeQuickActionsGrid: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @ObservedObject private var badgeStore = CustomerNotificationsBadgeStore.shared
    let dashboard: CustomerDashboard

    var body: some View {
        VStack(spacing: 18) {
            HStack(alignment: .top, spacing: 8) {
                PAXQuickActionButton(
                    title: String(localized: "Chat"),
                    systemImage: "bubble.left.and.bubble.right.fill",
                    emphasized: true
                ) {
                    PAXHaptics.light()
                    navigation.openChat(sessionID: dashboard.chat?.session_id)
                }
                PAXQuickActionButton(
                    title: String(localized: "Request"),
                    systemImage: "plus"
                ) {
                    PAXHaptics.light()
                    navigation.openOrdersList()
                }
                PAXQuickActionButton(
                    title: String(localized: "Projects"),
                    systemImage: "folder.fill"
                ) {
                    PAXHaptics.light()
                    navigation.openProjectsList()
                }
                PAXQuickActionButton(
                    title: String(localized: "Files"),
                    systemImage: "doc.on.doc"
                ) {
                    PAXHaptics.light()
                    navigation.openFiles()
                }
            }
            HStack(alignment: .top, spacing: 8) {
                PAXQuickActionButton(
                    title: String(localized: "Alerts"),
                    systemImage: "bell.fill",
                    badge: badgeStore.unreadCount
                ) {
                    PAXHaptics.light()
                    navigation.openNotifications()
                }
                PAXQuickActionButton(
                    title: String(localized: "Cyber"),
                    systemImage: "shield.checkered"
                ) {
                    PAXHaptics.light()
                    navigation.openCybercrime()
                }
                PAXQuickActionButton(
                    title: String(localized: "Services"),
                    systemImage: "square.grid.2x2.fill"
                ) {
                    PAXHaptics.light()
                    navigation.selectedTab = .services
                }
                PAXQuickActionButton(
                    title: String(localized: "Account"),
                    systemImage: "person.crop.circle.fill"
                ) {
                    PAXHaptics.light()
                    navigation.selectedTab = .account
                }
            }
        }
        .accessibilityElement(children: .contain)
    }
}

// MARK: - Conversation spotlight

private struct CustomerHomeConversationSpotlight: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    let preview: String
    let sessionID: String?
    let messageCount: Int
    let handler: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 10) {
                PAXRevolutGlyphAvatar(systemImage: "message.fill", size: 36, tint: theme.accent)
                VStack(alignment: .leading, spacing: 2) {
                    Text(String(localized: "Live conversation"))
                        .font(PAXTypography.rowTitle)
                        .foregroundStyle(theme.textPrimary)
                    Text(handlerLabel)
                        .font(PAXTypography.caption)
                        .foregroundStyle(theme.textSecondary)
                }
                Spacer()
                if messageCount > 0 {
                    Text("\(messageCount)")
                        .font(.caption.weight(.bold).monospacedDigit())
                        .foregroundStyle(theme.textPrimary)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(theme.accent.opacity(0.14))
                        .clipShape(Capsule())
                }
            }
            Text(preview)
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .lineLimit(3)
                .fixedSize(horizontal: false, vertical: true)
            Button(String(localized: "Continue in Chat")) {
                PAXHaptics.light()
                navigation.openChat(sessionID: sessionID)
            }
            .font(.subheadline.weight(.semibold))
            .foregroundStyle(theme.accentOnAccent)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(PAXBrandGradient.linear)
            .clipShape(Capsule())
        }
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .paxRevolutSurface(cornerRadius: 22, elevation: 1)
    }

    private var handlerLabel: String {
        switch handler?.lowercased() {
        case "admin", "live_request":
            return String(localized: "Support online")
        case "closed":
            return String(localized: "Conversation closed")
        default:
            return String(localized: "AI assistant")
        }
    }
}

// MARK: - Projects carousel

private struct CustomerHomeProjectsCarousel: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    let projects: [CustomerDashboard.ProjectSummary]

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            sectionHeader(
                title: String(localized: "Active projects"),
                icon: "folder.fill",
                actionTitle: String(localized: "View all")
            ) {
                navigation.openProjectsList()
            }
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 14) {
                    ForEach(projects.prefix(5), id: \.id) { project in
                        NavigationLink {
                            CustomerProjectDetailView(projectId: project.id)
                        } label: {
                            projectCard(project)
                        }
                        .buttonStyle(.plain)
                    }
                }
                .padding(.horizontal, 2)
            }
        }
    }

    private func projectCard(_ project: CustomerDashboard.ProjectSummary) -> some View {
        VStack(alignment: .leading, spacing: 14) {
            ZStack {
                Circle()
                    .stroke(theme.border.opacity(0.4), lineWidth: 5)
                    .frame(width: 56, height: 56)
                Circle()
                    .trim(from: 0, to: CGFloat(min(max(project.progress, 0), 100)) / 100)
                    .stroke(theme.accent, style: StrokeStyle(lineWidth: 5, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                    .frame(width: 56, height: 56)
                Text("\(project.progress)%")
                    .font(.caption2.weight(.bold).monospacedDigit())
                    .foregroundStyle(theme.textPrimary)
            }
            Text(project.title)
                .font(.headline)
                .foregroundStyle(theme.textPrimary)
                .lineLimit(2)
                .multilineTextAlignment(.leading)
            Text(project.status.capitalized)
                .font(.caption.weight(.medium))
                .foregroundStyle(theme.textSecondary)
        }
        .padding(18)
        .frame(width: 188, alignment: .leading)
        .paxRevolutSurface(cornerRadius: 20, elevation: 0)
    }
}

// MARK: - Requests panel

private struct CustomerHomeRequestsPanel: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    let orders: [CustomerDashboard.OrderSummary]

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            sectionHeader(
                title: String(localized: "Recent requests"),
                icon: "doc.text",
                actionTitle: String(localized: "View all")
            ) {
                navigation.openOrdersList()
            }
            VStack(spacing: 0) {
                ForEach(Array(orders.prefix(4).enumerated()), id: \.element.id) { _, order in
                    NavigationLink {
                        CustomerOrderDetailView(orderId: order.id)
                    } label: {
                        HStack(spacing: 14) {
                            PAXRevolutGlyphAvatar(systemImage: "doc.text", size: 36, tint: theme.accent)
                            VStack(alignment: .leading, spacing: 3) {
                                Text(order.service_label)
                                    .font(.subheadline.weight(.semibold))
                                    .foregroundStyle(theme.textPrimary)
                                Text(order.ref)
                                    .font(.caption.monospaced())
                                    .foregroundStyle(theme.textSecondary)
                            }
                            Spacer()
                            Text(order.status.capitalized)
                                .font(.caption2.weight(.semibold))
                                .foregroundStyle(theme.accent)
                                .padding(.horizontal, 8)
                                .padding(.vertical, 4)
                                .background(theme.accent.opacity(0.12))
                                .clipShape(Capsule())
                        }
                        .padding(.vertical, 14)
                        .padding(.horizontal, 16)
                    }
                    .buttonStyle(.plain)
                }
            }
            .paxRevolutSurface(cornerRadius: 20, elevation: 0)
        }
    }
}

// MARK: - Files spotlight

private struct CustomerHomeFilesSpotlight: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    let filesCount: Int

    var body: some View {
        Button {
            PAXHaptics.light()
            navigation.openFiles()
        } label: {
            HStack(spacing: 16) {
                PAXRevolutGlyphAvatar(
                    systemImage: "doc.on.doc",
                    size: 48,
                    tint: Color(uiColor: PAXDynamic.income)
                )
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Files and invoices"))
                        .font(.headline)
                        .foregroundStyle(theme.textPrimary)
                    Text(filesCount > 0
                         ? String(localized: "\(filesCount) documents ready to download")
                         : String(localized: "Access shared documents, quotes, and invoices"))
                        .font(.subheadline)
                        .foregroundStyle(theme.textSecondary)
                        .multilineTextAlignment(.leading)
                }
                Spacer(minLength: 0)
                PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
            }
            .padding(18)
            .paxRevolutSurface(cornerRadius: 20, elevation: 0)
        }
        .buttonStyle(PremiumHomePressStyle())
    }
}

// MARK: - Recommended services

private struct CustomerHomeRecommendedServices: View {
    @Environment(\.marketingTheme) private var theme
    let services: [CustomerServicesResponse.Service]

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            sectionTitle(String(localized: "Recommended for you"), icon: "sparkles")
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 14) {
                    ForEach(services.prefix(4)) { service in
                        NavigationLink {
                            CustomerServiceDetailView(slug: service.slug)
                        } label: {
                            serviceCard(service)
                        }
                        .buttonStyle(.plain)
                    }
                }
                .padding(.horizontal, 2)
            }
        }
    }

    private func serviceCard(_ service: CustomerServicesResponse.Service) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            CustomerServiceIconView(iconKey: service.icon_key ?? service.slug, size: 48)
            Text(service.name)
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(theme.textPrimary)
                .lineLimit(2)
                .multilineTextAlignment(.leading)
            Text(service.description)
                .font(.caption)
                .foregroundStyle(theme.textSecondary)
                .lineLimit(3)
                .multilineTextAlignment(.leading)
        }
        .padding(16)
        .frame(width: 200, alignment: .leading)
        .paxRevolutSurface(cornerRadius: 20, elevation: 0)
    }
}

// MARK: - Featured work

private struct CustomerHomeFeaturedWorkStrip: View {
    @Environment(\.marketingTheme) private var theme
    let items: [CustomerDashboard.PortfolioPreview]

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            sectionTitle(String(localized: "Featured work"), icon: "photo.on.rectangle")
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 14) {
                    ForEach(items.prefix(4)) { item in
                        NavigationLink {
                            CustomerPortfolioDetailView(slug: item.slug)
                        } label: {
                            featuredCard(item)
                        }
                        .buttonStyle(.plain)
                    }
                }
            }
        }
    }

    private func featuredCard(_ item: CustomerDashboard.PortfolioPreview) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Group {
                if let imageURL = item.image_url, let url = URL(string: imageURL) {
                    AsyncImage(url: url) { phase in
                        if case .success(let image) = phase {
                            image.resizable().scaledToFill()
                        } else {
                            theme.panel
                        }
                    }
                } else {
                    theme.panel
                }
            }
            .frame(width: 220, height: 120)
            .clipped()
            VStack(alignment: .leading, spacing: 4) {
                Text(item.title)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(theme.textPrimary)
                    .lineLimit(2)
                if let excerpt = item.excerpt, !excerpt.isEmpty {
                    Text(excerpt)
                        .font(.caption)
                        .foregroundStyle(theme.textSecondary)
                        .lineLimit(2)
                }
            }
            .padding(12)
            .frame(width: 220, alignment: .leading)
        }
        .background(theme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .stroke(theme.border.opacity(0.3), lineWidth: 0.5)
        )
    }
}

// MARK: - News digest

private struct CustomerHomeNewsDigest: View {
    @Environment(\.marketingTheme) private var theme
    let news: [CustomerDashboard.NewsItem]

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            sectionTitle(String(localized: "Latest news"), icon: "sparkles")
            ForEach(news.prefix(2), id: \.slug) { item in
                NavigationLink {
                    CustomerNewsDetailView(slug: item.slug)
                } label: {
                    HStack(alignment: .top, spacing: 14) {
                        RoundedRectangle(cornerRadius: 10, style: .continuous)
                            .fill(theme.accent.opacity(0.14))
                            .frame(width: 4)
                        VStack(alignment: .leading, spacing: 4) {
                            Text(item.title)
                                .font(.headline)
                                .foregroundStyle(theme.textPrimary)
                                .multilineTextAlignment(.leading)
                            if let excerpt = item.excerpt, !excerpt.isEmpty {
                                Text(excerpt)
                                    .font(.subheadline)
                                    .foregroundStyle(theme.textSecondary)
                                    .lineLimit(2)
                            }
                        }
                        Spacer(minLength: 0)
                        PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                    }
                    .padding(16)
                    .paxRevolutSurface(cornerRadius: 16, elevation: 0)
                }
                .buttonStyle(PremiumHomePressStyle())
            }
        }
    }
}

// MARK: - Guest premium strip

struct CustomerHomeGuestPremiumStrip: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    @State private var appeared = false

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            Text(String(localized: "Platform access").uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.7)
                .foregroundStyle(PAXTheme.textTertiary)
            Text(String(localized: "Your digital workspace"))
                .font(.title2.weight(.bold))
                .foregroundStyle(theme.textPrimary)
            Text(String(localized: "Sign in to track projects, submit requests, chat with our team, and access your files in one premium experience."))
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .lineSpacing(4)
            HStack(alignment: .top, spacing: 8) {
                PAXQuickActionButton(
                    title: String(localized: "Services"),
                    systemImage: "square.grid.2x2.fill",
                    emphasized: true
                ) {
                    PAXHaptics.light()
                    navigation.selectedTab = .services
                }
                PAXQuickActionButton(
                    title: String(localized: "Cyber"),
                    systemImage: "shield.checkered"
                ) {
                    PAXHaptics.light()
                    navigation.openCybercrime()
                }
                PAXQuickActionButton(
                    title: String(localized: "Chat"),
                    systemImage: "bubble.left.and.bubble.right.fill"
                ) {
                    PAXHaptics.light()
                    navigation.selectedTab = .account
                }
                PAXQuickActionButton(
                    title: String(localized: "Sign in"),
                    systemImage: "person.crop.circle"
                ) {
                    PAXHaptics.light()
                    navigation.selectedTab = .account
                }
            }
            HStack(spacing: 12) {
                Button(String(localized: "Sign In")) {
                    PAXHaptics.light()
                    navigation.selectedTab = .account
                }
                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                .frame(maxWidth: .infinity)

                Button(String(localized: "Create account")) {
                    PAXHaptics.light()
                    navigation.selectedTab = .account
                }
                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
                .frame(maxWidth: .infinity)
            }
        }
        .padding(24)
        .frame(maxWidth: .infinity, alignment: .leading)
        .paxRevolutSurface(cornerRadius: 22, elevation: 1)
        .padding(.horizontal, CustomerResponsiveLayout.screenPadding)
        .padding(.vertical, 24)
        .premiumHomeAppear(appeared, delay: 0)
        .onAppear {
            withAnimation(.spring(response: 0.55, dampingFraction: 0.84)) {
                appeared = true
            }
        }
    }
}

// MARK: - Shared helpers

@ViewBuilder
private func sectionTitle(_ title: String, icon: String) -> some View {
    HStack(spacing: 8) {
        PAXIcon(icon, size: .card, emphasis: .primary)
        Text(title)
            .font(.title3.weight(.semibold))
    }
}

@ViewBuilder
private func sectionHeader(title: String, icon: String, actionTitle: String, action: @escaping () -> Void) -> some View {
    HStack {
        sectionTitle(title, icon: icon)
        Spacer()
        Button(actionTitle) {
            PAXHaptics.light()
            action()
        }
        .font(.subheadline.weight(.medium))
    }
}

private struct PremiumHomePressStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1)
            .opacity(configuration.isPressed ? 0.92 : 1)
            .animation(.spring(response: 0.28, dampingFraction: 0.72), value: configuration.isPressed)
    }
}

private extension View {
    func premiumHomeAppear(_ appeared: Bool, delay: Double) -> some View {
        self
            .opacity(appeared ? 1 : 0)
            .offset(y: appeared ? 0 : 14)
            .animation(.spring(response: 0.5, dampingFraction: 0.82).delay(delay), value: appeared)
    }
}
