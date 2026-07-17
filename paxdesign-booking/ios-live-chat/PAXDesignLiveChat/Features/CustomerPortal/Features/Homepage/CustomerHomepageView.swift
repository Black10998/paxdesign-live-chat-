import SwiftUI

struct CustomerHomepageView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @StateObject private var network = CustomerNetworkMonitor.shared

    @State private var homepage: CustomerHomepageResponse?
    @State private var language: CustomerServicesCatalogLanguage = {
        let code = Locale.current.language.languageCode?.identifier ?? "de"
        return CustomerServicesCatalogLanguage(rawValue: code) ?? .de
    }()
    @State private var selectedPortfolioCategory = "All"
    @State private var error: String?
    @State private var isLoading = true
    @State private var showRequestSheet = false
    @State private var carouselIndex = 0

    var body: some View {
        NavigationStack {
            Group {
                if isLoading && homepage == nil {
                    ProgressView(String(localized: "Loading…"))
                        .tint(ServicesCatalogTheme.accent)
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else if let error, homepage == nil {
                    homepageError(error)
                } else if let homepage {
                    homepageScroll(homepage)
                }
            }
            .background(ServicesCatalogTheme.background.ignoresSafeArea())
            .navigationBarTitleDisplayMode(.inline)
            .toolbarBackground(ServicesCatalogTheme.background, for: .navigationBar)
            .toolbarColorScheme(.dark, for: .navigationBar)
            .refreshable { await load(force: true) }
            .task(id: language.rawValue) { await load(force: false) }
            .sheet(isPresented: $showRequestSheet) {
                NavigationStack {
                    CustomerCreateOrderView()
                        .environmentObject(api)
                }
            }
        }
        .preferredColorScheme(.dark)
    }

    @ViewBuilder
    private func homepageScroll(_ data: CustomerHomepageResponse) -> some View {
        ScrollView {
            LazyVStack(spacing: 0) {
                heroSection(data.hero)
                if !data.service_carousel.isEmpty {
                    serviceCarouselSection(data.service_carousel)
                }
                capabilitiesSection(data.capabilities)
                portfolioSection(data)
                aboutSection(data.about_teaser)
                statsSection(data.stats)
                awardsSection(data.awards)
                testimonialsSection(data.testimonials)
                featuresSection(data.features)
                processSection(data.process)
                newsSection(data.news_section)
            }
        }
        .environment(\.layoutDirection, data.isRTL ? .rightToLeft : .leftToRight)
    }

    private func heroSection(_ hero: CustomerHomepageResponse.Hero) -> some View {
        ZStack(alignment: .bottom) {
            if let urlString = hero.image_url, let url = URL(string: urlString) {
                AsyncImage(url: url) { phase in
                    if case .success(let image) = phase {
                        image.resizable().scaledToFill()
                    } else {
                        ServicesCatalogTheme.background
                    }
                }
                .frame(height: 420)
                .clipped()
                .overlay(
                    LinearGradient(
                        colors: [.black.opacity(0.2), .black.opacity(0.75)],
                        startPoint: .top,
                        endPoint: .bottom
                    )
                )
            } else {
                ServicesCatalogTheme.background.frame(height: 420)
            }

            VStack(spacing: 18) {
                HStack(spacing: 12) {
                    ForEach(Array(hero.tags.enumerated()), id: \.offset) { index, tag in
                        if index > 0 {
                            Text("·").foregroundStyle(ServicesCatalogTheme.accent.opacity(0.72))
                        }
                        Text(tag.uppercased())
                            .font(.system(size: 11, weight: .semibold))
                            .tracking(1.4)
                            .foregroundStyle(Color.white.opacity(0.72))
                    }
                }
                .multilineTextAlignment(.center)

                Text(hero.lead)
                    .font(.system(size: 34, weight: .bold))
                    .multilineTextAlignment(.center)
                    .foregroundStyle(.white)
                    .padding(.horizontal, 20)

                Text(hero.mid)
                    .font(.body)
                    .multilineTextAlignment(.center)
                    .foregroundStyle(Color.white.opacity(0.82))
                    .padding(.horizontal, 24)

                Text(hero.sub)
                    .font(.subheadline)
                    .multilineTextAlignment(.center)
                    .foregroundStyle(Color.white.opacity(0.62))
                    .padding(.horizontal, 28)

                HStack(spacing: 10) {
                    Button(hero.cta_primary) {
                        navigation.selectedTab = .services
                    }
                    .buttonStyle(HomepagePrimaryButtonStyle())

                    Button(hero.cta_secondary) {
                        showRequestSheet = true
                    }
                    .buttonStyle(HomepageSecondaryButtonStyle())
                }
                .padding(.top, 8)
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 36)
        }
    }

    private func serviceCarouselSection(_ cards: [CustomerHomepageResponse.ServiceCard]) -> some View {
        VStack(spacing: 16) {
            TabView(selection: $carouselIndex) {
                ForEach(Array(cards.enumerated()), id: \.element.id) { index, card in
                    HomepageServiceCarouselCard(card: card) {
                        navigation.openServiceRequest(slug: card.order_slug)
                    }
                    .padding(.horizontal, 20)
                    .tag(index)
                }
            }
            .tabViewStyle(.page(indexDisplayMode: .automatic))
            .frame(height: 240)
        }
        .padding(.vertical, 32)
    }

    private func capabilitiesSection(_ section: CustomerHomepageResponse.Capabilities) -> some View {
        VStack(alignment: .leading, spacing: 24) {
            VStack(spacing: 8) {
                Text(section.title)
                    .font(.system(size: 28, weight: .heavy))
                    .foregroundStyle(ServicesCatalogTheme.textPrimary)
                    .multilineTextAlignment(.center)
                    .frame(maxWidth: .infinity)
                Text(section.subtitle)
                    .font(.body)
                    .foregroundStyle(ServicesCatalogTheme.textSecondary)
                    .multilineTextAlignment(.center)
                    .frame(maxWidth: .infinity)
            }
            .padding(.horizontal, 20)

            ForEach(section.items) { item in
                HStack(alignment: .top, spacing: 16) {
                    Text("\(item.number)")
                        .font(.title2.weight(.bold))
                        .foregroundStyle(ServicesCatalogTheme.accent)
                        .frame(width: 36)
                    VStack(alignment: .leading, spacing: 6) {
                        Text(item.title)
                            .font(.headline)
                            .foregroundStyle(ServicesCatalogTheme.textPrimary)
                        Text(item.text)
                            .font(.subheadline)
                            .foregroundStyle(ServicesCatalogTheme.textSecondary)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                }
                .padding(20)
                .background(ServicesCatalogTheme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                .padding(.horizontal, 20)
            }
        }
        .padding(.vertical, 48)
    }

    private func portfolioSection(_ data: CustomerHomepageResponse) -> some View {
        VStack(alignment: .leading, spacing: 20) {
            VStack(spacing: 8) {
                Text(data.portfolio_section.title)
                    .font(.system(size: 24, weight: .heavy))
                    .foregroundStyle(ServicesCatalogTheme.textPrimary)
                    .frame(maxWidth: .infinity)
                Text(data.portfolio_section.subtitle)
                    .font(.body)
                    .foregroundStyle(ServicesCatalogTheme.textSecondary)
                    .multilineTextAlignment(.center)
                    .frame(maxWidth: .infinity)
            }
            .padding(.horizontal, 20)

            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 10) {
                    ForEach(data.portfolio_section.categories, id: \.self) { category in
                        Button(category) { selectedPortfolioCategory = category }
                            .font(.caption.weight(.semibold))
                            .padding(.horizontal, 14)
                            .padding(.vertical, 8)
                            .background(selectedPortfolioCategory == category ? ServicesCatalogTheme.accent : ServicesCatalogTheme.panel)
                            .foregroundStyle(selectedPortfolioCategory == category ? Color.black : ServicesCatalogTheme.textPrimary)
                            .clipShape(Capsule())
                    }
                }
                .padding(.horizontal, 20)
            }

            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 16) {
                    ForEach(data.portfolio_items) { item in
                        NavigationLink {
                            CustomerPortfolioDetailView(slug: item.slug)
                        } label: {
                            HomepagePortfolioCard(item: item)
                        }
                        .buttonStyle(.plain)
                    }
                }
                .padding(.horizontal, 20)
            }

            Button(data.portfolio_section.cta) {
                navigation.selectedTab = .portfolio
            }
            .buttonStyle(HomepagePrimaryButtonStyle())
            .padding(.horizontal, 20)
        }
        .padding(.vertical, 48)
    }

    private func aboutSection(_ about: CustomerHomepageResponse.AboutTeaser) -> some View {
        VStack(spacing: 20) {
            Text(about.title)
                .font(.title2.weight(.bold))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
                .multilineTextAlignment(.center)
            Text(about.subtitle)
                .font(.body)
                .foregroundStyle(ServicesCatalogTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 20)

            VStack(spacing: 12) {
                Text(about.heading)
                    .font(.title3.weight(.semibold))
                    .foregroundStyle(ServicesCatalogTheme.textSecondary)
                Text(about.brand.replacingOccurrences(of: "\n", with: "\n"))
                    .font(.system(size: 32, weight: .heavy))
                    .multilineTextAlignment(.center)
                    .foregroundStyle(ServicesCatalogTheme.textPrimary)
                HStack(spacing: 8) {
                    Text(about.since_label)
                        .foregroundStyle(ServicesCatalogTheme.textSecondary)
                    Text(about.since)
                        .foregroundStyle(ServicesCatalogTheme.accent)
                        .fontWeight(.bold)
                }
                Text(about.about_label)
                    .font(.headline)
                    .foregroundStyle(ServicesCatalogTheme.textPrimary)
                Text(about.about_text)
                    .font(.body)
                    .foregroundStyle(ServicesCatalogTheme.textSecondary)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 24)
            }
            .padding(24)
            .background(ServicesCatalogTheme.panel)
            .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            .padding(.horizontal, 20)

            NavigationLink(about.cta) {
                CustomerAboutView()
            }
            .buttonStyle(HomepagePrimaryButtonStyle())
            .padding(.horizontal, 20)
        }
        .padding(.vertical, 48)
    }

    private func statsSection(_ stats: [CustomerHomepageResponse.Stat]) -> some View {
        HStack(spacing: 12) {
            ForEach(stats) { stat in
                VStack(spacing: 6) {
                    Text("\(stat.value)\(stat.suffix)")
                        .font(.title.weight(.heavy))
                        .foregroundStyle(ServicesCatalogTheme.accent)
                    Text(stat.label)
                        .font(.caption)
                        .foregroundStyle(ServicesCatalogTheme.textSecondary)
                        .multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity)
                .padding(.vertical, 20)
                .background(ServicesCatalogTheme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            }
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 24)
    }

    private func awardsSection(_ awards: CustomerHomepageResponse.Awards) -> some View {
        VStack(spacing: 16) {
            Text(awards.title)
                .font(.title2.weight(.bold))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
                .multilineTextAlignment(.center)
            Text(awards.text)
                .font(.body)
                .foregroundStyle(ServicesCatalogTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 24)
            Text("★★★★★")
                .font(.title3)
                .foregroundStyle(ServicesCatalogTheme.accent)
            Text(awards.rating_label)
                .font(.headline)
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
        }
        .padding(24)
        .frame(maxWidth: .infinity)
        .background(ServicesCatalogTheme.panel)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .padding(.horizontal, 20)
        .padding(.vertical, 32)
    }

    private func testimonialsSection(_ items: [CustomerHomepageResponse.Testimonial]) -> some View {
        VStack(alignment: .leading, spacing: 16) {
            Text(String(localized: "What clients say"))
                .font(.title2.weight(.bold))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
                .padding(.horizontal, 20)

            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 16) {
                    ForEach(items) { item in
                        VStack(alignment: .leading, spacing: 12) {
                            Text(String(repeating: "★", count: max(0, min(item.stars, 5))))
                                .foregroundStyle(ServicesCatalogTheme.accent)
                            Text(item.quote)
                                .font(.subheadline)
                                .foregroundStyle(ServicesCatalogTheme.textSecondary)
                                .fixedSize(horizontal: false, vertical: true)
                            VStack(alignment: .leading, spacing: 2) {
                                Text(item.name)
                                    .font(.headline)
                                    .foregroundStyle(ServicesCatalogTheme.textPrimary)
                                Text(item.role)
                                    .font(.caption)
                                    .foregroundStyle(ServicesCatalogTheme.textSecondary)
                            }
                        }
                        .padding(20)
                        .frame(width: 280, alignment: .leading)
                        .background(ServicesCatalogTheme.cardBackground)
                        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                    }
                }
                .padding(.horizontal, 20)
            }
        }
        .padding(.vertical, 32)
    }

    private func featuresSection(_ features: [CustomerHomepageResponse.FeatureCard]) -> some View {
        LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 16) {
            ForEach(features) { feature in
                VStack(alignment: .leading, spacing: 10) {
                    Text(feature.command)
                        .font(.system(size: 11, weight: .medium, design: .monospaced))
                        .foregroundStyle(ServicesCatalogTheme.accent)
                    Text(feature.title)
                        .font(.subheadline.weight(.bold))
                        .foregroundStyle(ServicesCatalogTheme.textPrimary)
                    Text(feature.text)
                        .font(.caption)
                        .foregroundStyle(ServicesCatalogTheme.textSecondary)
                }
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(ServicesCatalogTheme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            }
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 32)
    }

    private func processSection(_ process: CustomerHomepageResponse.Process) -> some View {
        VStack(spacing: 24) {
            Text(process.title)
                .font(.system(size: 24, weight: .heavy))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
            Text(process.subtitle)
                .font(.body)
                .foregroundStyle(ServicesCatalogTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 20)

            ForEach(process.steps) { step in
                VStack(alignment: .leading, spacing: 10) {
                    HStack {
                        Text(step.number)
                            .font(.caption.weight(.bold))
                            .foregroundStyle(Color.black)
                            .padding(8)
                            .background(ServicesCatalogTheme.accent)
                            .clipShape(Circle())
                        Text(step.title)
                            .font(.headline)
                            .foregroundStyle(ServicesCatalogTheme.textPrimary)
                    }
                    Text(step.text)
                        .font(.subheadline)
                        .foregroundStyle(ServicesCatalogTheme.textSecondary)
                    if let tags = step.tags {
                        FlowLayout(spacing: 8) {
                            ForEach(tags, id: \.self) { tag in
                                Text(tag)
                                    .font(.caption2.weight(.semibold))
                                    .padding(.horizontal, 10)
                                    .padding(.vertical, 5)
                                    .background(ServicesCatalogTheme.panel)
                                    .clipShape(Capsule())
                            }
                        }
                    }
                }
                .padding(20)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(ServicesCatalogTheme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                .padding(.horizontal, 20)
            }
        }
        .padding(.vertical, 48)
    }

    private func newsSection(_ section: CustomerHomepageResponse.NewsSection) -> some View {
        VStack(spacing: 16) {
            Text(section.title)
                .font(.system(size: 24, weight: .heavy))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
            Text(section.subtitle)
                .font(.body)
                .foregroundStyle(ServicesCatalogTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 20)
            NavigationLink(section.cta) {
                CustomerNewsListView()
            }
            .buttonStyle(HomepagePrimaryButtonStyle())
            .padding(.horizontal, 20)
        }
        .padding(.vertical, 48)
        .padding(.bottom, 32)
    }

    private func homepageError(_ message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: network.isConnected ? "exclamationmark.triangle" : "wifi.slash")
                .font(.largeTitle)
                .foregroundStyle(ServicesCatalogTheme.accent)
            Text(message)
                .multilineTextAlignment(.center)
                .foregroundStyle(ServicesCatalogTheme.textSecondary)
            Button(String(localized: "Try again")) { Task { await load(force: true) } }
                .buttonStyle(.borderedProminent)
                .tint(ServicesCatalogTheme.accent)
        }
        .padding(32)
    }

    private func load(force: Bool) async {
        if homepage == nil || force { isLoading = true }
        error = nil
        do {
            homepage = try await api.fetchHomepage(lang: language.rawValue)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct HomepageServiceCarouselCard: View {
    let card: CustomerHomepageResponse.ServiceCard
    let onBook: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                if card.is_new {
                    Text("NEW")
                        .font(.caption2.weight(.heavy))
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(ServicesCatalogTheme.accent)
                        .foregroundStyle(.black)
                        .clipShape(Capsule())
                }
                Spacer()
            }
            Text(card.title)
                .font(.title3.weight(.bold))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
            Text(card.description)
                .font(.subheadline)
                .foregroundStyle(ServicesCatalogTheme.textSecondary)
                .lineLimit(3)
            Button(String(localized: "Book appointment"), action: onBook)
                .font(.caption.weight(.bold))
                .foregroundStyle(ServicesCatalogTheme.accent)
        }
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(ServicesCatalogTheme.cardBackground)
        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
    }
}

