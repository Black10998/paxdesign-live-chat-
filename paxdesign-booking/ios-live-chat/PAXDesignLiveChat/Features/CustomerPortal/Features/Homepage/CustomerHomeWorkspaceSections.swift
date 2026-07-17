import SwiftUI

/// Data-driven premium workspace cards shown below the homepage hero for signed-in customers.
struct CustomerHomeWorkspaceSections: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme

    let dashboard: CustomerDashboard
    var profileName: String

    var body: some View {
        VStack(alignment: .leading, spacing: 24) {
            welcomeCard
            quickActionsRow
            if let projects = dashboard.projects_active, !projects.isEmpty {
                projectsSection(projects)
            }
            if let preview = dashboard.chat?.last_preview, !preview.isEmpty {
                messagesSection(preview: preview)
            }
            if let orders = dashboard.orders_recent, !orders.isEmpty {
                requestsSection(orders)
            }
            filesSection
            if let unread = dashboard.unread_count, unread > 0 {
                notificationsSection(unread)
            }
            if let services = dashboard.services_featured, !services.isEmpty {
                servicesSection(services)
            }
            if let portfolio = dashboard.portfolio, !portfolio.isEmpty {
                portfolioSection(portfolio)
            }
            if let news = dashboard.news, !news.isEmpty {
                newsSection(news)
            }
        }
        .padding(.horizontal, CustomerResponsiveLayout.screenPadding)
        .padding(.vertical, 32)
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private var welcomeCard: some View {
        CustomerCalmShellCard {
            VStack(alignment: .leading, spacing: 10) {
                Text(greeting)
                    .font(.title2.weight(.bold))
                    .foregroundStyle(theme.textPrimary)
                    .fixedSize(horizontal: false, vertical: true)
                    .frame(maxWidth: .infinity, alignment: .leading)
                Text(String(localized: "Your projects, requests, and conversations — all in one place."))
                    .font(.body)
                    .foregroundStyle(theme.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
            .padding(22)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
    }

    private var quickActionsRow: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 12) {
                quickAction(String(localized: "New request"), systemImage: "plus.circle.fill") {
                    navigation.selectedTab = .services
                }
                quickAction(String(localized: "Chat"), systemImage: "message.fill") {
                    navigation.openChat(sessionID: dashboard.chat?.session_id)
                }
                quickAction(String(localized: "Projects"), systemImage: "folder.fill") {
                    navigation.openProjectsList()
                }
                quickAction(String(localized: "Files"), systemImage: "doc.fill") {
                    navigation.openFiles()
                }
            }
        }
    }

    private func quickAction(_ title: String, systemImage: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            VStack(spacing: 8) {
                Image(systemName: systemImage)
                    .font(.title3)
                    .foregroundStyle(theme.accent)
                Text(title)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(theme.textPrimary)
                    .multilineTextAlignment(.center)
                    .lineLimit(2)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(width: 88)
            .padding(.vertical, 14)
            .background(theme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .stroke(theme.border, lineWidth: 0.5)
            )
        }
        .buttonStyle(.plain)
    }

    private func projectsSection(_ projects: [CustomerDashboard.ProjectSummary]) -> some View {
        workspaceCard(title: String(localized: "Active projects"), systemImage: "folder.fill") {
            ForEach(projects.prefix(3), id: \.id) { project in
                NavigationLink {
                    CustomerProjectDetailView(projectId: project.id)
                } label: {
                    HStack(alignment: .center, spacing: 12) {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(project.title)
                                .font(.headline)
                                .foregroundStyle(theme.textPrimary)
                                .multilineTextAlignment(.leading)
                                .fixedSize(horizontal: false, vertical: true)
                                .frame(maxWidth: .infinity, alignment: .leading)
                            Text(project.status)
                                .font(.caption)
                                .foregroundStyle(theme.textSecondary)
                        }
                        Text("\(project.progress)%")
                            .font(.subheadline.weight(.semibold))
                            .foregroundStyle(theme.accent)
                    }
                }
                .buttonStyle(.plain)
                if project.id != projects.prefix(3).last?.id {
                    Divider()
                }
            }
        }
    }

    private func messagesSection(preview: String) -> some View {
        workspaceCard(title: String(localized: "Latest messages"), systemImage: "message.fill") {
            Text(preview)
                .font(.body)
                .foregroundStyle(theme.textSecondary)
                .lineLimit(3)
                .fixedSize(horizontal: false, vertical: true)
                .frame(maxWidth: .infinity, alignment: .leading)
            Button(String(localized: "Open chat")) {
                navigation.openChat(sessionID: dashboard.chat?.session_id)
            }
            .buttonStyle(CustomerCalmAccentButtonStyle())
        }
    }

    private func requestsSection(_ orders: [CustomerDashboard.OrderSummary]) -> some View {
        workspaceCard(title: String(localized: "Recent requests"), systemImage: "tray.fill") {
            ForEach(orders.prefix(3), id: \.id) { order in
                NavigationLink {
                    CustomerOrderDetailView(orderId: order.id)
                } label: {
                    HStack {
                        Text(order.service_label)
                            .font(.subheadline.weight(.medium))
                            .foregroundStyle(theme.textPrimary)
                            .multilineTextAlignment(.leading)
                            .fixedSize(horizontal: false, vertical: true)
                            .frame(maxWidth: .infinity, alignment: .leading)
                        Text(order.status)
                            .font(.caption)
                            .foregroundStyle(theme.textSecondary)
                    }
                }
                .buttonStyle(.plain)
            }
        }
    }

    private var filesSection: some View {
        workspaceCard(title: String(localized: "Files & invoices"), systemImage: "doc.text.fill") {
            if let count = dashboard.files_count, count > 0 {
                Text(String(localized: "\(count) files available"))
                    .font(.body)
                    .foregroundStyle(theme.textSecondary)
            } else {
                Text(String(localized: "Download shared documents, quotes, and invoices."))
                    .font(.body)
                    .foregroundStyle(theme.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
            Button(String(localized: "Open files")) {
                navigation.openFiles()
            }
            .buttonStyle(CustomerCalmAccentButtonStyle())
        }
    }

    private func notificationsSection(_ unread: Int) -> some View {
        workspaceCard(title: String(localized: "Notifications"), systemImage: "bell.badge.fill") {
            Text(String(localized: "\(unread) unread updates"))
                .font(.body)
                .foregroundStyle(theme.textSecondary)
            Button(String(localized: "View notifications")) {
                navigation.openNotifications()
            }
            .buttonStyle(CustomerCalmAccentButtonStyle())
        }
    }

    private func servicesSection(_ services: [CustomerServicesResponse.Service]) -> some View {
        workspaceCard(title: String(localized: "Recommended services"), systemImage: "sparkles") {
            ForEach(services.prefix(3)) { service in
                NavigationLink {
                    CustomerServiceDetailView(slug: service.slug)
                } label: {
                    HStack(spacing: 12) {
                        CustomerServiceIconView(iconKey: service.icon_key ?? service.slug, size: 40)
                        VStack(alignment: .leading, spacing: 4) {
                            Text(service.name)
                                .font(.headline)
                                .foregroundStyle(theme.textPrimary)
                                .multilineTextAlignment(.leading)
                                .fixedSize(horizontal: false, vertical: true)
                                .frame(maxWidth: .infinity, alignment: .leading)
                            Text(service.description)
                                .font(.caption)
                                .foregroundStyle(theme.textSecondary)
                                .lineLimit(2)
                                .fixedSize(horizontal: false, vertical: true)
                                .frame(maxWidth: .infinity, alignment: .leading)
                        }
                    }
                }
                .buttonStyle(.plain)
            }
        }
    }

    private func portfolioSection(_ items: [CustomerDashboard.PortfolioPreview]) -> some View {
        workspaceCard(title: String(localized: "Featured work"), systemImage: "photo.on.rectangle.angled") {
            ForEach(items.prefix(2)) { item in
                NavigationLink {
                    CustomerPortfolioDetailView(slug: item.slug)
                } label: {
                    HStack(spacing: 12) {
                        if let imageURL = item.image_url, let url = URL(string: imageURL) {
                            AsyncImage(url: url) { phase in
                                if case .success(let image) = phase {
                                    image.resizable().scaledToFill()
                                } else {
                                    theme.panel
                                }
                            }
                            .frame(width: 56, height: 56)
                            .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
                        }
                        VStack(alignment: .leading, spacing: 4) {
                            Text(item.title)
                                .font(.headline)
                                .foregroundStyle(theme.textPrimary)
                                .multilineTextAlignment(.leading)
                                .fixedSize(horizontal: false, vertical: true)
                                .frame(maxWidth: .infinity, alignment: .leading)
                            if let excerpt = item.excerpt, !excerpt.isEmpty {
                                Text(excerpt)
                                    .font(.caption)
                                    .foregroundStyle(theme.textSecondary)
                                    .lineLimit(2)
                                    .fixedSize(horizontal: false, vertical: true)
                                    .frame(maxWidth: .infinity, alignment: .leading)
                            }
                        }
                    }
                }
                .buttonStyle(.plain)
            }
        }
    }

    private func newsSection(_ news: [CustomerDashboard.NewsItem]) -> some View {
        workspaceCard(title: String(localized: "Latest news"), systemImage: "newspaper.fill") {
            ForEach(news.prefix(2), id: \.slug) { item in
                NavigationLink {
                    CustomerNewsDetailView(slug: item.slug)
                } label: {
                    VStack(alignment: .leading, spacing: 4) {
                        Text(item.title)
                            .font(.headline)
                            .foregroundStyle(theme.textPrimary)
                            .multilineTextAlignment(.leading)
                            .fixedSize(horizontal: false, vertical: true)
                            .frame(maxWidth: .infinity, alignment: .leading)
                        if let excerpt = item.excerpt, !excerpt.isEmpty {
                            Text(excerpt)
                                .font(.caption)
                                .foregroundStyle(theme.textSecondary)
                                .lineLimit(2)
                                .fixedSize(horizontal: false, vertical: true)
                                .frame(maxWidth: .infinity, alignment: .leading)
                        }
                    }
                }
                .buttonStyle(.plain)
            }
        }
    }

    private func workspaceCard<Content: View>(
        title: String,
        systemImage: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        CustomerCalmShellCard {
            VStack(alignment: .leading, spacing: 14) {
                Label(title, systemImage: systemImage)
                    .font(.headline)
                    .foregroundStyle(theme.textPrimary)
                    .frame(maxWidth: .infinity, alignment: .leading)
                content()
            }
            .padding(20)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
    }

    private var greeting: String {
        let name = profileName.trimmingCharacters(in: .whitespacesAndNewlines)
        if name.isEmpty {
            return String(localized: "Welcome back")
        }
        return String(localized: "Welcome back, \(name)")
    }
}
