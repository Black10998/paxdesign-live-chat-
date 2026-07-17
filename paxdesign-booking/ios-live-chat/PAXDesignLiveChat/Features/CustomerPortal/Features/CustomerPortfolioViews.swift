import SwiftUI

// MARK: - List

struct CustomerPortfolioListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.marketingTheme) private var theme
    @State private var showcase: CustomerPortfolioShowcaseResponse?
    @State private var catalog: CustomerPortfolioResponse?
    @State private var selectedShowcaseCategory = ""
    @State private var selectedCatalogCategory = ""
    @State private var error: String?
    @State private var isLoading = true
    @State private var language: CustomerServicesCatalogLanguage = {
        let code = Locale.current.language.languageCode?.identifier ?? "de"
        return CustomerServicesCatalogLanguage(rawValue: code) ?? .de
    }()

    private var catalogColumns: [GridItem] {
        [GridItem(.flexible(), spacing: 16), GridItem(.flexible(), spacing: 16)]
    }

    var body: some View {
        Group {
            if isLoading && showcase == nil && catalog == nil {
                CustomerPortfolioListSkeleton()
            } else if let error, showcase == nil && catalog == nil {
                CustomerPremiumEmptyState(
                    title: String(localized: "Portfolio unavailable"),
                    message: error,
                    systemImage: "photo.on.rectangle",
                    actionTitle: String(localized: "Try again")
                ) { Task { await load(force: true) } }
            } else if filteredShowcaseItems.isEmpty && filteredCatalogItems.isEmpty {
                CustomerPremiumEmptyState(
                    title: String(localized: "No portfolio items yet"),
                    message: String(localized: "Our latest work will appear here once published."),
                    systemImage: "photo.on.rectangle.angled"
                )
            } else {
                portfolioScroll
            }
        }
        .background(theme.background.ignoresSafeArea())
        .navigationTitle(showcase?.header.title ?? String(localized: "Portfolio"))
        .navigationBarTitleDisplayMode(.large)
        .toolbarBackground(theme.background, for: .navigationBar)
        .customerPortalToolbar()
        .task(id: taskKey) { await load() }
        .refreshable { await load(force: true) }
    }

    private var taskKey: String {
        "\(language.rawValue)-\(selectedShowcaseCategory)-\(selectedCatalogCategory)"
    }

    @ViewBuilder
    private var portfolioScroll: some View {
        ScrollView {
            LazyVStack(alignment: isRTL ? .trailing : .leading, spacing: CustomerCalmDesign.sectionSpacing) {
                if let showcase {
                    CustomerCalmSectionIntro(
                        tags: showcase.header.tags,
                        title: showcase.header.title,
                        intro: showcase.header.intro
                    )

                    if let categories = showcase.categories, !categories.isEmpty {
                        showcaseCategoryStrip(categories)
                    }

                    LazyVStack(spacing: CustomerCalmDesign.cardSpacing) {
                        ForEach(Array(filteredShowcaseItems.enumerated()), id: \.element.id) { index, item in
                            NavigationLink {
                                CustomerPortfolioDetailView(slug: item.slug, language: language)
                            } label: {
                                CustomerPortfolioShowcaseCard(item: item, index: index)
                            }
                            .buttonStyle(CustomerPressableCardStyle())
                        }
                    }

                    if let url = URL(string: showcase.cta.url.isEmpty ? "https://paxdesign.at/kontakt" : showcase.cta.url) {
                        CustomerCalmCTABlock(
                            tags: showcase.cta.tags,
                            title: showcase.cta.title,
                            text: showcase.cta.text,
                            button: showcase.cta.button,
                            url: url
                        ) {
                            navigation.selectedTab = .account
                            navigation.accountPath = [CustomerPortalDestination(kind: .contact)]
                            PAXHaptics.light()
                        }
                    }
                }

                if !filteredCatalogItems.isEmpty {
                    originalPortfolioSection
                }
            }
            .padding(.horizontal, CustomerCalmDesign.contentPadding)
            .padding(.vertical, 16)
        }
        .environment(\.layoutDirection, isRTL ? .rightToLeft : .leftToRight)
    }

    private var isRTL: Bool {
        showcase?.isRTL == true || language == .ar
    }

    @ViewBuilder
    private var originalPortfolioSection: some View {
        VStack(alignment: .leading, spacing: 20) {
            Divider().padding(.vertical, 8)

            VStack(alignment: .leading, spacing: 10) {
                Text(String(localized: "Full portfolio"))
                    .font(.title2.weight(.bold))
                    .foregroundStyle(theme.textPrimary)
                Text(String(localized: "Browse our complete project catalog."))
                    .font(.body)
                    .foregroundStyle(theme.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
            }

            if let categories = catalog?.categories, !categories.isEmpty {
                catalogCategoryStrip(categories)
            }

            LazyVGrid(columns: catalogColumns, spacing: 16) {
                ForEach(Array(filteredCatalogItems.enumerated()), id: \.element.id) { index, item in
                    NavigationLink {
                        CustomerPortfolioDetailView(slug: item.slug, language: language)
                    } label: {
                        CustomerPortfolioClassicCard(item: item, index: index)
                    }
                    .buttonStyle(CustomerPressableCardStyle())
                }
            }
        }
    }

    private func showcaseCategoryStrip(_ categories: [CustomerPortfolioResponse.Category]) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 10) {
                CustomerCalmCategoryChip(
                    title: String(localized: "All"),
                    isSelected: selectedShowcaseCategory.isEmpty
                ) {
                    withAnimation(PAXTheme.quickSpring) { selectedShowcaseCategory = "" }
                    PAXHaptics.light()
                }
                ForEach(categories, id: \.slug) { category in
                    CustomerCalmCategoryChip(
                        title: category.name,
                        isSelected: selectedShowcaseCategory == category.slug
                    ) {
                        withAnimation(PAXTheme.quickSpring) { selectedShowcaseCategory = category.slug }
                        PAXHaptics.light()
                    }
                }
            }
        }
    }

    private func catalogCategoryStrip(_ categories: [CustomerPortfolioResponse.Category]) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 10) {
                catalogCategoryChip("", title: String(localized: "All"))
                ForEach(categories, id: \.slug) { category in
                    catalogCategoryChip(category.slug, title: category.name)
                }
            }
        }
    }

    private func catalogCategoryChip(_ slug: String, title: String) -> some View {
        Button(title) {
            withAnimation(PAXTheme.quickSpring) { selectedCatalogCategory = slug }
            PAXHaptics.light()
        }
        .font(.subheadline.weight(.semibold))
        .padding(.horizontal, 14)
        .padding(.vertical, 9)
        .background(selectedCatalogCategory == slug ? theme.accent : theme.panel)
        .foregroundStyle(selectedCatalogCategory == slug ? theme.accentOnAccent : theme.textPrimary)
        .clipShape(Capsule())
        .accessibilityAddTraits(selectedCatalogCategory == slug ? .isSelected : [])
    }

    private var filteredShowcaseItems: [CustomerPortfolioItem] {
        guard let items = showcase?.items else { return [] }
        guard !selectedShowcaseCategory.isEmpty else { return items }
        return items.filter { item in
            item.category_slugs?.contains(selectedShowcaseCategory) == true
        }
    }

    private var filteredCatalogItems: [CustomerPortfolioItem] {
        guard let items = catalog?.items else { return [] }
        guard !selectedCatalogCategory.isEmpty else { return items }
        return items.filter { item in
            item.category_slugs?.contains(selectedCatalogCategory) == true
        }
    }

    private func load(force: Bool = false) async {
        if (showcase == nil && catalog == nil) || force { isLoading = true }
        error = nil
        async let showcaseTask = try? api.fetchPortfolioShowcase(lang: language.rawValue)
        async let catalogTask = try? api.fetchPortfolio(
            category: selectedCatalogCategory.isEmpty ? nil : selectedCatalogCategory,
            lang: language.rawValue
        )
        let loadedShowcase = await showcaseTask
        let loadedCatalog = await catalogTask
        if let loadedShowcase {
            showcase = loadedShowcase
        }
        if let loadedCatalog {
            catalog = loadedCatalog
        }
        if showcase == nil && catalog == nil {
            error = String(localized: "Unable to load portfolio.")
        }
        isLoading = false
    }
}

