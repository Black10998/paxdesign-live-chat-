import SwiftUI

struct CustomerPortfolioListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var response: CustomerPortfolioResponse?
    @State private var selectedCategory = ""
    @State private var error: String?
    @State private var isLoading = true

    private let columns = [
        GridItem(.flexible(), spacing: 14),
        GridItem(.flexible(), spacing: 14),
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
                    description: Text(String(localized: "Our latest work will appear here once published."))
                )
            } else {
                ScrollView {
                    LazyVStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 8) {
                                Text(String(localized: "Portfolio"))
                                    .font(.title2.weight(.bold))
                                Text(String(localized: "Selected projects from our studio — fully native, always synced from WordPress."))
                                    .font(.subheadline)
                                    .foregroundStyle(PAXTheme.textSecondary)
                            }
                        }
                        .padding(.horizontal)

                        if let categories = response?.categories, !categories.isEmpty {
                            ScrollView(.horizontal, showsIndicators: false) {
                                HStack(spacing: 8) {
                                    categoryChip("", title: String(localized: "All"))
                                    ForEach(categories, id: \.slug) { category in
                                        categoryChip(category.slug, title: category.name)
                                    }
                                }
                                .padding(.horizontal)
                            }
                        }

                        LazyVGrid(columns: columns, spacing: 14) {
                            ForEach(filteredItems) { item in
                                NavigationLink {
                                    CustomerPortfolioDetailView(slug: item.slug)
                                } label: {
                                    CustomerPortfolioCard(item: item)
                                }
                                .buttonStyle(.plain)
                            }
                        }
                        .padding(.horizontal)
                    }
                    .padding(.vertical)
                }
            }
        }
        .background(PAXBackground())
        .navigationTitle(String(localized: "Portfolio"))
        .task(id: selectedCategory) { await load() }
        .refreshable { await load(force: true) }
    }

    private var filteredItems: [CustomerPortfolioItem] {
        response?.items ?? []
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
        VStack(alignment: .leading, spacing: 0) {
            if let imageURL = item.image_url, let url = URL(string: imageURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        Rectangle().fill(PAXTheme.accentSoft)
                    }
                }
                .frame(height: 132)
                .clipped()
            } else {
                Rectangle()
                    .fill(PAXTheme.accentSoft)
                    .frame(height: 132)
                    .overlay {
                        Image(systemName: "photo.on.rectangle.angled")
                            .font(.title2)
                            .foregroundStyle(PAXTheme.accent)
                    }
            }

            VStack(alignment: .leading, spacing: 6) {
                Text(item.title)
                    .font(.headline)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .lineLimit(2)
                    .multilineTextAlignment(.leading)
                if let excerpt = item.excerpt, !excerpt.isEmpty {
                    Text(excerpt)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(2)
                } else if let client = item.client, !client.isEmpty {
                    Text(client)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }
            .padding(12)
        }
        .background(PAXTheme.surfaceElevated)
        .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))
        .shadow(color: .black.opacity(0.06), radius: 8, y: 4)
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
                VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                    if let imageURL = item.image_url, let url = URL(string: imageURL) {
                        AsyncImage(url: url) { phase in
                            if case .success(let image) = phase {
                                image.resizable().scaledToFill()
                            } else {
                                Rectangle().fill(PAXTheme.accentSoft)
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 240)
                        .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))
                    }

                    CustomerPortalCard {
                        VStack(alignment: .leading, spacing: 10) {
                            Text(item.title).font(.title2.weight(.bold))
                            if let client = item.client, !client.isEmpty {
                                Label(client, systemImage: "building.2")
                                    .font(.subheadline)
                                    .foregroundStyle(PAXTheme.textSecondary)
                            }
                            if let categories = item.categories, !categories.isEmpty {
                                ScrollView(.horizontal, showsIndicators: false) {
                                    HStack(spacing: 8) {
                                        ForEach(categories, id: \.self) { category in
                                            Text(category)
                                                .font(.caption.weight(.semibold))
                                                .padding(.horizontal, 10)
                                                .padding(.vertical, 5)
                                                .background(PAXTheme.accentSoft)
                                                .clipShape(Capsule())
                                        }
                                    }
                                }
                            }
                            if let body = item.body, !body.isEmpty {
                                Text(body.replacingOccurrences(of: "<[^>]+>", with: "", options: .regularExpression))
                                    .font(.body)
                                    .foregroundStyle(PAXTheme.textPrimary)
                            } else if let excerpt = item.excerpt, !excerpt.isEmpty {
                                Text(excerpt)
                                    .font(.body)
                                    .foregroundStyle(PAXTheme.textPrimary)
                            }
                        }
                    }

                    if let blocks = item.blocks, !blocks.isEmpty {
                        CustomerNativeContentBlocksView(blocks: blocks)
                    }

                    if let gallery = item.gallery, !gallery.isEmpty {
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
                                                .frame(width: 200, height: 140)
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
                PAXContentUnavailableView(String(localized: "Unable to load project"), systemImage: "exclamationmark.triangle", description: Text(error))
                    .padding(.top, 40)
            } else {
                ProgressView().padding(.top, 48)
            }
        }
        .background(PAXBackground())
        .navigationTitle(item?.title ?? String(localized: "Portfolio"))
        .navigationBarTitleDisplayMode(.inline)
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
