import SwiftUI

// MARK: - Discover (site-aligned native browse)

struct CustomerDiscoverView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var navigation: CustomerContentNavigation?
    @State private var services: CustomerServicesResponse?
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        NavigationStack {
            ScrollView {
                if isLoading && navigation == nil {
                    CustomerNativePageSkeleton()
                        .padding(.top, 8)
                } else if let error {
                    PAXContentUnavailableView(
                        String(localized: "Content unavailable"),
                        systemImage: "exclamationmark.triangle",
                        description: Text(error)
                    )
                    .padding(.top, 32)
                } else {
                    LazyVStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                        discoverHero

                        if let featured = services?.services.filter(\.featured), !featured.isEmpty {
                            CustomerPortalCard {
                                VStack(alignment: .leading, spacing: 12) {
                                    CustomerPortalSectionHeader(title: String(localized: "Featured"))
                                    ScrollView(.horizontal, showsIndicators: false) {
                                        HStack(spacing: 12) {
                                            ForEach(featured) { service in
                                                NavigationLink {
                                                    CustomerServiceDetailView(slug: service.slug)
                                                } label: {
                                                    CustomerDiscoverFeaturedCard(service: service)
                                                }
                                                .buttonStyle(.plain)
                                            }
                                        }
                                    }
                                }
                            }
                            .padding(.horizontal)
                        }

                        if let sections = navigation?.sections {
                            ForEach(sections) { section in
                                CustomerPortalCard {
                                    VStack(alignment: .leading, spacing: 12) {
                                        CustomerPortalSectionHeader(title: section.title)
                                        ForEach(section.items) { item in
                                            CustomerDiscoverMenuRow(item: item, depth: 0)
                                        }
                                    }
                                }
                                .padding(.horizontal)
                            }
                        }

                        NavigationLink {
                            CustomerPortfolioListView()
                        } label: {
                            CustomerPortalCard {
                                HStack {
                                    VStack(alignment: .leading, spacing: 6) {
                                        Text(String(localized: "Portfolio"))
                                            .font(.headline)
                                        Text(String(localized: "Explore our latest work."))
                                            .font(.subheadline)
                                            .foregroundStyle(PAXTheme.textSecondary)
                                    }
                                    Spacer()
                                    PAXIcon("chevron.right", size: .inline, emphasis: .secondary)
                                }
                            }
                        }
                        .buttonStyle(.plain)
                        .padding(.horizontal)
                    }
                    .padding(.vertical)
                }
            }
            .background(PAXBackground())
            .navigationTitle(String(localized: "Discover"))
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    NavigationLink {
                        CustomerServicesCatalogView()
                    } label: {
                        PAXLabel(String(localized: "All services"), icon: "square.grid.2x2")
                    }
                }
            }
            .refreshable { await load(force: true) }
            .task { await load(force: false) }
        }
    }

    private var discoverHero: some View {
        CustomerPortalCard {
            VStack(alignment: .leading, spacing: 8) {
                Text(String(localized: "PAXDesign"))
                    .font(.title.weight(.bold))
                Text(String(localized: "Professional digital services — native, fast, and always up to date from our studio."))
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .padding(.horizontal)
    }

    private func load(force: Bool) async {
        if navigation == nil || force { isLoading = true }
        error = nil
        do {
            async let navTask = api.fetchContentNavigation()
            async let servicesTask = api.fetchServices()
            navigation = try await navTask
            services = try await servicesTask
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct CustomerDiscoverFeaturedCard: View {
    let service: CustomerServicesResponse.Service

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            CustomerServiceIconView(iconKey: service.icon_key ?? service.slug, size: 44)
            Text(service.name)
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(PAXTheme.textPrimary)
                .lineLimit(2)
                .multilineTextAlignment(.leading)
        }
        .frame(width: 140, alignment: .leading)
        .padding(12)
        .background(PAXTheme.accentSoft)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
    }
}

private struct CustomerDiscoverMenuRow: View {
    let item: CustomerContentNavigation.MenuItem
    let depth: Int

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            NavigationLink {
                CustomerContentDestinationView(item: item)
            } label: {
                HStack(spacing: 12) {
                    PAXIcon(iconName, size: .row, tint: PAXTheme.accent)
                        .frame(width: 28)
                    Text(item.title)
                        .font(depth == 0 ? .headline : .subheadline)
                        .foregroundStyle(PAXTheme.textPrimary)
                    Spacer()
                    PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                }
                .padding(.leading, CGFloat(depth) * 12)
            }
            .buttonStyle(.plain)

            if let children = item.children, !children.isEmpty {
                ForEach(children) { child in
                    CustomerDiscoverMenuRow(item: child, depth: depth + 1)
                }
            }
        }
    }

    private var iconName: String {
        switch item.type {
        case "service": return "sparkles"
        case "portfolio": return "photo.on.rectangle.angled"
        case "page": return "doc.text"
        default: return "chevron.forward"
        }
    }
}