private struct HomepagePortfolioCard: View {
    let item: CustomerHomepageResponse.PortfolioItem

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            if let urlString = item.image_url, let url = URL(string: urlString) {
                AsyncImage(url: url) { phase in
                    if case .success(let image) = phase {
                        image.resizable().scaledToFill()
                    } else {
                        Color.gray.opacity(0.2)
                    }
                }
                .frame(width: 260, height: 160)
                .clipped()
            }
            Text(item.title)
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
                .padding(12)
                .frame(width: 260, alignment: .leading)
        }
        .background(ServicesCatalogTheme.cardBackground)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
    }
}

private struct HomepagePrimaryButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.subheadline.weight(.bold))
            .foregroundStyle(.black)
            .padding(.horizontal, 20)
            .padding(.vertical, 12)
            .background(ServicesCatalogTheme.accent.opacity(configuration.isPressed ? 0.85 : 1))
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}

private struct HomepageSecondaryButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.subheadline.weight(.semibold))
            .foregroundStyle(.white)
            .padding(.horizontal, 20)
            .padding(.vertical, 12)
            .background(Color.white.opacity(configuration.isPressed ? 0.08 : 0.12))
            .overlay(RoundedRectangle(cornerRadius: 12).stroke(Color.white.opacity(0.2)))
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}

/// Simple horizontal flow for process tags.
private struct FlowLayout: Layout {
    var spacing: CGFloat = 8

    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        let result = arrange(proposal: proposal, subviews: subviews)
        return result.size
    }

    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        let result = arrange(proposal: proposal, subviews: subviews)
        for (index, frame) in result.frames.enumerated() {
            subviews[index].place(at: CGPoint(x: bounds.minX + frame.minX, y: bounds.minY + frame.minY), proposal: .unspecified)
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
