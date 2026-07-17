import SwiftUI

struct CustomerPortfolioListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var response: CustomerPortfolioResponse?
    @State private var selectedCategory = ""
    @State private var error: String?
    @State private var isLoading = true

    private let columns = [
        GridItem(.flexible(), spacing: 12),
        GridItem(.flexible(), spacing: 12),
    ]

    var body: some View {
        Group {
            if isLoading && response == nil {
                ProgressView(String(localized: "Loading portfolio…"))
            } else if let error {
                PAXContentUnavailableView(String(localized: "Portfolio unavailable"), systemImage: "photo.on.rectangle", description: Text(error))
            } else if filteredItems.isEmpty {
                PAXContentUnavailableView(
                    String(localized: "No portfolio items yet"),
                    systemImage: "photo.on.rectangle.angled",
                    description: Text(String(localized: "Our latest work will appear here."))
                )
            } else {
                ScrollView {
                    if let categories = response?.categories, !categories.isEmpty {
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack(spacing: 8) {
                                categoryChip("", title: String(localized: "All"))
                                ForEach(categories, id: \.slug) { category in
                                    categoryChip(category.slug, title: category.name)
                                }
                            }
                            .padding(.horizontal)
                            .padding(.vertical, 8)
                        }
                    }
                    LazyVGrid(columns: columns, spacing: 12) {
                        ForEach(filteredItems) { item in
                            NavigationLink {
                                CustomerPortfolioDetailView(slug: item.slug)
                            } label: {
                                CustomerPortfolioCard(item: item)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding()
                }
            }
        }
        .background(PAXBackground())
        .navigationTitle(String(localized: "Portfolio"))
        .task(id: selectedCategory) { await load() }
        .refreshable { await load(force: true) }
    }

    private var filteredItems: [CustomerPortfolioItem] {
        guard let items = response?.items else { return [] }
        if selectedCategory.isEmpty { return items }
        return items
    }

    private func categoryChip(_ slug: String, title: String) -> some View {
        Button(title) { selectedCategory = slug }
            .font(.subheadline.weight(.medium))
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(selectedCategory == slug ? PAXTheme.accent : PAXTheme.surfaceElevated)
            .foregroundStyle(selectedCategory == slug ? Color.white : PAXTheme.textPrimary)
            .clipShape(Capsule())
    }

    private func load(force: Bool = false) async {
        if response == nil || force { isLoading = true }
        error = nil
        do {
            response = try await api.fetchPortfolio(category: selectedCategory.isEmpty ? nil : selectedCategory)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

struct CustomerPortfolioCard: View {
    let item: CustomerPortfolioItem

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            if let imageURL = item.image_url, let url = URL(string: imageURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        Rectangle().fill(PAXTheme.accentSoft)
                    }
                }
                .frame(height: 120)
                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            } else {
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .fill(PAXTheme.accentSoft)
                    .frame(height: 120)
                    .overlay {
                        Image(systemName: "photo")
                            .font(.title2)
                            .foregroundStyle(PAXTheme.accent)
                    }
            }
            Text(item.title)
                .font(.headline)
                .foregroundStyle(PAXTheme.textPrimary)
                .lineLimit(2)
            if let client = item.client, !client.isEmpty {
                Text(client)
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }
        }
        .padding(12)
        .background(PAXTheme.surfaceElevated)
        .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))
    }
}

struct CustomerPortfolioDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    @State private var item: CustomerPortfolioDetail?
    @State private var error: String?

    var body: some View {
        ScrollView {
            if let item {
                VStack(alignment: .leading, spacing: 16) {
                    if let imageURL = item.image_url, let url = URL(string: imageURL) {
                        AsyncImage(url: url) { phase in
                            if case .success(let image) = phase {
                                image.resizable().scaledToFill()
                            } else {
                                ProgressView()
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 220)
                        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                    }
                    Text(item.title).font(.title.bold())
                    if let client = item.client, !client.isEmpty {
                        Label(client, systemImage: "building.2")
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                    }
                    if let body = item.body, !body.isEmpty {
                        Text(body.replacingOccurrences(of: "<[^>]+>", with: "", options: .regularExpression))
                            .font(.body)
                    }
                    if let gallery = item.gallery, !gallery.isEmpty {
                        CustomerPortalSectionHeader(title: String(localized: "Gallery"))
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack(spacing: 12) {
                                ForEach(gallery, id: \.self) { urlString in
                                    if let url = URL(string: urlString) {
                                        AsyncImage(url: url) { phase in
                                            if case .success(let image) = phase {
                                                image.resizable().scaledToFill()
                                            } else {
                                                Color.gray.opacity(0.2)
                                            }
                                        }
                                        .frame(width: 180, height: 120)
                                        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                                    }
                                }
                            }
                        }
                    }
                    if let projectURL = item.project_url, let url = URL(string: projectURL) {
                        CustomerSafariLink(title: String(localized: "View live project"), url: url)
                    }
                }
                .padding()
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load project"), systemImage: "exclamationmark.triangle", description: Text(error))
            } else {
                ProgressView().padding(.top, 48)
            }
        }
        .background(PAXBackground())
        .navigationTitle(item?.title ?? String(localized: "Portfolio"))
        .task { await load() }
    }

    private func load() async {
        do {
            item = try await api.fetchPortfolioItem(slug: slug)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}