struct CustomerContentDestinationView: View {
    let item: CustomerContentNavigation.MenuItem

    var body: some View {
        Group {
            switch item.type {
            case "service":
                CustomerServiceDetailView(slug: item.slug)
            case "portfolio":
                CustomerPortfolioDetailView(slug: item.slug)
            default:
                CustomerNativePageView(slug: item.slug, title: item.title)
            }
        }
    }
}

// MARK: - Native WordPress page

struct CustomerNativePageView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    let title: String
    @State private var page: CustomerContentPage?
    @State private var error: String?

    var body: some View {
        ScrollView {
            if let page {
                VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                    if let imageURL = page.image_url, let url = URL(string: imageURL) {
                        AsyncImage(url: url) { phase in
                            if case .success(let image) = phase {
                                image.resizable().scaledToFill()
                            } else {
                                Rectangle().fill(PAXTheme.accentSoft)
                            }
                        }
                        .frame(height: 220)
                        .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))
                    }

                    CustomerPortalCard {
                        VStack(alignment: .leading, spacing: 12) {
                            Text(page.title).font(.title2.weight(.bold))
                            if let excerpt = page.excerpt, !excerpt.isEmpty {
                                Text(excerpt)
                                    .font(.subheadline)
                                    .foregroundStyle(PAXTheme.textSecondary)
                            }
                        }
                    }

                    if let blocks = page.blocks, !blocks.isEmpty {
                        CustomerNativeContentBlocksView(blocks: blocks)
                    } else if let body = page.body_text, !body.isEmpty {
                        CustomerPortalCard {
                            Text(body)
                                .font(.body)
                                .foregroundStyle(PAXTheme.textPrimary)
                        }
                    }

                    if let gallery = page.gallery, !gallery.isEmpty {
                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 12) {
                                CustomerPortalSectionHeader(title: String(localized: "Gallery"))
                                ScrollView(.horizontal, showsIndicators: false) {
                                    HStack(spacing: 12) {
                                        ForEach(gallery, id: \.self) { urlString in
                                            if let url = URL(string: urlString) {
                                                AsyncImage(url: url) { phase in
                                                    if case .success(let image) = phase {
                                                        image.resizable().scaledToFill()
                                                    } else {
                                                        Color.gray.opacity(0.15)
                                                    }
                                                }
                                                .frame(width: 180, height: 120)
                                                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                .padding()
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load"), systemImage: "exclamationmark.triangle", description: Text(error))
                    .padding(.top, 40)
            } else {
                CustomerNativePageSkeleton()
                    .padding(.top, 8)
            }
        }
        .background(PAXBackground())
        .navigationTitle(page?.title ?? title)
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func load() async {
        do {
            page = try await api.fetchContentPage(slug: slug)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

// MARK: - Services catalog (rich list)

struct CustomerServicesCatalogView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var response: CustomerServicesResponse?
    @State private var search = ""
    @State private var selectedCategory = ""
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        Group {
            if isLoading && response == nil {
                CustomerServicesCatalogSkeleton()
            } else if let error {
                PAXContentUnavailableView(String(localized: "Services unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
            } else if let response {
                if filteredServices(response.services).isEmpty {
                    PAXContentUnavailableView(
                        String(localized: "No services available"),
                        systemImage: "square.grid.2x2",
                        description: Text(String(localized: "Services from our website will appear here automatically once they are published."))
                    )
                } else {
                    ScrollView {
                        LazyVStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                            CustomerPortalCard {
                                VStack(alignment: .leading, spacing: 8) {
                                    Text(String(localized: "Services"))
                                        .font(.title2.weight(.bold))
                                    Text(String(localized: "Every service from our studio — images, details, and native ordering."))
                                        .font(.subheadline)
                                        .foregroundStyle(PAXTheme.textSecondary)
                                }
                            }
                            .padding(.horizontal)

                            if !response.categories.isEmpty {
                                ScrollView(.horizontal, showsIndicators: false) {
                                    HStack(spacing: 10) {
                                        categoryChip("", title: String(localized: "All"))
                                        ForEach(response.categories) { category in
                                            categoryChip(category.slug, title: category.name)
                                        }
                                    }
                                    .padding(.horizontal)
                                }
                            }
                            ForEach(filteredServices(response.services)) { service in
                                NavigationLink {
                                    CustomerServiceDetailView(slug: service.slug)
                                } label: {
                                    CustomerServiceCard(service: service)
                                }
                                .buttonStyle(.plain)
                            }
                        }
                        .padding(.vertical)
                    }
                }
            }
        }
        .background(PAXBackground())
        .navigationTitle(String(localized: "Services"))
        .searchable(text: $search, prompt: String(localized: "Search services"))
        .task(id: "\(search)|\(selectedCategory)") { await load(force: false) }
        .refreshable { await load(force: true) }
    }

    private func categoryChip(_ slug: String, title: String) -> some View {
        Button(title) { selectedCategory = slug }
            .font(.subheadline.weight(.medium))
            .padding(.horizontal, 14)
            .padding(.vertical, 8)
            .background(selectedCategory == slug ? PAXTheme.accent : PAXTheme.accentSoft)
            .foregroundStyle(selectedCategory == slug ? Color.white : PAXTheme.textPrimary)
            .clipShape(Capsule())
    }

    private func filteredServices(_ services: [CustomerServicesResponse.Service]) -> [CustomerServicesResponse.Service] {
        var list = services
        if !selectedCategory.isEmpty {
            list = list.filter { $0.category == selectedCategory }
        }
        let query = search.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        guard !query.isEmpty else { return list }
        return list.filter {
            $0.name.lowercased().contains(query) || $0.description.lowercased().contains(query)
        }
    }

    private func load(force: Bool) async {
        if response == nil || force { isLoading = true }
        error = nil
        do {
            response = try await api.fetchServices(
                search: search.isEmpty ? nil : search,
                category: selectedCategory.isEmpty ? nil : selectedCategory
            )
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct CustomerServiceCard: View {
    let service: CustomerServicesResponse.Service

    var body: some View {
        CustomerPortalCard {
            HStack(alignment: .top, spacing: 14) {
                if let imageURL = service.image_url, let url = URL(string: imageURL) {
                    AsyncImage(url: url) { phase in
                        switch phase {
                        case .success(let image):
                            image.resizable().scaledToFill()
                        default:
                            CustomerServiceIconView(iconKey: service.icon_key ?? service.slug)
                        }
                    }
                    .frame(width: 56, height: 56)
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                } else {
                    CustomerServiceIconView(iconKey: service.icon_key ?? service.slug, size: 56)
                }
                VStack(alignment: .leading, spacing: 6) {
                    HStack {
                        Text(service.name)
                            .font(.headline)
                            .foregroundStyle(PAXTheme.textPrimary)
                        if service.featured {
                            Text(String(localized: "Popular"))
                                .font(.caption2.weight(.semibold))
                                .padding(.horizontal, 8)
                                .padding(.vertical, 3)
                                .background(PAXTheme.accentSoft)
                                .clipShape(Capsule())
                        }
                    }
                    Text(service.description)
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(3)
                        .multilineTextAlignment(.leading)
                }
            }
        }
        .padding(.horizontal)
    }
}
