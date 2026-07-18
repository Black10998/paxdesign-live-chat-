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
            CustomerHomeInsightHeader(profileName: profileName)
                .premiumHomeAppear(appeared, delay: 0)

            CustomerHomeLiveStatsRow(dashboard: dashboard)
                .premiumHomeAppear(appeared, delay: 0.04)

            CustomerHomeQuickActionsGrid(dashboard: dashboard)
                .premiumHomeAppear(appeared, delay: 0.08)

            if let preview = dashboard.chat?.last_preview, !preview.isEmpty {
                CustomerHomeConversationSpotlight(
                    preview: preview,
                    sessionID: dashboard.chat?.session_id,
                    messageCount: dashboard.chat?.message_count ?? 0,
                    handler: dashboard.chat?.handler
                )
                .premiumHomeAppear(appeared, delay: 0.12)
            }

            if let projects = dashboard.projects_active, !projects.isEmpty {
                CustomerHomeProjectsCarousel(projects: projects)
                    .premiumHomeAppear(appeared, delay: 0.16)
            }

            if let orders = dashboard.orders_recent, !orders.isEmpty {
                CustomerHomeRequestsPanel(orders: orders)
                    .premiumHomeAppear(appeared, delay: 0.2)
            }

            CustomerHomeFilesSpotlight(filesCount: dashboard.files_count ?? 0)
                .premiumHomeAppear(appeared, delay: 0.22)

            if let services = dashboard.services_featured, !services.isEmpty {
                CustomerHomeRecommendedServices(services: services)
                    .premiumHomeAppear(appeared, delay: 0.26)
            }

            if let portfolio = dashboard.portfolio, !portfolio.isEmpty {
                CustomerHomeFeaturedWorkStrip(items: portfolio)
                    .premiumHomeAppear(appeared, delay: 0.3)
            }

            CustomerHomeUtilityRow(dashboard: dashboard)
                .premiumHomeAppear(appeared, delay: 0.34)

            if let news = dashboard.news, !news.isEmpty {
                CustomerHomeNewsDigest(news: news)
                    .premiumHomeAppear(appeared, delay: 0.38)
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
            RoundedRectangle(cornerRadius: 6, style: .continuous)
                .fill(theme.panel)
                .frame(height: 14)
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 12) {
                    ForEach(0..<3, id: \.self) { _ in
                        RoundedRectangle(cornerRadius: 18, style: .continuous)
                            .fill(theme.panel)
                            .frame(width: 148, height: 96)
                    }
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
    @Environment(\.marketingTheme) private var theme
    let profileName: String
    @State private var pulse = false

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 8) {
                Circle()
                    .fill(theme.accent)
                    .frame(width: 8, height: 8)
                    .scaleEffect(pulse ? 1.15 : 0.85)
                    .opacity(pulse ? 1 : 0.55)
                    .animation(.easeInOut(duration: 1.4).repeatForever(autoreverses: true), value: pulse)
                    .onAppear { pulse = true }
                Text(String(localized: "Your workspace"))
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(theme.textSecondary)
                    .textCase(.uppercase)
                    .tracking(0.6)
            }
            Text(CustomerHomeGreeting.text(forName: profileName))
                .font(.system(size: 28, weight: .bold, design: .rounded))
                .foregroundStyle(theme.textPrimary)
                .fixedSize(horizontal: false, vertical: true)
            Text(String(localized: "Everything you need — projects, requests, files, and direct contact with our team."))
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .lineSpacing(3)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }
}

// MARK: - Live stats widgets

private struct CustomerHomeLiveStatsRow: View {
    @Environment(\.marketingTheme) private var theme
    let dashboard: CustomerDashboard

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 12) {
                statTile(
                    value: "\(dashboard.projects_active?.count ?? 0)",
                    label: String(localized: "Active projects"),
                    icon: "folder.fill",
                    tint: theme.accent
                )
                statTile(
                    value: "\(dashboard.orders_recent?.count ?? 0)",
                    label: String(localized: "Recent requests"),
                    icon: "doc.text",
                    tint: Color(red: 0.35, green: 0.62, blue: 1)
                )
                statTile(
                    value: "\(dashboard.unread_count ?? 0)",
                    label: String(localized: "Unread"),
                    icon: "bell.badge.fill",
                    tint: Color(red: 1, green: 0.45, blue: 0.35)
                )
                statTile(
                    value: "\(dashboard.files_count ?? 0)",
                    label: String(localized: "Files"),
                    icon: "doc.on.doc",
                    tint: Color(red: 0.55, green: 0.78, blue: 0.42)
                )
            }
            .padding(.horizontal, 2)
        }
    }

    private func statTile(value: String, label: String, icon: String, tint: Color) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                ZStack {
                    RoundedRectangle(cornerRadius: 10, style: .continuous)
                        .fill(tint.opacity(0.16))
                        .frame(width: 36, height: 36)
                    PAXIcon(icon, size: .card, emphasis: .primary, tint: tint)
                }
                Spacer(minLength: 0)
                Text(value)
                    .font(.title2.weight(.bold))
                    .foregroundStyle(theme.textPrimary)
            }
            Text(label)
                .font(.caption.weight(.medium))
                .foregroundStyle(theme.textSecondary)
                .lineLimit(2)
        }
        .padding(14)
        .frame(width: 148, alignment: .leading)
        .background(theme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .stroke(theme.border.opacity(0.35), lineWidth: 0.5)
        )
        .shadow(color: theme.shadowDark.opacity(0.12), radius: 10, y: 4)
    }
}