// MARK: - Cards

struct CustomerPortfolioShowcaseCard: View {
    @Environment(\.marketingTheme) private var theme
    let item: CustomerPortfolioItem
    var index: Int = 0

    var body: some View {
        CustomerCalmShellCard {
            VStack(alignment: .leading, spacing: 0) {
                portfolioImage
                VStack(alignment: .leading, spacing: 12) {
                    if let client = item.client, !client.isEmpty {
                        Text(client.uppercased())
                            .font(.system(size: 11, weight: .semibold))
                            .tracking(1.1)
                            .foregroundStyle(theme.textSecondary)
                    }
                    Text(item.displayTitle)
                        .font(.title3.weight(.semibold))
                        .foregroundStyle(theme.textPrimary)
                        .multilineTextAlignment(.leading)
                        .fixedSize(horizontal: false, vertical: true)
                    if !item.displayExcerpt.isEmpty {
                        Text(item.displayExcerpt)
                            .font(.body)
                            .foregroundStyle(theme.textSecondary)
                            .lineSpacing(4)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    if let stats = item.stats, !stats.isEmpty {
                        CustomerCalmStatGrid(stats: stats)
                    }
                }
                .padding(20)
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel(item.displayTitle)
        .accessibilityHint(String(localized: "Opens project details"))
    }

    @ViewBuilder
    private var portfolioImage: some View {
        ZStack(alignment: .topLeading) {
            if let imageURL = item.image_url, let url = URL(string: imageURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        SkeletonBlock(height: 220, cornerRadius: 0)
                    }
                }
                .frame(maxWidth: .infinity)
                .frame(height: 220)
                .clipped()
            } else {
                Rectangle()
                    .fill(theme.panel)
                    .frame(height: 220)
                    .overlay {
                        Image(systemName: "photo.on.rectangle.angled")
                            .font(.title2)
                            .foregroundStyle(theme.accent)
                    }
            }

            if !item.displayCategory.isEmpty {
                Text(item.displayCategory)
                    .font(.system(size: 11, weight: .semibold))
                    .tracking(0.6)
                    .foregroundStyle(theme.accentOnAccent)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(theme.accent)
                    .clipShape(Capsule())
                    .padding(14)
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

struct CustomerPortfolioClassicCard: View {
    @Environment(\.marketingTheme) private var theme
    let item: CustomerPortfolioItem
    var index: Int = 0

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            if let imageURL = item.image_url, let url = URL(string: imageURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        SkeletonBlock(height: 120, cornerRadius: 0)
                    }
                }
                .frame(maxWidth: .infinity)
                .frame(height: 120)
                .clipped()
            }
            VStack(alignment: .leading, spacing: 6) {
                Text(item.displayTitle)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(theme.textPrimary)
                    .multilineTextAlignment(.leading)
                    .lineLimit(3)
                    .fixedSize(horizontal: false, vertical: true)
                if !item.displayCategory.isEmpty {
                    Text(item.displayCategory)
                        .font(.caption)
                        .foregroundStyle(theme.textSecondary)
                        .lineLimit(1)
                }
            }
            .padding(12)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(theme.cardBackground)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .stroke(theme.border, lineWidth: 0.5)
        )
    }
}

// MARK: - Detail

struct CustomerPortfolioDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @Environment(\.marketingTheme) private var theme
    @Environment(\.horizontalSizeClass) private var horizontalSizeClass
    let slug: String
    var language: CustomerServicesCatalogLanguage = {
        let code = Locale.current.language.languageCode?.identifier ?? "de"
        return CustomerServicesCatalogLanguage(rawValue: code) ?? .de
    }()
    @State private var item: CustomerPortfolioDetail?
    @State private var error: String?
    @State private var selectedGalleryIndex: Int?

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
        .background(theme.background.ignoresSafeArea())
        .navigationTitle(item?.displayTitle ?? String(localized: "Portfolio"))
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(theme.background, for: .navigationBar)
        .task(id: slug) { await load() }
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
        Group {
            if let imageURL = item.image_url, let url = URL(string: imageURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        SkeletonBlock(height: 240, cornerRadius: 0)
                    }
                }
                .frame(maxWidth: .infinity)
                .frame(height: 240)
                .clipped()
            } else {
                Rectangle()
                    .fill(theme.panel)
                    .frame(maxWidth: .infinity)
                    .frame(height: 180)
            }
        }
        .accessibilityHidden(true)
    }

    @ViewBuilder
    private func detailHeader(_ item: CustomerPortfolioDetail) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            if let client = item.client, !client.isEmpty {
                CustomerResponsiveCaption(text: client.uppercased(), color: theme.textSecondary)
            } else if let categories = item.categories, !categories.isEmpty {
                CustomerResponsiveCaption(text: categories.joined(separator: " · "), color: theme.textSecondary)
            }
            CustomerResponsiveTitle(text: item.displayTitle, color: theme.textPrimary)
            if !item.displaySubtitle.isEmpty {
                CustomerResponsiveBody(text: item.displaySubtitle, color: theme.textSecondary)
            }
        }
        .customerConstrainedContent()
        .padding(.bottom, 8)
    }

    @ViewBuilder
    private func detailContent(_ item: CustomerPortfolioDetail) -> some View {
        VStack(alignment: .leading, spacing: CustomerCalmDesign.sectionSpacing) {
            detailHeader(item)

            if let structured = item.structuredDetail {
                if let stats = structured.stats, !stats.isEmpty {
                    detailStatsSection(stats)
                }
                if let metadata = structured.metadata, !metadata.isEmpty {
                    metadataSection(metadata)
                }
                if let sections = structured.sections, !sections.isEmpty {
                    narrativeSections(sections)
                } else if let paragraphs = structured.paragraphs, !paragraphs.isEmpty {
                    ForEach(Array(paragraphs.enumerated()), id: \.offset) { _, paragraph in
                        CustomerResponsiveBody(
                            text: CustomerPortfolioTextSanitizer.clean(paragraph),
                            color: theme.textPrimary
                        )
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
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, CustomerCalmDesign.contentPadding)
        .padding(.vertical, 28)
    }

    private var metadataColumns: [GridItem] {
        CustomerResponsiveLayout.metadataColumns(for: horizontalSizeClass)
    }

    private func detailStatsSection(_ stats: [CustomerPortfolioStructuredDetail.Stat]) -> some View {
        LazyVGrid(columns: CustomerResponsiveLayout.statColumns(for: horizontalSizeClass), spacing: 12) {
            ForEach(stats) { stat in
                VStack(alignment: .leading, spacing: 4) {
                    Text(stat.value)
                        .font(.title2.weight(.bold))
                        .foregroundStyle(theme.accent)
                        .minimumScaleFactor(0.85)
                        .lineLimit(2)
                        .fixedSize(horizontal: false, vertical: true)
                    CustomerResponsiveCaption(text: stat.label, color: theme.textSecondary)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(14)
                .background(theme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                .overlay(
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .stroke(theme.border, lineWidth: 0.5)
                )
            }
        }
    }

    private func metadataSection(_ rows: [CustomerPortfolioStructuredDetail.Metadata]) -> some View {
        LazyVGrid(columns: metadataColumns, spacing: 12) {
            ForEach(rows) { row in
                VStack(alignment: .leading, spacing: 6) {
                    Text(row.label)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(theme.textSecondary)
                        .textCase(.uppercase)
                        .fixedSize(horizontal: false, vertical: true)
                    if let link = row.link, !link.isEmpty, let url = URL(string: link) {
                        Link(destination: url) {
                            Text(row.value)
                                .font(.subheadline.weight(.medium))
                                .multilineTextAlignment(.leading)
                                .fixedSize(horizontal: false, vertical: true)
                                .frame(maxWidth: .infinity, alignment: .leading)
                        }
                    } else {
                        Text(row.value)
                            .font(.subheadline.weight(.medium))
                            .foregroundStyle(theme.textPrimary)
                            .multilineTextAlignment(.leading)
                            .fixedSize(horizontal: false, vertical: true)
                            .frame(maxWidth: .infinity, alignment: .leading)
                    }
                }
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(theme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                .overlay(
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .stroke(theme.border, lineWidth: 0.5)
                )
            }
        }
    }

    private func narrativeSections(_ sections: [CustomerPortfolioStructuredDetail.Section]) -> some View {
        VStack(alignment: .leading, spacing: 24) {
            ForEach(sections) { section in
                VStack(alignment: .leading, spacing: 10) {
                    CustomerResponsiveHeadline(text: section.title, color: theme.textPrimary)
                    CustomerResponsiveBody(
                        text: CustomerPortfolioTextSanitizer.clean(section.body),
                        color: theme.textSecondary
                    )
                }
                .customerConstrainedContent()
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
                        .background(theme.accent.opacity(0.14))
                        .foregroundStyle(theme.accent)
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
                        .background(theme.panel)
                        .foregroundStyle(theme.textSecondary)
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
                .foregroundStyle(theme.textSecondary)
        }
        if let excerpt = item.excerpt, !item.displaySubtitle.isEmpty {
            Text(item.displaySubtitle)
                .font(.body)
                .foregroundStyle(theme.textPrimary)
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
            item = try await api.fetchPortfolioItem(slug: slug, lang: language.rawValue)
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
    @Environment(\.marketingTheme) private var theme
    let title: String
    let message: String
    let systemImage: String
    var actionTitle: String? = nil
    var action: (() -> Void)? = nil

    var body: some View {
        VStack(spacing: 16) {
            Image(systemName: systemImage)
                .font(.system(size: 44))
                .foregroundStyle(theme.accent)
            Text(title)
                .font(.title3.weight(.semibold))
                .foregroundStyle(theme.textPrimary)
            Text(message)
                .font(.subheadline)
                .foregroundStyle(theme.textSecondary)
                .multilineTextAlignment(.center)
            if let actionTitle, let action {
                Button(actionTitle, action: action)
                    .buttonStyle(CustomerCalmAccentButtonStyle())
            }
        }
        .padding(32)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

struct CustomerPressableCardStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.985 : 1)
            .offset(y: configuration.isPressed ? 0 : 0)
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
