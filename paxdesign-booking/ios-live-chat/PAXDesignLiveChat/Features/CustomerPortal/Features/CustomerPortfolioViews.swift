import SwiftUI

// MARK: - List

struct CustomerPortfolioListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @Environment(\.marketingTheme) private var theme
    @State private var response: CustomerPortfolioResponse?
    @State private var selectedCategory = ""
    @State private var error: String?
    @State private var isLoading = true

    private var columns: [GridItem] {
        [GridItem(.flexible(), spacing: 16), GridItem(.flexible(), spacing: 16)]
    }

    var body: some View {
        Group {
            if isLoading && response == nil {
                CustomerPortfolioListSkeleton()
            } else if let error {
                CustomerPremiumEmptyState(
                    title: String(localized: "Portfolio unavailable"),
                    message: error,
                    systemImage: "photo.on.rectangle",
                    actionTitle: String(localized: "Try again")
                ) { Task { await load(force: true) } }
            } else if filteredItems.isEmpty {
                CustomerPremiumEmptyState(
                    title: String(localized: "No portfolio items yet"),
                    message: String(localized: "Our latest work will appear here once published."),
                    systemImage: "photo.on.rectangle.angled"
                )
            } else {
                ScrollView {
                    LazyVStack(alignment: .leading, spacing: 28) {
                        portfolioHero
                        if let categories = response?.categories, !categories.isEmpty {
                            categoryStrip(categories)
                        }
                        LazyVGrid(columns: columns, spacing: 16) {
                            ForEach(Array(filteredItems.enumerated()), id: \.element.id) { index, item in
                                NavigationLink {
                                    CustomerPortfolioDetailView(slug: item.slug)
                                } label: {
                                    CustomerPortfolioShowcaseCard(item: item, index: index)
                                }
                                .buttonStyle(CustomerPressableCardStyle())
                            }
                        }
                    }
                    .padding(.horizontal, 20)
                    .padding(.vertical, 12)
                }
            }
        }
        .background(PAXBackground())
        .navigationTitle(String(localized: "Portfolio"))
        .navigationBarTitleDisplayMode(.large)
        .task(id: selectedCategory) { await load() }
        .refreshable { await load(force: true) }
    }

    private var portfolioHero: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(String(localized: "Selected work"))
                .font(.largeTitle.weight(.bold))
                .foregroundStyle(PAXTheme.textPrimary)
            Text(String(localized: "Premium digital products crafted with clarity, performance, and long-term scalability."))
                .font(.body)
                .foregroundStyle(PAXTheme.textSecondary)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
    }

    private func categoryStrip(_ categories: [CustomerPortfolioResponse.Category]) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 10) {
                categoryChip("", title: String(localized: "All"))
                ForEach(categories, id: \.slug) { category in
                    categoryChip(category.slug, title: category.name)
                }
            }
        }
    }

    private func categoryChip(_ slug: String, title: String) -> some View {
        Button(title) {
            withAnimation(PAXTheme.quickSpring) { selectedCategory = slug }
            PAXHaptics.light()
        }
        .font(.subheadline.weight(.semibold))
        .padding(.horizontal, 14)
        .padding(.vertical, 9)
        .background(selectedCategory == slug ? PAXTheme.accent : PAXTheme.surfaceElevated)
        .foregroundStyle(selectedCategory == slug ? Color.white : PAXTheme.textPrimary)
        .clipShape(Capsule())
        .accessibilityAddTraits(selectedCategory == slug ? .isSelected : [])
    }

    private var filteredItems: [CustomerPortfolioItem] {
        response?.items ?? []
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

// MARK: - Cards

struct CustomerPortfolioShowcaseCard: View {
    let item: CustomerPortfolioItem
    var index: Int = 0

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            portfolioImage
            VStack(alignment: .leading, spacing: 6) {
                Text(item.displayTitle)
                    .font(.headline)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .lineLimit(2)
                    .multilineTextAlignment(.leading)
                if !item.displayExcerpt.isEmpty {
                    Text(item.displayExcerpt)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(2)
                } else if let client = item.client, !client.isEmpty {
                    Text(client)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            .padding(14)
        }
        .background(PAXTheme.surfaceElevated)
        .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 20, style: .continuous)
                .stroke(PAXTheme.border.opacity(0.25), lineWidth: 0.5)
        )
        .shadow(color: .black.opacity(0.08), radius: 12, y: 6)
        .accessibilityElement(children: .combine)
        .accessibilityLabel(item.displayTitle)
        .accessibilityHint(String(localized: "Opens project details"))
    }

    @ViewBuilder
    private var portfolioImage: some View {
        if let imageURL = item.image_url, let url = URL(string: imageURL) {
            AsyncImage(url: url) { phase in
                switch phase {
                case .success(let image):
                    image.resizable().scaledToFill()
                default:
                    SkeletonBlock(height: 150, cornerRadius: 0)
                }
            }
            .frame(height: 150)
            .clipped()
        } else {
            Rectangle()
                .fill(PAXTheme.accentSoft)
                .frame(height: 150)
                .overlay {
                    Image(systemName: "photo.on.rectangle.angled")
                        .font(.title2)
                        .foregroundStyle(PAXTheme.accent)
                }
        }
    }
}