// MARK: - Quick actions

private struct CustomerHomeQuickActionsGrid: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    let dashboard: CustomerDashboard

    private let columns = [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]

    var body: some View {
        LazyVGrid(columns: columns, spacing: 12) {
            quickAction(
                title: String(localized: "Open Chat"),
                subtitle: String(localized: "Talk to our team"),
                icon: "message.fill",
                style: .accent
            ) {
                navigation.openChat(sessionID: dashboard.chat?.session_id)
            }
            quickAction(
                title: String(localized: "New Request"),
                subtitle: String(localized: "Start a project"),
                icon: "plus.circle",
                style: .neutral
            ) {
                navigation.openOrdersList()
            }
            quickAction(
                title: String(localized: "Projects"),
                subtitle: String(localized: "Track progress"),
                icon: "folder.fill",
                style: .neutral
            ) {
                navigation.openProjectsList()
            }
            quickAction(
                title: String(localized: "Files"),
                subtitle: String(localized: "Documents & invoices"),
                icon: "doc.on.doc",
                style: .neutral
            ) {
                navigation.openFiles()
            }
        }
    }

    private enum QuickActionStyle { case accent, neutral }

    private func quickAction(
        title: String,
        subtitle: String,
        icon: String,
        style: QuickActionStyle,
        action: @escaping () -> Void
    ) -> some View {
        Button {
            PAXHaptics.light()
            action()
        } label: {
            VStack(alignment: .leading, spacing: 16) {
                PAXIcon(icon, size: .display, emphasis: .primary)
                VStack(alignment: .leading, spacing: 4) {
                    Text(title)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(theme.textPrimary)
                    Text(subtitle)
                        .font(.caption)
                        .foregroundStyle(theme.textSecondary)
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                }
            }
            .padding(20)
            .frame(maxWidth: .infinity, minHeight: 132, alignment: .leading)
            .background(
                RoundedRectangle(cornerRadius: 20, style: .continuous)
                    .fill(theme.cardBackground)
                    .shadow(color: theme.shadowDark.opacity(style == .accent ? 0.14 : 0.08), radius: style == .accent ? 14 : 10, y: style == .accent ? 6 : 4)
            )
            .overlay(
                RoundedRectangle(cornerRadius: 20, style: .continuous)
                    .stroke(theme.border.opacity(style == .accent ? 0.45 : 0.28), lineWidth: 0.5)
            )
        }
        .buttonStyle(PremiumHomePressStyle())
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
            HStack(spacing: 8) {
                PAXIcon("message.fill", size: .card, emphasis: .primary, tint: theme.accent)
                Text(String(localized: "Latest conversation"))
                    .font(.headline)
                    .foregroundStyle(theme.textPrimary)
                Spacer()
            }
            Text(preview)
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .lineLimit(3)
                .fixedSize(horizontal: false, vertical: true)
            HStack {
                if messageCount > 0 {
                    Text(String(localized: "\(messageCount) messages"))
                        .font(.caption)
                        .foregroundStyle(theme.textSecondary)
                }
                Spacer()
                Button(String(localized: "Continue in Chat")) {
                    PAXHaptics.light()
                    navigation.openChat(sessionID: sessionID)
                }
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(theme.accentOnAccent)
                .padding(.horizontal, 16)
                .padding(.vertical, 10)
                .background(
                    LinearGradient(
                        colors: [Color(red: 0.83, green: 1, blue: 0.2), theme.accent],
                        startPoint: .leading,
                        endPoint: .trailing
                    )
                )
                .clipShape(Capsule())
            }
        }
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(
            LinearGradient(
                colors: [theme.panel, theme.accent.opacity(0.06)],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .clipShape(RoundedRectangle(cornerRadius: 22, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 22, style: .continuous)
                .stroke(theme.accent.opacity(0.25), lineWidth: 1)
        )
        .shadow(color: theme.shadowDark.opacity(0.18), radius: 16, y: 8)
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
            ZStack(alignment: .bottomTrailing) {
                Circle()
                    .stroke(theme.border.opacity(0.4), lineWidth: 4)
                    .frame(width: 52, height: 52)
                Circle()
                    .trim(from: 0, to: CGFloat(min(max(project.progress, 0), 100)) / 100)
                    .stroke(theme.accent, style: StrokeStyle(lineWidth: 4, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                    .frame(width: 52, height: 52)
                Text("\(project.progress)%")
                    .font(.caption2.weight(.bold))
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
        .frame(width: 200, alignment: .leading)
        .background(theme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 20, style: .continuous)
                .stroke(theme.border.opacity(0.3), lineWidth: 0.5)
        )
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
                ForEach(Array(orders.prefix(4).enumerated()), id: \.element.id) { index, order in
                    NavigationLink {
                        CustomerOrderDetailView(orderId: order.id)
                    } label: {
                        HStack(spacing: 14) {
                            ZStack {
                                Circle()
                                    .fill(theme.accent.opacity(0.14))
                                    .frame(width: 36, height: 36)
                                PAXIcon("doc.text", size: .inline, emphasis: .primary, tint: theme.accent)
                            }
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
            .background(theme.panel)
            .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 20, style: .continuous)
                    .stroke(theme.border.opacity(0.3), lineWidth: 0.5)
            )
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
                ZStack {
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [Color(red: 0.55, green: 0.78, blue: 0.42).opacity(0.2), theme.panel],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                        .frame(width: 52, height: 52)
                    PAXIcon("doc.on.doc", size: .action, emphasis: .primary, tint: Color(red: 0.55, green: 0.78, blue: 0.42))
                }
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Files & invoices"))
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
            .background(theme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 20, style: .continuous)
                    .stroke(theme.border.opacity(0.3), lineWidth: 0.5)
            )
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
        .background(theme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 20, style: .continuous)
                .stroke(theme.border.opacity(0.3), lineWidth: 0.5)
        )
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

// MARK: - Utility row

private struct CustomerHomeUtilityRow: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    let dashboard: CustomerDashboard

    var body: some View {
        HStack(spacing: 12) {
            utilityChip(
                title: String(localized: "Notifications"),
                icon: "bell.badge.fill",
                badge: dashboard.unread_count
            ) {
                navigation.openNotifications()
            }
            utilityChip(
                title: String(localized: "Services"),
                icon: "square.grid.2x2.fill",
                badge: nil
            ) {
                navigation.selectedTab = .services
            }
        }
    }

    private func utilityChip(title: String, icon: String, badge: Int?, action: @escaping () -> Void) -> some View {
        Button {
            PAXHaptics.light()
            action()
        } label: {
            HStack(spacing: 10) {
                PAXIcon(icon, size: .row, emphasis: .secondary)
                Text(title)
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(theme.textPrimary)
                if let badge, badge > 0 {
                    Text("\(badge)")
                        .font(.caption2.weight(.bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(Color.red)
                        .clipShape(Capsule())
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .frame(maxWidth: .infinity)
            .background(theme.panel)
            .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .stroke(theme.border.opacity(0.3), lineWidth: 0.5)
            )
        }
        .buttonStyle(PremiumHomePressStyle())
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
                    .background(theme.panel)
                    .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
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
            HStack(spacing: 10) {
                PAXIcon("sparkles", size: .hero, emphasis: .primary, tint: theme.accent)
                Text(String(localized: "Your digital workspace"))
                    .font(.title2.weight(.bold))
                    .foregroundStyle(theme.textPrimary)
            }
            Text(String(localized: "Sign in to track projects, submit requests, chat with our team, and access your files — all in one premium experience."))
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .lineSpacing(4)
            HStack(spacing: 12) {
                guestFeatureChip(icon: "folder.fill", label: String(localized: "Projects"))
                guestFeatureChip(icon: "message.fill", label: String(localized: "Chat"))
                guestFeatureChip(icon: "doc.on.doc", label: String(localized: "Files"))
            }
            Button(String(localized: "Sign in to your account")) {
                PAXHaptics.light()
                navigation.selectedTab = .account
            }
            .buttonStyle(CustomerCalmAccentButtonStyle())
        }
        .padding(24)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(
            LinearGradient(
                colors: [theme.panel, theme.accent.opacity(0.05)],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .clipShape(RoundedRectangle(cornerRadius: 22, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 22, style: .continuous)
                .stroke(
                    LinearGradient(
                        colors: [theme.accent.opacity(0.5), theme.accent.opacity(0.1)],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1
                )
        )
        .padding(.horizontal, CustomerResponsiveLayout.screenPadding)
        .padding(.vertical, 24)
        .premiumHomeAppear(appeared, delay: 0)
        .onAppear {
            withAnimation(.spring(response: 0.55, dampingFraction: 0.84)) {
                appeared = true
            }
        }
    }

    private func guestFeatureChip(icon: String, label: String) -> some View {
        VStack(spacing: 6) {
            ZStack {
                Circle()
                    .fill(theme.accent.opacity(0.12))
                    .frame(width: 40, height: 40)
                PAXIcon(icon, size: .card, emphasis: .primary, tint: theme.accent)
            }
            Text(label)
                .font(.caption2.weight(.medium))
                .foregroundStyle(theme.textSecondary)
        }
        .frame(maxWidth: .infinity)
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