struct CustomerPortfolioCard: View {
    let item: CustomerPortfolioItem

    var body: some View {
        CustomerPortfolioShowcaseCard(item: item)
    }
}

// MARK: - Detail

struct CustomerPortfolioDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    @State private var item: CustomerPortfolioDetail?
    @State private var error: String?
    @State private var selectedGalleryIndex: Int?
    @State private var heroVisible = true

    var body: some View {
        ScrollView {
            if let item {
                VStack(spacing: 0) {
                    heroSection(item)
                    detailContent(item)
                }
            } else if let error {
                CustomerPremiumEmptyState(
                    title: String(localized: "Unable to load project"),
                    message: error,
                    systemImage: "exclamationmark.triangle",
                    actionTitle: String(localized: "Try again")
                ) { Task { await load() } }
                .padding(.top, 40)
            } else {
                CustomerPortfolioDetailSkeleton()
                    .padding(.top, 8)
            }
        }
        .background(PAXBackground())
        .navigationTitle(item?.displayTitle ?? String(localized: "Portfolio"))
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
        .sheet(item: Binding(
            get: { selectedGalleryIndex.map { GallerySelection(index: $0) } },
            set: { selectedGalleryIndex = $0?.index }
        )) { selection in
            if let item {
                CustomerPortfolioGalleryViewer(
                    images: item.showcaseGallery,
                    initialIndex: selection.index
                )
            }
        }
    }

    @ViewBuilder
    private func heroSection(_ item: CustomerPortfolioDetail) -> some View {
        ZStack(alignment: .bottomLeading) {
            if let imageURL = item.image_url, let url = URL(string: imageURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        SkeletonBlock(height: 360, cornerRadius: 0)
                    }
                }
                .frame(maxWidth: .infinity)
                .frame(height: 360)
                .clipped()
            } else {
                Rectangle()
                    .fill(PAXTheme.accentSoft)
                    .frame(height: 280)
            }

            LinearGradient(
                colors: [.clear, .black.opacity(0.75)],
                startPoint: .center,
                endPoint: .bottom
            )
            .frame(height: 360)

            VStack(alignment: .leading, spacing: 8) {
                if let categories = item.categories, !categories.isEmpty {
                    Text(categories.joined(separator: " · "))
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(.white.opacity(0.85))
                        .textCase(.uppercase)
                }
                Text(item.displayTitle)
                    .font(.system(size: 30, weight: .bold, design: .rounded))
                    .foregroundStyle(.white)
                    .multilineTextAlignment(.leading)
                if !item.displaySubtitle.isEmpty {
                    Text(item.displaySubtitle)
                        .font(.subheadline)
                        .foregroundStyle(.white.opacity(0.88))
                        .lineLimit(3)
                }
            }
            .padding(24)
        }
        .accessibilityElement(children: .combine)
    }

    @ViewBuilder
    private func detailContent(_ item: CustomerPortfolioDetail) -> some View {
        VStack(alignment: .leading, spacing: 28) {
            if let structured = item.structuredDetail {
                if let stats = structured.stats, !stats.isEmpty {
                    statsSection(stats)
                }
                if let metadata = structured.metadata, !metadata.isEmpty {
                    metadataSection(metadata)
                }
                if let sections = structured.sections, !sections.isEmpty {
                    narrativeSections(sections)
                } else if let paragraphs = structured.paragraphs, !paragraphs.isEmpty {
                    ForEach(Array(paragraphs.enumerated()), id: \.offset) { _, paragraph in
                        Text(CustomerPortfolioTextSanitizer.clean(paragraph))
                            .font(.body)
                            .foregroundStyle(PAXTheme.textPrimary)
                            .lineSpacing(5)
                    }
                }
                if let services = structured.services, !services.isEmpty {
                    servicesSection(services)
                }
                if let tags = structured.tags, !tags.isEmpty {
                    tagsSection(tags)
                }
            } else {
                legacyBody(item)
            }

            if !item.showcaseGallery.isEmpty {
                gallerySection(item.showcaseGallery)
            }

            footerActions(item)
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 28)
    }

    private func statsSection(_ stats: [CustomerPortfolioStructuredDetail.Stat]) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 14) {
                ForEach(stats) { stat in
                    VStack(alignment: .leading, spacing: 8) {
                        Text(stat.value)
                            .font(.system(size: 34, weight: .bold, design: .rounded))
                            .foregroundStyle(PAXTheme.accent)
                        Text(stat.label)
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    .padding(18)
                    .frame(width: 220, alignment: .leading)
                    .background(PAXTheme.surfaceElevated)
                    .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                }
            }
        }
    }

    private func metadataSection(_ rows: [CustomerPortfolioStructuredDetail.Metadata]) -> some View {
        LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
            ForEach(rows) { row in
                VStack(alignment: .leading, spacing: 6) {
                    Text(row.label)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(PAXTheme.textSecondary)
                        .textCase(.uppercase)
                    if let link = row.link, !link.isEmpty, let url = URL(string: link) {
                        Link(row.value, destination: url)
                            .font(.subheadline.weight(.medium))
                    } else {
                        Text(row.value)
                            .font(.subheadline.weight(.medium))
                            .foregroundStyle(PAXTheme.textPrimary)
                    }
                }
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(PAXTheme.surfaceElevated)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            }
        }
    }

    private func narrativeSections(_ sections: [CustomerPortfolioStructuredDetail.Section]) -> some View {
        VStack(alignment: .leading, spacing: 24) {
            ForEach(sections) { section in
                VStack(alignment: .leading, spacing: 10) {
                    Text(section.title)
                        .font(.title3.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(CustomerPortfolioTextSanitizer.clean(section.body))
                        .font(.body)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineSpacing(6)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
        }
    }

    private func servicesSection(_ services: [String]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            CustomerPortalSectionHeader(title: String(localized: "Deliverables"))
            CustomerFlowLayout(spacing: 8) {
                ForEach(services, id: \.self) { service in
                    Text(service)
                        .font(.subheadline.weight(.medium))
                        .padding(.horizontal, 12)
                        .padding(.vertical, 8)
                        .background(PAXTheme.accentSoft)
                        .foregroundStyle(PAXTheme.accent)
                        .clipShape(Capsule())
                }
            }
        }
    }

    private func tagsSection(_ tags: [String]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            CustomerPortalSectionHeader(title: String(localized: "Technologies"))
            CustomerFlowLayout(spacing: 8) {
                ForEach(tags, id: \.self) { tag in
                    Text(tag)
                        .font(.caption.weight(.semibold))
                        .padding(.horizontal, 10)
                        .padding(.vertical, 6)
                        .background(PAXTheme.surfaceElevated)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .clipShape(Capsule())
                }
            }
        }
    }

    @ViewBuilder
    private func legacyBody(_ item: CustomerPortfolioDetail) -> some View {
        if let client = item.client, !client.isEmpty {
            Label(client, systemImage: "building.2")
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
        }
        if let excerpt = item.excerpt, !item.displaySubtitle.isEmpty {
            Text(item.displaySubtitle)
                .font(.body)
                .foregroundStyle(PAXTheme.textPrimary)
                .lineSpacing(5)
        }
    }

    private func gallerySection(_ images: [CustomerPortfolioStructuredDetail.GalleryImage]) -> some View {
        VStack(alignment: .leading, spacing: 14) {
            CustomerPortalSectionHeader(title: String(localized: "Gallery"))
            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                ForEach(Array(images.enumerated()), id: \.element.id) { index, image in
                    Button {
                        selectedGalleryIndex = index
                        PAXHaptics.light()
                    } label: {
                        AsyncImage(url: URL(string: image.url)) { phase in
                            if case .success(let img) = phase {
                                img.resizable().scaledToFill()
                            } else {
                                SkeletonBlock(height: 140, cornerRadius: 16)
                            }
                        }
                        .frame(height: 140)
                        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                    }
                    .buttonStyle(.plain)
                    .accessibilityLabel(String(localized: "Project image \(index + 1)"))
                }
            }
        }
    }

    @ViewBuilder
    private func footerActions(_ item: CustomerPortfolioDetail) -> some View {
        VStack(spacing: 12) {
            if let urlString = item.structuredDetail?.website_url ?? item.project_url,
               !urlString.isEmpty, let url = URL(string: urlString) {
                CustomerSafariLink(
                    title: String(localized: "View on website"),
                    url: url,
                    style: .filled
                )
            }
            NavigationLink {
                CustomerCreateOrderView(
                    prefilledTitle: item.displayTitle,
                    prefilledDescription: CustomerCreateOrderView.templateDescription(
                        title: item.displayTitle,
                        features: item.structuredDetail?.services ?? []
                    )
                )
            } label: {
                Label(String(localized: "Start a similar project"), systemImage: "plus.circle.fill")
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
        }
        .padding(.top, 8)
    }

    private func load() async {
        do {
            item = try await api.fetchPortfolioItem(slug: slug)
            error = nil
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

// MARK: - Gallery viewer

private struct GallerySelection: Identifiable {
    let index: Int
    var id: Int { index }
}

struct CustomerPortfolioGalleryViewer: View {
    let images: [CustomerPortfolioStructuredDetail.GalleryImage]
    let initialIndex: Int
    @Environment(\.dismiss) private var dismiss
    @State private var index: Int

    init(images: [CustomerPortfolioStructuredDetail.GalleryImage], initialIndex: Int) {
        self.images = images
        self.initialIndex = initialIndex
        _index = State(initialValue: initialIndex)
    }

    var body: some View {
        NavigationStack {
            TabView(selection: $index) {
                ForEach(Array(images.enumerated()), id: \.element.id) { offset, image in
                    AsyncImage(url: URL(string: image.url)) { phase in
                        if case .success(let img) = phase {
                            img.resizable().scaledToFit()
                                .frame(maxWidth: .infinity, maxHeight: .infinity)
                        } else {
                            ProgressView()
                        }
                    }
                    .tag(offset)
                    .padding()
                }
            }
            .tabViewStyle(.page(indexDisplayMode: .automatic))
            .background(Color.black)
            .navigationTitle(String(localized: "Gallery"))
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button(String(localized: "Done")) { dismiss() }
                }
            }
        }
    }
}

// MARK: - Shared UX

struct CustomerPremiumEmptyState: View {
    let title: String
    let message: String
    let systemImage: String
    var actionTitle: String? = nil
    var action: (() -> Void)? = nil

    var body: some View {
        VStack(spacing: 16) {
            Image(systemName: systemImage)
                .font(.system(size: 44))
                .foregroundStyle(PAXTheme.accent)
            Text(title)
                .font(.title3.weight(.semibold))
            Text(message)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
            if let actionTitle, let action {
                Button(actionTitle, action: action)
                    .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
            }
        }
        .padding(32)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

struct CustomerPressableCardStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1)
            .animation(PAXTheme.quickSpring, value: configuration.isPressed)
    }
}

// Simple flow layout for tags/services chips.
struct CustomerFlowLayout: Layout {
    var spacing: CGFloat = 8

    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        let result = arrange(proposal: proposal, subviews: subviews)
        return result.size
    }

    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        let result = arrange(proposal: proposal, subviews: subviews)
        for (index, frame) in result.frames.enumerated() {
            subviews[index].place(
                at: CGPoint(x: bounds.minX + frame.minX, y: bounds.minY + frame.minY),
                proposal: ProposedViewSize(frame.size)
            )
        }
    }

    private func arrange(proposal: ProposedViewSize, subviews: Subviews) -> (size: CGSize, frames: [CGRect]) {
        let maxWidth = proposal.width ?? .infinity
        var x: CGFloat = 0
        var y: CGFloat = 0
        var rowHeight: CGFloat = 0
        var frames: [CGRect] = []

        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x + size.width > maxWidth, x > 0 {
                x = 0
                y += rowHeight + spacing
                rowHeight = 0
            }
            frames.append(CGRect(origin: CGPoint(x: x, y: y), size: size))
            rowHeight = max(rowHeight, size.height)
            x += size.width + spacing
        }

        return (CGSize(width: maxWidth, height: y + rowHeight), frames)
    }
}
